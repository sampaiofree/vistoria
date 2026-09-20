<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionReportRevision
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Inspection $inspection, User $actor, int $reportRevision): Inspection
    {
        return DB::transaction(function () use ($inspection, $actor, $reportRevision): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->with('responsibles')
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            Equipment::query()
                ->forOrganization($this->tenant->id())
                ->whereKey($inspection->equipment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $actor->isActive()
                || $actor->isSuperAdmin()
                || $actor->operational_role !== OperationalRole::Inspector
                || ! $actor->belongsToOrganization($this->tenant->id())
                || ! in_array($inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)
                || ! $inspection->hasAnyResponsibilityForUser($actor, ...\App\Enums\InspectionResponsibility::cases())) {
                throw ValidationException::withMessages([
                    'report_revision' => 'Apenas o Inspetor responsável pode alterar a revisão durante a inspeção ou correção.',
                ]);
            }

            $alreadyReserved = Inspection::query()
                ->where('organization_id', $this->tenant->id())
                ->where('equipment_id', $inspection->equipment_id)
                ->where('report_revision', $reportRevision)
                ->whereKeyNot($inspection->getKey())
                ->exists();

            if ($alreadyReserved) {
                throw ValidationException::withMessages([
                    'report_revision' => 'Esta revisão já está reservada para outra inspeção deste equipamento.',
                ]);
            }

            $inspection->update([
                'report_revision' => $reportRevision,
                'updated_by' => $actor->getKey(),
            ]);

            return $inspection->refresh();
        });
    }
}
