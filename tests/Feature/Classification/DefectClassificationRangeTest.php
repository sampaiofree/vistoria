<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Enums\DefectAssessmentCondition;
use App\Enums\GutCriterion;
use App\Enums\InspectionStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\DefectClassification;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DefectClassificationRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_are_required_non_negative_ordered_and_active_ranges_cannot_overlap(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        $payload = [
            'code' => 'IE-1',
            'name' => 'Prioridade 1',
            'color' => '#0F766E',
            'position' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), $payload)
            ->assertSessionHasErrors(['lower_limit', 'upper_limit']);

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                ...$payload,
                'lower_limit' => -1,
                'upper_limit' => 5,
            ])
            ->assertSessionHasErrors('lower_limit');

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                ...$payload,
                'lower_limit' => 6,
                'upper_limit' => 5,
            ])
            ->assertSessionHasErrors('upper_limit');

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                ...$payload,
                'lower_limit' => 5,
                'upper_limit' => 25,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                ...$payload,
                'code' => 'IE-2',
                'lower_limit' => 25,
                'upper_limit' => 50,
            ])
            ->assertSessionHasErrors('lower_limit');

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                ...$payload,
                'code' => 'IE-3',
                'lower_limit' => 27,
                'upper_limit' => 50,
            ])
            ->assertRedirect();
    }

    public function test_gut_score_selects_inclusive_range_and_ignores_manual_classification_input(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = $this->categoryWithGutOptions($organization, 5);
        $automatic = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'IE-2',
            'lower_limit' => 125,
            'upper_limit' => 125,
        ]);
        $manualAttempt = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'IE-3',
            'status' => RegistrationStatus::Inactive,
            'lower_limit' => 0,
            'upper_limit' => 124,
        ]);
        $assessment = $this->assessmentForCategory($organization, $category);

        $updated = app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::Worsened->value,
            'gravity' => 5,
            'urgency' => 5,
            'trend' => 5,
            'defect_classification_id' => $manualAttempt->id,
        ]);

        $this->assertSame(125, $updated->gut_score);
        $this->assertSame($automatic->id, $updated->defect_classification_id);
        $this->assertSame('IE-2', $updated->classification_code);
        $this->assertSame(125, $updated->classification_snapshot['gut_score']);
        $this->assertSame(125, $updated->classification_snapshot['lower_limit']);
        $this->assertSame(125, $updated->classification_snapshot['upper_limit']);
    }

    public function test_gut_score_without_a_range_is_saved_unclassified_and_legacy_overlaps_are_rejected(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = $this->categoryWithGutOptions($organization, 2);
        $assessment = $this->assessmentForCategory($organization, $category);

        DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'lower_limit' => 0,
            'upper_limit' => 7,
        ]);

        $unclassified = app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::Worsened->value,
            'gravity' => 2,
            'urgency' => 2,
            'trend' => 2,
        ]);

        $this->assertSame(8, $unclassified->gut_score);
        $this->assertNull($unclassified->defect_classification_id);
        $this->assertNull($unclassified->classification_code);
        $this->assertNull($unclassified->classification_snapshot);

        DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'lower_limit' => 8,
            'upper_limit' => 8,
        ]);
        DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'lower_limit' => 8,
            'upper_limit' => 9,
        ]);

        try {
            app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
                'condition' => DefectAssessmentCondition::Worsened->value,
                'gravity' => 2,
                'urgency' => 2,
                'trend' => 2,
            ]);
            $this->fail('Faixas históricas sobrepostas deveriam impedir a classificação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gut', $exception->errors());
        }

        $assessment->update([
            'condition' => DefectAssessmentCondition::Repaired,
            'gravity' => 2,
            'urgency' => 2,
            'trend' => 2,
            'gut_score' => 8,
            'defect_classification_id' => 1,
            'classification_code' => 'LEGADA',
        ]);
        $cleared = app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::Repaired->value,
        ]);

        $this->assertNull($cleared->gut_score);
        $this->assertNull($cleared->defect_classification_id);
        $this->assertNull($cleared->classification_code);
    }

    /** @return array{Organization, User} */
    private function tenant(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        return [$organization, $admin];
    }

    private function categoryWithGutOptions(Organization $organization, int $score): DefectCategory
    {
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);

        foreach ([GutCriterion::Gravity, GutCriterion::Urgency, GutCriterion::Trend] as $criterion) {
            DefectCategoryGutOption::factory()->create([
                'organization_id' => $organization->id,
                'defect_category_id' => $category->id,
                'criterion' => $criterion,
                'score' => $score,
            ]);
        }

        return $category;
    }

    private function assessmentForCategory(Organization $organization, DefectCategory $category): DefectAssessment
    {
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'organization_id' => $organization->id,
            'status' => InspectionStatus::InProgress,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'organization_id' => $organization->id,
            'condition' => DefectAssessmentCondition::Worsened,
        ]);
    }
}
