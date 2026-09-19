<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\DefectCategory;
use App\Enums\GutCriterion;
use Illuminate\Support\Collection;

final class NativeDefectCatalog
{
    public const VERSION = 1;

    /** @var array<string, list<array{code:string,name:string,color:string,position:int,lower_limit:int,upper_limit:int,description:string}>> */
    private const CLASSIFICATIONS = [
        'CV' => [
            ['code' => 'CV-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'CV-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
            ['code' => 'CV-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'CV-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
            ['code' => 'CV-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
        ],
        'TAC' => [
            ['code' => 'TA-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 45, 'upper_limit' => 75, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'TA-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 25, 'upper_limit' => 44, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'TA-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 15, 'upper_limit' => 24, 'description' => 'Tratar em até 5 anos'],
        ],
        'REC' => [
            ['code' => 'IE-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'IE-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
            ['code' => 'IE-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'IE-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
            ['code' => 'IE-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
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

    /** @return Collection<int, DefectClassificationDefinition> */
    public static function classifications(DefectCategory $category): Collection
    {
        return collect(self::CLASSIFICATIONS[$category->value])
            ->map(fn (array $definition): DefectClassificationDefinition => new DefectClassificationDefinition(
                code: $definition['code'],
                name: $definition['name'],
                description: $definition['description'],
                color: $definition['color'],
                position: $definition['position'],
                severity_rank: $definition['position'],
                lower_limit: $definition['lower_limit'],
                upper_limit: $definition['upper_limit'],
            ));
    }

    /** @return array<string, list<array{score:int,color:string}>> */
    public static function gutOptions(): array
    {
        $options = [];
        foreach (GutCriterion::cases() as $criterion) {
            $options[$criterion->value] = [];
            foreach (self::GUT_COLORS as $score => $color) {
                $options[$criterion->value][] = ['score' => $score, 'color' => $color];
            }
        }

        return $options;
    }
}
