<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Models\DefectClassification;
use App\Models\User;
use App\Services\Classification\DefectClassificationRangeValidator;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDefectClassification
{
    public function __construct(private readonly DefectClassificationRangeValidator $rangeValidator) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectClassification $classification, array $data): DefectClassification
    {
        return DB::transaction(function () use ($actor, $classification, $data): DefectClassification {
            $classification = DefectClassification::query()
                ->with('category')
                ->lockForUpdate()
                ->findOrFail($classification->getKey());
            $category = $classification->category;

            if ($category === null) {
                throw ValidationException::withMessages(['classification' => 'A categoria da classificação não foi encontrada.']);
            }

            $category->newQuery()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            $code = TextNormalizer::technicalCode((string) $data['code']);
            $lowerLimit = (int) $data['lower_limit'];
            $upperLimit = (int) $data['upper_limit'];

            if ($code !== $classification->code && $classification->assessments()->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'O código não pode ser alterado depois que a classificação foi utilizada.',
                ]);
            }

            $this->rangeValidator->ensureValidRange($lowerLimit, $upperLimit);

            if ($classification->isActive()) {
                $this->rangeValidator->ensureNoActiveOverlap(
                    $category,
                    $lowerLimit,
                    $upperLimit,
                    $classification->getKey(),
                );
            }

            $classification->update([
                'code' => $code,
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'color' => TextNormalizer::hexColor((string) $data['color']),
                'position' => (int) ($data['position'] ?? $classification->position),
                'severity_rank' => isset($data['severity_rank']) ? (int) $data['severity_rank'] : null,
                'lower_limit' => $lowerLimit,
                'upper_limit' => $upperLimit,
                'updated_by' => $actor->getKey(),
            ]);

            return $classification->refresh();
        });
    }
}
