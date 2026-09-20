<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\User;
use App\Services\Defects\RefreshDefectAssessmentQuantityState;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteDefectAssessmentQuantity
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RefreshDefectAssessmentQuantityState $refreshState,
    ) {}

    public function handle(User $actor, DefectAssessmentQuantity $quantity): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $quantity): DefectAssessment {
            $quantity = DefectAssessmentQuantity::query()
                ->forOrganization($this->tenant->id())
                ->whereKey($quantity->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['inspection', 'defect', 'locationMapVersion', 'location'])
                ->lockForUpdate()
                ->findOrFail($quantity->defect_assessment_id);

            $this->ensureEditable($actor, $assessment);
            $wasComplete = $assessment->status === DefectAssessmentStatus::Complete;
            $quantity->delete();

            $remaining = DefectAssessmentQuantity::query()
                ->where('organization_id', $assessment->organization_id)
                ->where('defect_assessment_id', $assessment->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
            foreach ($remaining as $item) {
                $item->update(['position' => 1000000000 + $item->position]);
            }
            foreach ($remaining->values() as $index => $item) {
                $item->update(['position' => $index + 1]);
            }

            return $this->refreshState->handle($assessment, $actor, $wasComplete);
        });
    }

    private function ensureEditable(User $actor, DefectAssessment $assessment): void
    {
        if (! $actor->isActive()
            || $actor->isSuperAdmin()
            || ! $actor->belongsToOrganization($this->tenant->id())
            || ! $actor->can('manageFieldContent', $assessment->inspection)) {
            throw ValidationException::withMessages([
                'quantity' => 'A avaliação não está disponível para editar o quantitativo.',
            ]);
        }
    }
}
