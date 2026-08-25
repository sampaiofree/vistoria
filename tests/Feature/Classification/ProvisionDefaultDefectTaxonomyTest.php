<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use App\Models\Organization;
use App\Services\Classification\GutClassificationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProvisionDefaultDefectTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_cv_tac_and_rec_with_their_ranges_recommendations_and_gut_options(): void
    {
        $organization = Organization::factory()->create();

        $civil = app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);
        $categories = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->with(['classifications', 'gutOptions'])
            ->get()
            ->keyBy('code');

        $this->assertSame('CV', $civil->code);
        $this->assertSame(['CV', 'REC', 'TAC'], $categories->keys()->sort()->values()->all());
        $this->assertSame([
            'CV-1' => [75, 125, 'Tratar em até 1 ano'],
            'CV-2' => [36, 74, 'Tratar em até 2 anos'],
            'CV-3' => [16, 35, 'Tratar em até 3 anos'],
            'CV-4' => [8, 15, 'Intervenção por oportunidade'],
            'CV-5' => [1, 7, 'Registro de condição'],
        ], $this->ranges($categories['CV']));
        $this->assertSame([
            'TA-1' => [45, 75, 'Tratar em até 1 ano'],
            'TA-2' => [25, 44, 'Tratar em até 3 anos'],
            'TA-3' => [15, 24, 'Tratar em até 5 anos'],
        ], $this->ranges($categories['TAC']));
        $this->assertSame([
            'IE-1' => [75, 125, 'Tratar em até 1 ano'],
            'IE-2' => [36, 74, 'Tratar em até 2 anos'],
            'IE-3' => [16, 35, 'Tratar em até 3 anos'],
            'IE-4' => [8, 15, 'Intervenção por oportunidade'],
            'IE-5' => [1, 7, 'Registro de condição'],
        ], $this->ranges($categories['REC']));
        $this->assertSame([
            'CV-1' => 'Grave',
            'CV-2' => 'Alta',
            'CV-3' => 'Média',
            'CV-4' => 'Baixa',
            'CV-5' => 'Muito baixa',
        ], $this->names($categories['CV']));
        $this->assertSame([
            'TA-1' => 'Grave',
            'TA-2' => 'Alta',
            'TA-3' => 'Média',
        ], $this->names($categories['TAC']));
        $this->assertSame([
            'IE-1' => 'Grave',
            'IE-2' => 'Alta',
            'IE-3' => 'Média',
            'IE-4' => 'Baixa',
            'IE-5' => 'Muito baixa',
        ], $this->names($categories['REC']));

        foreach ($categories as $category) {
            $this->assertSame(15, $category->gutOptions->count());
            $this->assertSame([
                1 => '#00AEEF',
                2 => '#92D050',
                3 => '#FFFF00',
                4 => '#FFC000',
                5 => '#FF0000',
            ], $category->gutOptions
                ->filter(fn ($option): bool => $option->criterion->value === 'gravity')
                ->pluck('color', 'score')
                ->all());
        }
    }

    public function test_it_fills_only_missing_defaults_without_overwriting_customized_values(): void
    {
        $organization = Organization::factory()->create();
        $category = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'CV')
            ->firstOrFail();
        $category->update(['name' => 'Civil personalizada']);
        $classification = DefectClassification::query()
            ->where('organization_id', $organization->id)
            ->where('defect_category_id', $category->id)
            ->where('code', 'CV-1')
            ->firstOrFail();
        $classification->update([
            'name' => 'Nome personalizado',
            'description' => 'Prazo personalizado',
            'lower_limit' => 80,
            'upper_limit' => 120,
            'color' => '#123456',
        ]);

        app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);
        app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);

        $this->assertSame(3, DefectCategory::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(5, $category->fresh()->classifications()->count());
        $this->assertSame(3, DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'TAC')
            ->firstOrFail()
            ->classifications()
            ->count());
        $this->assertSame('Prazo personalizado', $classification->fresh()->description);
        $this->assertSame('Nome personalizado', $classification->fresh()->name);
        $this->assertSame(80, $classification->fresh()->lower_limit);
        $this->assertSame(120, $classification->fresh()->upper_limit);
        $this->assertSame('#123456', $classification->fresh()->color);
    }

    public function test_it_replaces_legacy_technical_names_without_replacing_customized_names(): void
    {
        $organization = Organization::factory()->create();
        $category = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'CV')
            ->firstOrFail();
        $classification = $category->classifications()
            ->where('code', 'CV-1')
            ->firstOrFail();

        $classification->update(['name' => 'CV-1']);
        app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);

        $this->assertSame('Grave', $classification->fresh()->name);

        $classification->update(['name' => 'Crítica personalizada']);
        app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);

        $this->assertSame('Crítica personalizada', $classification->fresh()->name);
    }

    public function test_resolver_uses_default_ranges_and_leaves_tac_scores_below_fifteen_unclassified(): void
    {
        $organization = Organization::factory()->create();
        app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);
        $categories = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->with(['classifications', 'gutOptions'])
            ->get()
            ->keyBy('code');
        $resolver = app(GutClassificationResolver::class);

        $this->assertSame('CV-1', $resolver->resolve($categories['CV'], ['gravity' => 5, 'urgency' => 5, 'trend' => 5])['classification']?->code);
        $this->assertSame('IE-3', $resolver->resolve($categories['REC'], ['gravity' => 2, 'urgency' => 2, 'trend' => 4])['classification']?->code);
        $this->assertNull($resolver->resolve($categories['TAC'], ['gravity' => 1, 'urgency' => 1, 'trend' => 5])['classification']);
    }

    /** @return array<string, array{int, int, string}> */
    private function ranges(DefectCategory $category): array
    {
        return $category->classifications
            ->mapWithKeys(fn (DefectClassification $classification): array => [
                $classification->code => [
                    $classification->lower_limit,
                    $classification->upper_limit,
                    $classification->description,
                ],
            ])
            ->all();
    }

    /** @return array<string, string> */
    private function names(DefectCategory $category): array
    {
        return $category->classifications->pluck('name', 'code')->all();
    }
}
