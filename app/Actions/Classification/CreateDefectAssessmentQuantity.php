<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\User;
use App\Services\Defects\NativeDefectQuantityCalculator;
use App\Services\Defects\RefreshDefectAssessmentQuantityState;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDefectAssessmentQuantity
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NativeDefectQuantityCalculator $calculator,
        private readonly RefreshDefectAssessmentQuantityState $refreshState,
    ) {}

    /** @param array{description?: string|null, quantity: array<string, mixed>} $data */
    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['inspection', 'defect', 'locationMapVersion', 'location'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $this->ensureEditable($actor, $assessment);
            $wasComplete = $assessment->status === DefectAssessmentStatus::Complete;
            $measurement = $this->calculator->calculate($assessment->defect->category, $data['quantity']);
            $position = ((int) DefectAssessmentQuantity::query()
                ->where('organization_id', $assessment->organization_id)
                ->where('defect_assessment_id', $assessment->id)
                ->max('position')) + 1;

            DefectAssessmentQuantity::query()->create([
                ...$measurement,
                'organization_id' => $assessment->organization_id,
                'inspection_id' => $assessment->inspection_id,
                'defect_assessment_id' => $assessment->id,
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'position' => $position,
            ]);

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
