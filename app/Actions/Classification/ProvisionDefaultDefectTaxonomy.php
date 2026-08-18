<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionDefaultDefectTaxonomy
{
    /** @var array<string, string> */
    private const CV_COLORS = [
        'CV-1' => '#FF0000',
        'CV-2' => '#FFC000',
        'CV-3' => '#FFFF00',
        'CV-4' => '#92D050',
        'CV-5' => '#0070C0',
    ];

    public function handle(int $organizationId): DefectCategory
    {
        return DB::transaction(function () use ($organizationId): DefectCategory {
            $category = DefectCategory::query()->firstOrCreate(
                ['organization_id' => $organizationId, 'code' => 'CV'],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => 'CIVIL',
                    'description' => 'Avarias relacionadas aos elementos civis.',
                    'status' => RegistrationStatus::Active,
                    'position' => 1,
                ],
            );

            foreach (range(1, 5) as $position) {
                $code = 'CV-'.$position;
                DefectClassification::query()->firstOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'defect_category_id' => $category->getKey(),
                        'code' => $code,
                    ],
                    [
                        'public_id' => (string) Str::ulid(),
                        'name' => 'CV-'.$position,
                        'color' => self::CV_COLORS[$code],
                        'status' => RegistrationStatus::Active,
                        'position' => $position,
                        'severity_rank' => $position,
                    ],
                );
            }

            return $category->refresh();
        });
    }
}
