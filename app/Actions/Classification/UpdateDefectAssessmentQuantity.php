<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\MeasurementUnit;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\User;
use App\Services\Defects\DefectStatusSynchronizer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDefectAssessmentQuantity
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
    ) {}

    /** @param array{measurement_value:mixed,measurement_unit:mixed}|null $data */
    public function handle(User $actor, DefectAssessment $assessment, ?array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['inspection', 'defect'])
                ->lockForUpdate()
                ->findOrFail($assessment->id);

            if (! $actor->isActive()
                || $actor->isSuperAdmin()
                || ! $actor->belongsToOrganization($this->tenant->id())
                || ! in_array($assessment->inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)
                || ! $assessment->inspection->hasAnyResponsibilityForUser(
                    $actor,
                    InspectionResponsibility::Preparer,
                )) {
                throw ValidationException::withMessages([
                    'quantity' => 'A avaliação não está disponível para editar o quantitativo.',
                ]);
            }

            $wasComplete = $assessment->status === DefectAssessmentStatus::Complete;
            $keepPublished = $wasComplete && $assessment->locationMarkers()->exists();

            if ($data === null) {
                DefectAssessmentQuantity::query()
                    ->where('organization_id', $assessment->organization_id)
                    ->where('defect_assessment_id', $assessment->id)
                    ->delete();
            } else {
                $quantity = DefectAssessmentQuantity::query()
                    ->where('organization_id', $assessment->organization_id)
                    ->where('defect_assessment_id', $assessment->id)
                    ->first();

                $values = [
                    'inspection_id' => $assessment->inspection_id,
                    'measurement_value' => (float) $data['measurement_value'],
                    'measurement_unit' => MeasurementUnit::from((string) $data['measurement_unit']),
                    'quantity' => 1,
                    'position' => 1,
                ];

                if ($quantity === null) {
                    DefectAssessmentQuantity::query()->create([
                        ...$values,
                        'organization_id' => $assessment->organization_id,
                        'defect_assessment_id' => $assessment->id,
                    ]);
                } else {
                    $quantity->update($values);
                }
            }

            if ($wasComplete && ! $keepPublished) {
                $assessment->fill([
                    'status' => DefectAssessmentStatus::Draft,
                    'assessed_at' => null,
                    'defect_snapshot' => null,
                ]);
            } elseif ($keepPublished) {
                $assessment->assessed_at = now();
            }

            $assessment->updated_by = $actor->id;
            $assessment->save();

            if ($wasComplete && ! $keepPublished) {
                $this->statusSynchronizer->handle($assessment->defect, $actor);
            }

            return $assessment->refresh()->load('quantity');
        });
    }
}
