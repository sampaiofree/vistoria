<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class SaveDefectCategoryGut
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectCategory $category, array $data): DefectCategory
    {
        return DB::transaction(function () use ($actor, $category, $data): DefectCategory {
            $category = DefectCategory::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($category->getKey());

            DefectCategoryGutOption::query()
                ->forOrganization($this->tenant->id())
                ->where('defect_category_id', $category->getKey())
                ->delete();

            foreach ($data['gut_options'] ?? [] as $option) {
                DefectCategoryGutOption::query()->create([
                    'organization_id' => $category->organization_id,
                    'defect_category_id' => $category->getKey(),
                    'criterion' => $option['criterion'],
                    'score' => (int) $option['score'],
                    'color' => strtoupper((string) $option['color']),
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);
            }

            return $category->refresh();
        });
    }
}
