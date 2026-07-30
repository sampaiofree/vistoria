<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePlannedInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Inspection $inspection, User $actor, array $data): Inspection
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode editar inspeções na organização atual.',
            ]);
        }

        return DB::transaction(function () use ($inspection, $actor, $data): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            if ($inspection->status !== InspectionStatus::Planned) {
                throw ValidationException::withMessages([
                    'status' => 'A inspeção só pode ser editada enquanto estiver planejada.',
                ]);
            }

            $inspection->update([
                'service_order' => TextNormalizer::nullableText($data['service_order'] ?? null),
                'external_report_number' => TextNormalizer::nullableText($data['external_report_number'] ?? null),
                'procedure_number' => TextNormalizer::nullableText($data['procedure_number'] ?? null),
                'atmospheric_classification' => TextNormalizer::nullableText($data['atmospheric_classification'] ?? null),
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'general_notes' => TextNormalizer::nullableText($data['general_notes'] ?? null),
                'updated_by' => $actor->getKey(),
            ]);

            return $inspection->refresh();
        });
    }
}
