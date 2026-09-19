<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Models\Client;
use App\Models\User;
use App\Services\Equipments\EquipmentCsv;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ImportEquipmentsFromCsv
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CreateEquipment $createEquipment,
        private readonly EquipmentCsv $csv,
    ) {}

    /** @param array{rows:array<int, array{line:int, values:array<string, string>}>} $state
     * @param  array<string, ?string>  $mapping
     * @return array{total:int, created:int, rejected:array<int, array{line:int, maintenance_item_code:?string, tag:?string, reason:string}>}
     */
    public function handle(User $actor, array $state, array $mapping): array
    {
        $client = Client::query()->forOrganization($this->tenant->id())->first();
        if ($client === null || ! $client->isActive()) {
            throw ValidationException::withMessages([
                'client' => $client === null
                    ? 'Cadastre o cliente em Configurações antes de importar equipamentos.'
                    : 'Ative o cliente em Configurações antes de importar equipamentos.',
            ]);
        }

        $prepared = collect($state['rows'])->map(function (array $row) use ($mapping): array {
            $data = $this->csv->mapRow($mapping, $row['values']);

            return [...$row, 'data' => $this->normalize($data)];
        })->all();

        $itemCounts = collect($prepared)
            ->pluck('data.maintenance_item_code')
            ->filter()
            ->countBy();
        $prefixCounts = collect($prepared)
            ->pluck('data.defect_code_prefix')
            ->filter()
            ->countBy();

        $created = 0;
        $rejected = [];
        foreach ($prepared as $row) {
            $data = $row['data'];
            $reasons = [];

            if (($data['maintenance_item_code'] ?? null) !== null && ($itemCounts[$data['maintenance_item_code']] ?? 0) > 1) {
                $reasons[] = 'Item manutenção repetido no CSV.';
            }

            if (($data['defect_code_prefix'] ?? null) !== null && ($prefixCounts[$data['defect_code_prefix']] ?? 0) > 1) {
                $reasons[] = 'Prefixo de avaria repetido no CSV.';
            }

            $validator = Validator::make(
                $data,
                [
                    'maintenance_plan_code' => ['nullable', 'string', 'max:80'],
                    'maintenance_item_code' => ['required', 'string', 'max:80'],
                    'tag' => ['required', 'string', 'max:120'],
                    'description' => ['nullable', 'string', 'max:10000'],
                    'installation_location' => ['nullable', 'string', 'max:255'],
                    'area_code' => ['nullable', 'string', 'max:80'],
                    'area_name' => ['nullable', 'string', 'max:180'],
                    'subarea_code' => ['nullable', 'string', 'max:80'],
                    'subarea_name' => ['nullable', 'string', 'max:180'],
                    'name' => ['required', 'string', 'max:180'],
                    'task_list_group' => ['nullable', 'string', 'max:80'],
                    'task_list_group_counter' => ['nullable', 'string', 'max:80'],
                    'abc_code' => ['nullable', 'string', 'max:20'],
                    'defect_code_prefix' => ['required', 'string', 'max:80'],
                ],
                [],
                EquipmentCsv::FIELDS,
            );

            if ($validator->fails()) {
                $reasons = [...$reasons, ...collect($validator->errors()->all())->unique()->all()];
            }

            if ($reasons === []) {
                try {
                    $this->createEquipment->handle($actor, $data);
                    $created++;

                    continue;
                } catch (ValidationException $exception) {
                    $reasons = $exception->errors() === [] ? ['Dados inválidos.'] : collect($exception->errors())->flatten()->unique()->all();
                } catch (QueryException) {
                    $reasons = ['Não foi possível registrar esta linha. Tente novamente e, se o problema persistir, contate o suporte informando o número da linha.'];
                }
            }

            $rejected[] = [
                'line' => $row['line'],
                'maintenance_item_code' => $data['maintenance_item_code'] ?? null,
                'tag' => $data['tag'] ?? null,
                'reason' => implode(' ', $reasons),
            ];
        }

        return ['total' => count($prepared), 'created' => $created, 'rejected' => $rejected];
    }

    /** @param array<string, ?string> $data
     * @return array<string, ?string>
     */
    private function normalize(array $data): array
    {
        return [
            'maintenance_plan_code' => TextNormalizer::technicalCode($data['maintenance_plan_code'] ?? null),
            'maintenance_item_code' => TextNormalizer::technicalCode($data['maintenance_item_code'] ?? null),
            'tag' => TextNormalizer::equipmentTag((string) ($data['tag'] ?? '')),
            'description' => TextNormalizer::nullableText($data['description'] ?? null),
            'installation_location' => TextNormalizer::nullableText($data['installation_location'] ?? null),
            'area_code' => TextNormalizer::technicalCode($data['area_code'] ?? null),
            'area_name' => TextNormalizer::nullableText($data['area_name'] ?? null),
            'subarea_code' => TextNormalizer::technicalCode($data['subarea_code'] ?? null),
            'subarea_name' => TextNormalizer::nullableText($data['subarea_name'] ?? null),
            'name' => TextNormalizer::text((string) ($data['name'] ?? '')),
            'task_list_group' => TextNormalizer::technicalCode($data['task_list_group'] ?? null),
            'task_list_group_counter' => TextNormalizer::technicalCode($data['task_list_group_counter'] ?? null),
            'abc_code' => TextNormalizer::technicalCode($data['abc_code'] ?? null),
            'defect_code_prefix' => TextNormalizer::technicalCode($data['defect_code_prefix'] ?? null),
        ];
    }
}
