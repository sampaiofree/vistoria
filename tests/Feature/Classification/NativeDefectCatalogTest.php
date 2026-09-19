<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectCategory;
use App\Services\Classification\GutClassificationResolver;
use App\Services\Classification\NativeDefectCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NativeDefectCatalogTest extends TestCase
{
    public function test_native_definitions_preserve_codes_names_colors_recommendations_and_inclusive_ranges(): void
    {
        $expected = [
            'CV' => [['CV-1', 'Grave', '#FF0000', 75, 125, 'Tratar em até 1 ano'], ['CV-2', 'Alta', '#FFC000', 36, 74, 'Tratar em até 2 anos'], ['CV-3', 'Média', '#FFFF00', 16, 35, 'Tratar em até 3 anos'], ['CV-4', 'Baixa', '#92D050', 8, 15, 'Intervenção por oportunidade'], ['CV-5', 'Muito baixa', '#0070C0', 1, 7, 'Registro de condição']],
            'TAC' => [['TA-1', 'Grave', '#FF0000', 45, 75, 'Tratar em até 1 ano'], ['TA-2', 'Alta', '#FFC000', 25, 44, 'Tratar em até 3 anos'], ['TA-3', 'Média', '#FFFF00', 15, 24, 'Tratar em até 5 anos']],
            'REC' => [['IE-1', 'Grave', '#FF0000', 75, 125, 'Tratar em até 1 ano'], ['IE-2', 'Alta', '#FFC000', 36, 74, 'Tratar em até 2 anos'], ['IE-3', 'Média', '#FFFF00', 16, 35, 'Tratar em até 3 anos'], ['IE-4', 'Baixa', '#92D050', 8, 15, 'Intervenção por oportunidade'], ['IE-5', 'Muito baixa', '#0070C0', 1, 7, 'Registro de condição']],
        ];

        $this->assertSame(['CV', 'TAC', 'REC'], array_column(DefectCategory::options(), 'code'));
        foreach (DefectCategory::cases() as $category) {
            $definitions = NativeDefectCatalog::classifications($category);
            $this->assertSame($expected[$category->value], $definitions->map(fn ($definition): array => [
                $definition->code, $definition->name, $definition->color, $definition->lower_limit, $definition->upper_limit, $definition->description,
            ])->all());
            foreach ($definitions as $index => $definition) {
                $this->assertSame($index + 1, $definition->severity_rank);
                $this->assertTrue($definition->contains($definition->lower_limit));
                $this->assertTrue($definition->contains($definition->upper_limit));
                $this->assertFalse($definition->contains($definition->lower_limit - 1));
                $this->assertFalse($definition->contains($definition->upper_limit + 1));
            }
        }
    }

    public function test_all_note_combinations_use_the_expected_native_classification(): void
    {
        $resolver = new GutClassificationResolver;
        foreach (DefectCategory::cases() as $category) {
            foreach (range(1, 5) as $gravity) {
                foreach (range(1, 5) as $urgency) {
                    foreach (range(1, 5) as $trend) {
                        $score = $gravity * $urgency * $trend;
                        $rank = $category === DefectCategory::AnticorrosiveTreatment
                            ? match (true) {
                                $score < 15 || $score > 75 => null, $score >= 45 => 1, $score >= 25 => 2, default => 3
                            }
                        : match (true) {
                            $score >= 75 => 1, $score >= 36 => 2, $score >= 16 => 3, $score >= 8 => 4, default => 5
                        };
                        $prefix = match ($category) {
                            DefectCategory::Civil => 'CV', DefectCategory::AnticorrosiveTreatment => 'TA', DefectCategory::StructuralRecovery => 'IE'
                        };
                        $result = $resolver->resolve($category, compact('gravity', 'urgency', 'trend'));
                        $this->assertSame($score, $result['gut_score']);
                        $this->assertSame($rank === null ? null : "{$prefix}-{$rank}", $result['classification']?->code);
                    }
                }
            }
        }
        foreach (NativeDefectCatalog::gutOptions() as $options) {
            $this->assertSame([1, 2, 3, 4, 5], array_column($options, 'score'));
            $this->assertSame(['#00AEEF', '#92D050', '#FFFF00', '#FFC000', '#FF0000'], array_column($options, 'color'));
        }
    }

    public function test_resolver_rejects_missing_and_out_of_range_notes(): void
    {
        foreach ([['gravity' => 0, 'urgency' => 6, 'trend' => null], ['gravity' => true, 'urgency' => 1.5, 'trend' => 'invalid']] as $scores) {
            try {
                (new GutClassificationResolver)->resolve(DefectCategory::Civil, $scores);
                $this->fail('Notas inválidas deveriam ser rejeitadas.');
            } catch (ValidationException $exception) {
                $this->assertSame(['gravity', 'urgency', 'trend'], array_keys($exception->errors()));
            }
        }
    }

    public function test_catalog_management_routes_are_removed(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(str_starts_with($route->uri(), 'defect-categories'));
            $this->assertFalse(str_starts_with($route->uri(), 'defect-classifications'));
        }
        $this->get('/defect-categories')->assertNotFound();
        $this->post('/defect-categories', [])->assertNotFound();
        $this->patch('/defect-classifications/1', [])->assertNotFound();
    }
}
