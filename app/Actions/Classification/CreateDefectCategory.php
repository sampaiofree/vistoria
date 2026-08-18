<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\GutCriterion;
use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class CreateDefectCategory
{
    /** @var array<string, array{prefix: string}> */
    private const STANDARD_CLASSIFICATIONS = [
        'TAC' => ['prefix' => 'TA'],
        'REC' => ['prefix' => 'IE'],
    ];

    /** @var array<int, string> */
    private const STANDARD_COLORS = [
        1 => '#FF0000',
        2 => '#FFC000',
        3 => '#FFFF00',
        4 => '#92D050',
        5 => '#0070C0',
    ];

    /** @var array<int, string> */
    private const GUT_COLORS = [
        1 => '#00AEEF',
        2 => '#92D050',
        3 => '#FFFF00',
        4 => '#FFC000',
        5 => '#FF0000',
    ];

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): DefectCategory
    {
        return DB::transaction(function () use ($actor, $data): DefectCategory {
            $category = DefectCategory::query()->create([
                'organization_id' => $actor->organization_id,
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode((string) $data['code']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'status' => $data['status'] ?? RegistrationStatus::Active,
                'requires_location_map' => (bool) ($data['requires_location_map'] ?? false),
                'position' => (int) ($data['position'] ?? 1),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $standard = self::STANDARD_CLASSIFICATIONS[$category->code] ?? null;
            if ($standard !== null) {
                foreach (self::STANDARD_COLORS as $position => $color) {
                    $code = $standard['prefix'].'-'.$position;
                    $category->classifications()->create([
                        'organization_id' => $actor->organization_id,
                        'code' => $code,
                        'name' => $code,
                        'color' => $color,
                        'status' => RegistrationStatus::Active,
                        'position' => $position,
                        'severity_rank' => $position,
                        'created_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(),
                    ]);
                }
            }

            foreach ([GutCriterion::Gravity, GutCriterion::Urgency, GutCriterion::Trend] as $criterion) {
                foreach (self::GUT_COLORS as $score => $color) {
                    $category->gutOptions()->create([
                        'organization_id' => $actor->organization_id,
                        'criterion' => $criterion,
                        'score' => $score,
                        'color' => $color,
                        'created_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(),
                    ]);
                }
            }

            return $category;
        });
    }
}
