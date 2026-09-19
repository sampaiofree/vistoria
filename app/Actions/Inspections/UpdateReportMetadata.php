<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateReportMetadata
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Inspection $inspection, User $actor, array $data): Inspection
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode editar os dados do relatório na organização atual.',
            ]);
        }

        return DB::transaction(function () use ($inspection, $actor, $data): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            $reportDate = isset($data['report_date']) && $data['report_date'] !== null
                ? CarbonImmutable::parse((string) $data['report_date'])->toDateString()
                : null;

            if ($actor->operational_role === OperationalRole::Inspector) {
                $restrictedChanges = [
                    'emission_type' => $inspection->emission_type?->value !== ($data['emission_type'] ?? null),
                    'report_date' => $inspection->report_date?->toDateString() !== $reportDate,
                    'service_order' => $inspection->service_order !== TextNormalizer::nullableText($data['service_order'] ?? null),
                    'external_report_number' => array_key_exists('external_report_number', $data)
                        && $inspection->external_report_number !== TextNormalizer::nullableText($data['external_report_number']),
                ];

                $errors = collect($restrictedChanges)
                    ->filter()
                    ->mapWithKeys(fn (bool $_, string $field): array => [
                        $field => 'Este campo é somente leitura para usuários com papel Inspetor.',
                    ])
                    ->all();

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }

            $currentDate = $inspection->report_date?->toDateString();
            $dateChanged = $currentDate !== $reportDate;
            if ($inspection->status === InspectionStatus::Released && $dateChanged && ! ($data['confirm_revision_reorder'] ?? false)) {
                throw ValidationException::withMessages([
                    'confirm_revision_reorder' => 'Confirme a alteração da data, pois a cronologia do equipamento poderá ser renumerada.',
                ]);
            }

            $attributes = [
                'emission_type' => $data['emission_type'] ?? null,
                'report_date' => $reportDate,
                'service_order' => TextNormalizer::nullableText($data['service_order'] ?? null),
                'first_page_text_template' => blank($data['first_page_text_template'] ?? null)
                    ? null
                    : trim((string) $data['first_page_text_template']),
                'updated_by' => $actor->getKey(),
            ];

            if (array_key_exists('external_report_number', $data)) {
                $attributes['external_report_number'] = TextNormalizer::nullableText($data['external_report_number']);
            }

            if (array_key_exists('report_designer', $data)) {
                $attributes['report_designer'] = TextNormalizer::text((string) $data['report_designer']);
            }

            if (array_key_exists('designer_i_report_number', $data)) {
                $attributes['designer_i_report_number'] = TextNormalizer::nullableText($data['designer_i_report_number']);
            }

            $inspection->update($attributes);

            return $inspection->refresh();
        });
    }
}
