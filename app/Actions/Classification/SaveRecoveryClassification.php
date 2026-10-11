<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectCategory;
use App\Enums\RecoveryClassification;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Defects\InspectionAssessmentResolver;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveRecoveryClassification
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, DefectAssessment $assessment, string $code): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $code): DefectAssessment {
            $assessment = DefectAssessment::query()->forOrganization($this->tenant->id())
                ->with(['inspection', 'defect'])->lockForUpdate()->findOrFail($assessment->id);
            app(InspectionAssessmentResolver::class)->ensureMutable($assessment);

            if (! $actor->can('update', $assessment) || ! $assessment->isDraft()
                || ! $assessment->inspection->is_m3
                || ! in_array($assessment->defect->category, [DefectCategory::Civil, DefectCategory::StructuralRecovery], true)) {
                throw ValidationException::withMessages(['recovery_classification_code' => 'A classificação de recuperação não pode ser alterada nesta avaliação.']);
            }

            $classification = RecoveryClassification::from($code);
            $assessment->update([
                'recovery_classification_code' => $classification->value,
                'recovery_classification_snapshot' => ['version' => 1, ...$classification->toArray()],
                'recovery_classified_at' => now(),
                'recovery_classified_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $assessment->refresh();
        });
    }
}
