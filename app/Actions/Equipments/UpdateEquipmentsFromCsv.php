<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Models\Equipment;
use App\Models\User;
use App\Services\Equipments\EquipmentCsv;
use App\Services\Equipments\EquipmentUpdateRules;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateEquipmentsFromCsv
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly EquipmentCsv $csv,
        private readonly UpdateEquipment $updateEquipment,
    ) {}

    /** @param array{rows:array<int, array{line:int, values:array<string, string>}>} $state
     * @param  array<string, ?string>  $mapping
     * @return array<string, mixed>
     */
    public function plan(User $actor, array $state, array $mapping): array
    {
        $prepared = collect($state['rows'])->map(function (array $row) use ($mapping): array {
            $values = $this->csv->mapRow($mapping, $row['values']);

            return [
                'line' => $row['line'],
                'code' => TextNormalizer::technicalCode($values['maintenance_item_code']),
                'values' => $values,
            ];
        });
        $codeCounts = $prepared->pluck('code')->filter()->countBy();
        $uniqueFields = ['numero_cliente', 'numero_interno', 'defect_code_prefix'];
        $proposedCounts = [];
        foreach ($prepared as $source) {
            foreach ($uniqueFields as $field) {
                if (($mapping[$field] ?? null) === null) {
                    continue;
                }

                $value = TextNormalizer::technicalCode($source['values'][$field] ?? null);
                if ($value !== null) {
                    $proposedCounts[$field][$value] = ($proposedCounts[$field][$value] ?? 0) + 1;
                }
            }
        }
        $rows = [];
        $summary = ['total' => $prepared->count(), 'ready' => 0, 'unchanged' => 0, 'rejected' => 0, 'related' => 0];

        foreach ($prepared as $source) {
            $code = $source['code'];
            $base = ['line' => $source['line'], 'maintenance_item_code' => $code, 'tag' => null];

            if ($code === null) {
                $rows[] = $this->rejected($base, 'Informe o item de manutenção.');
            } elseif (($codeCounts[$code] ?? 0) > 1) {
                $rows[] = $this->rejected($base, 'Item manutenção repetido no CSV.');
            } else {
                $equipment = Equipment::query()->withTrashed()
                    ->forOrganization($this->tenant->id())
                    ->where('maintenance_item_code', $code)
                    ->first();

                if ($equipment === null || $equipment->trashed()) {
                    $rows[] = $this->rejected($base, 'Item de manutenção não encontrado entre os equipamentos disponíveis da organização.');
                } elseif (! $actor->can('update', $equipment)) {
                    $rows[] = $this->rejected($base, 'Você não tem permissão para atualizar este equipamento.');
                } else {
                    $base['tag'] = $equipment->tag;
                    $updates = $this->updates($source['values'], $mapping);
                    $changes = [];

                    foreach ($updates as $field => $value) {
                        $previous = $equipment->getAttribute($field);
                        if ($previous !== $value) {
                            $changes[] = [
                                'field' => $field,
                                'label' => EquipmentCsv::FIELDS[$field],
                                'from' => $previous,
                                'to' => $value,
                            ];
                        } else {
                            unset($updates[$field]);
                        }
                    }

                    if ($changes === []) {
                        $rows[] = [...$base, 'status' => 'unchanged'];
                    } else {
                        $candidate = $this->candidate($equipment, $updates);
                        $errors = $this->errors($equipment, $candidate);
                        foreach ($uniqueFields as $field) {
                            if (isset($updates[$field]) && ($proposedCounts[$field][$updates[$field]] ?? 0) > 1) {
                                $errors[] = EquipmentCsv::FIELDS[$field].' repetido no CSV.';
                            }
                        }

                        if ($errors !== []) {
                            $rows[] = $this->rejected($base, implode(' ', $errors));
                        } else {
                            $related = $equipment->inspections()->exists() || $equipment->defects()->exists();
                            $rows[] = [
                                ...$base,
                                'status' => 'ready',
                                'equipment_id' => $equipment->getKey(),
                                'fingerprint' => $this->fingerprint($equipment),
                                'updates' => $updates,
                                'changes' => $changes,
                                'related' => $related,
                            ];
                            if ($related) {
                                $summary['related']++;
                            }
                        }
                    }
                }
            }

            $summary[$rows[array_key_last($rows)]['status']]++;
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function apply(User $actor, array $plan, bool $confirmRelatedRecords): array
    {
        if ($plan['summary']['related'] > 0 && ! $confirmRelatedRecords) {
            throw ValidationException::withMessages([
                'confirm_related_records_edit' => 'Confirme a atualização dos equipamentos que possuem inspeções ou avarias vinculadas.',
            ]);
        }

        $result = ['total' => $plan['summary']['total'], 'updated' => 0, 'unchanged' => 0, 'rejected' => []];

        foreach ($plan['rows'] as $row) {
            if ($row['status'] === 'unchanged') {
                $result['unchanged']++;

                continue;
            }

            if ($row['status'] === 'rejected') {
                $result['rejected'][] = $this->publicRejection($row);

                continue;
            }

            try {
                DB::transaction(function () use ($actor, $row, $confirmRelatedRecords): void {
                    $equipment = Equipment::query()
                        ->forOrganization($this->tenant->id())
                        ->lockForUpdate()
                        ->find($row['equipment_id']);

                    if ($equipment === null || $equipment->maintenance_item_code !== $row['maintenance_item_code']) {
                        throw ValidationException::withMessages(['equipment' => 'O equipamento não está mais disponível para atualização.']);
                    }

                    if (! $actor->can('update', $equipment)) {
                        throw ValidationException::withMessages(['equipment' => 'Você não tem permissão para atualizar este equipamento.']);
                    }

                    if ($this->fingerprint($equipment) !== $row['fingerprint']) {
                        throw ValidationException::withMessages(['equipment' => 'O equipamento mudou desde a prévia. Envie o CSV novamente.']);
                    }

                    $candidate = $this->candidate($equipment, $row['updates']);
                    $errors = $this->errors($equipment, $candidate);
                    if ($errors !== []) {
                        throw ValidationException::withMessages(['equipment' => $errors]);
                    }

                    $this->updateEquipment->handle($actor, $equipment, [
                        ...$candidate,
                        'confirm_related_records_edit' => $confirmRelatedRecords,
                    ]);
                });
                $result['updated']++;
            } catch (ValidationException $exception) {
                $row['reason'] = implode(' ', collect($exception->errors())->flatten()->unique()->all());
                $result['rejected'][] = $this->publicRejection($row);
            } catch (QueryException) {
                $row['reason'] = 'Não foi possível atualizar esta linha. Confira os identificadores e tente novamente.';
                $result['rejected'][] = $this->publicRejection($row);
            }
        }

        return $result;
    }

    /** @param array<string, ?string> $values
     * @param  array<string, ?string>  $mapping
     * @return array<string, string>
     */
    private function updates(array $values, array $mapping): array
    {
        $updates = [];
        foreach ($mapping as $field => $column) {
            if ($field === 'maintenance_item_code' || $column === null) {
                continue;
            }

            $raw = $values[$field] ?? null;
            if ($raw === null || trim($raw) === '') {
                continue;
            }

            $normalized = match ($field) {
                'tag' => TextNormalizer::equipmentTag($raw),
                'name' => TextNormalizer::text($raw),
                'description', 'installation_location', 'area_name', 'subarea_name' => TextNormalizer::nullableText($raw),
                default => TextNormalizer::technicalCode($raw),
            };

            if ($normalized !== null && $normalized !== '') {
                $updates[$field] = $normalized;
            }
        }

        return $updates;
    }

    /** @param array<string, string> $updates
     * @return array<string, mixed>
     */
    private function candidate(Equipment $equipment, array $updates): array
    {
        return array_replace($equipment->only(array_keys(EquipmentCsv::FIELDS)), $updates);
    }

    /** @param array<string, mixed> $candidate
     * @return array<int, string>
     */
    private function errors(Equipment $equipment, array $candidate): array
    {
        $errors = Validator::make(
            $candidate,
            EquipmentUpdateRules::for($this->tenant->id(), $equipment->getKey()),
            [],
            EquipmentCsv::FIELDS,
        )->errors()->all();

        foreach (['numero_cliente', 'numero_interno', 'defect_code_prefix'] as $field) {
            if (Equipment::query()->withTrashed()->forOrganization($this->tenant->id())
                ->where($field, $candidate[$field])
                ->whereKeyNot($equipment->getKey())
                ->exists()) {
                $errors[] = 'Já existe um equipamento com este '.EquipmentCsv::FIELDS[$field].' na organização.';
            }
        }

        return array_values(array_unique($errors));
    }

    private function fingerprint(Equipment $equipment): string
    {
        return hash('sha256', serialize($equipment->getAttributes()));
    }

    /** @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function rejected(array $base, string $reason): array
    {
        return [...$base, 'status' => 'rejected', 'reason' => $reason];
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicRejection(array $row): array
    {
        return [
            'line' => $row['line'],
            'maintenance_item_code' => $row['maintenance_item_code'],
            'tag' => $row['tag'],
            'reason' => $row['reason'],
        ];
    }
}
