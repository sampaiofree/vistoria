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

final class UpdateDefectAssessmentQuantity
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NativeDefectQuantityCalculator $calculator,
        private readonly RefreshDefectAssessmentQuantityState $refreshState,
    ) {}

    /** @param array{description?: string|null, quantity: array<string, mixed>} $data */
    public function handle(User $actor, DefectAssessmentQuantity $quantity, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $quantity, $data): DefectAssessment {
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
            $measurement = $this->calculator->calculate($assessment->defect->category, $data['quantity']);
            $quantity->update([
                ...$measurement,
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'inspection_id' => $assessment->inspection_id,
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
