<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\GutCriterion;
use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\DefectClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionDefaultDefectTaxonomy
{
    /** @var array<string, array{name:string,description:string,position:int,classifications:list<array{code:string,name:string,color:string,position:int,lower_limit:int,upper_limit:int,description:string}>> */
    private const CATEGORIES = [
        'CV' => [
            'name' => 'CIVIL',
            'description' => 'Avarias relacionadas aos elementos civis.',
            'position' => 1,
            'classifications' => [
                ['code' => 'CV-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'CV-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
                ['code' => 'CV-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'CV-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
                ['code' => 'CV-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
            ],
        ],
        'TAC' => [
            'name' => 'TAC',
            'description' => 'Avarias relacionadas ao tratamento anticorrosivo.',
            'position' => 2,
            'classifications' => [
                ['code' => 'TA-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 45, 'upper_limit' => 75, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'TA-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 25, 'upper_limit' => 44, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'TA-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 15, 'upper_limit' => 24, 'description' => 'Tratar em até 5 anos'],
            ],
        ],
        'REC' => [
            'name' => 'REC',
            'description' => 'Avarias relacionadas à recuperação estrutural.',
            'position' => 3,
            'classifications' => [
                ['code' => 'IE-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'IE-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
                ['code' => 'IE-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'IE-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
                ['code' => 'IE-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
            ],
        ],
    ];

    /** @var array<int, string> */
    private const GUT_COLORS = [
        1 => '#00AEEF',
        2 => '#92D050',
        3 => '#FFFF00',
        4 => '#FFC000',
        5 => '#FF0000',
    ];

    public function handle(int $organizationId): DefectCategory
    {
        return DB::transaction(function () use ($organizationId): DefectCategory {
            $categories = [];

            foreach (self::CATEGORIES as $code => $definition) {
                $category = DefectCategory::query()->firstOrCreate(
                    ['organization_id' => $organizationId, 'code' => $code],
                    [
                        'public_id' => (string) Str::ulid(),
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'status' => RegistrationStatus::Active,
                        'position' => $definition['position'],
                    ],
                );

                foreach ($definition['classifications'] as $classificationDefinition) {
                    $classification = DefectClassification::query()->firstOrCreate(
                        [
                            'organization_id' => $organizationId,
                            'defect_category_id' => $category->getKey(),
                            'code' => $classificationDefinition['code'],
                        ],
                        [
                            'public_id' => (string) Str::ulid(),
                            'name' => $classificationDefinition['name'],
                            'description' => $classificationDefinition['description'],
                            'color' => $classificationDefinition['color'],
                            'status' => RegistrationStatus::Active,
                            'position' => $classificationDefinition['position'],
                            'severity_rank' => $classificationDefinition['position'],
                            'lower_limit' => $classificationDefinition['lower_limit'],
                            'upper_limit' => $classificationDefinition['upper_limit'],
                        ],
                    );

                    $missingValues = array_filter([
                        'name' => $classification->name === null || $classification->name === $classification->code
                            ? $classificationDefinition['name']
                            : null,
                        'description' => $classification->description === null ? $classificationDefinition['description'] : null,
                        'color' => $classification->color === null ? $classificationDefinition['color'] : null,
                        'severity_rank' => $classification->severity_rank === null ? $classificationDefinition['position'] : null,
                        'lower_limit' => $classification->lower_limit === null ? $classificationDefinition['lower_limit'] : null,
                        'upper_limit' => $classification->upper_limit === null ? $classificationDefinition['upper_limit'] : null,
                    ], static fn (mixed $value): bool => $value !== null);

                    if ($missingValues !== []) {
                        $classification->update($missingValues);
                    }
                }

                foreach (GutCriterion::cases() as $criterion) {
                    foreach (self::GUT_COLORS as $score => $color) {
                        DefectCategoryGutOption::query()->firstOrCreate(
                            [
                                'organization_id' => $organizationId,
                                'defect_category_id' => $category->getKey(),
                                'criterion' => $criterion,
                                'score' => $score,
                            ],
                            ['color' => $color],
                        );
                    }
                }

                $categories[$code] = $category;
            }

            return $categories['CV']->refresh();
        });
    }
}
