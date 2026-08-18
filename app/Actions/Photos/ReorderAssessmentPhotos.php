<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderAssessmentPhotos
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array<int, string> $publicIds */
    public function handle(User $actor, DefectAssessment $assessment, array $publicIds): void
    {
        $assessment = DefectAssessment::query()
            ->forOrganization($this->tenant->id())
            ->findOrFail($assessment->getKey());

        if ($assessment->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['photos' => 'Fotografias não podem ser reordenadas após o encerramento da inspeção.']);
        }

        DB::transaction(function () use ($assessment, $publicIds): void {
            $photos = AssessmentPhoto::query()
                ->where('defect_assessment_id', $assessment->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            if ($photos->count() !== count($publicIds) || $photos->keys()->diff($publicIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['photos' => 'A lista de fotografias está desatualizada. Recarregue a avaliação e tente novamente.']);
            }

            foreach (array_values($publicIds) as $position => $publicId) {
                $photos->get($publicId)->update(['position' => $position + 1]);
            }
        });
    }
}
