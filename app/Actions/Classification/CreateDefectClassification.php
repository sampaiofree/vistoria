<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use App\Models\User;
use App\Services\Classification\DefectClassificationRangeValidator;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDefectClassification
{
    public function __construct(private readonly DefectClassificationRangeValidator $rangeValidator) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectCategory $category, array $data): DefectClassification
    {
        if ((int) $category->organization_id !== (int) $actor->organization_id) {
            throw ValidationException::withMessages(['category' => 'A categoria não pertence à organização atual.']);
        }

        return DB::transaction(function () use ($actor, $category, $data): DefectClassification {
            $category = DefectCategory::query()
                ->where('organization_id', $actor->organization_id)
                ->lockForUpdate()
                ->findOrFail($category->getKey());
            $lowerLimit = (int) $data['lower_limit'];
            $upperLimit = (int) $data['upper_limit'];
            $status = $data['status'] ?? RegistrationStatus::Active;

            $this->rangeValidator->ensureValidRange($lowerLimit, $upperLimit);

            if ($status === RegistrationStatus::Active || $status === RegistrationStatus::Active->value) {
                $this->rangeValidator->ensureNoActiveOverlap($category, $lowerLimit, $upperLimit);
            }

            return DefectClassification::query()->create([
                'organization_id' => $actor->organization_id,
                'defect_category_id' => $category->getKey(),
                'code' => TextNormalizer::technicalCode((string) $data['code']),
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'color' => TextNormalizer::hexColor((string) $data['color']),
                'status' => $status,
                'position' => (int) ($data['position'] ?? 1),
                'severity_rank' => isset($data['severity_rank']) ? (int) $data['severity_rank'] : null,
                'lower_limit' => $lowerLimit,
                'upper_limit' => $upperLimit,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }
}
