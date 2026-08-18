<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Enums\DefectAssessmentCondition;
use App\Enums\GutCriterion;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectCategoryGutRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_independent_category_options_with_zero_and_gaps(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->put(route('defect-categories.gut.update', $category), [
                'gut_options' => [
                    ['criterion' => 'gravity', 'score' => 0, 'color' => '#000000'],
                    ['criterion' => 'gravity', 'score' => 4, 'color' => '#FFFFFF'],
                    ['criterion' => 'urgency', 'score' => 2, 'color' => '#123456'],
                ],
            ])
            ->assertRedirect(route('defect-categories.show', $category));

        $this->assertDatabaseHas('defect_category_gut_options', [
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Gravity->value,
            'score' => 0,
            'color' => '#000000',
        ]);
        $this->assertSame(2, $category->fresh()->gutOptions()->where('criterion', 'gravity')->count());
        $this->assertSame(1, $category->fresh()->gutOptions()->where('criterion', 'urgency')->count());
        $this->assertSame(0, $category->fresh()->gutOptions()->where('criterion', 'trend')->count());

        $this->actingAs($admin)
            ->get(route('defect-categories.show', $category))
            ->assertInertia(fn (Assert $page) => $page
                ->where('category.gut.configured', true)
                ->where('category.gut.gravity.0.score', 0)
                ->where('category.gut.urgency.0.color', '#123456'));
    }

    public function test_category_gut_rejects_duplicate_scores_and_invalid_colors(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->put(route('defect-categories.gut.update', $category), [
                'gut_options' => [
                    ['criterion' => 'gravity', 'score' => 2, 'color' => '#000000'],
                    ['criterion' => 'gravity', 'score' => 2, 'color' => '#123'],
                ],
            ])
            ->assertSessionHasErrors(['gut_options.1.score', 'gut_options.1.color']);
    }

    public function test_member_cannot_manage_category_gut(): void
    {
        [$organization, , $member] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->get(route('defect-categories.gut.edit', $category))
            ->assertForbidden();
    }

    public function test_assessment_gut_is_validated_against_its_category_and_snapshotted_separately(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Gravity,
            'score' => 0,
            'color' => '#000000',
        ]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Urgency,
            'score' => 4,
            'color' => '#123456',
        ]);

        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'organization_id' => $organization->id,
            'status' => InspectionStatus::InProgress,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'condition' => DefectAssessmentCondition::Worsened,
        ]);

        $updated = app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::Worsened->value,
            'gravity' => 0,
            'urgency' => 4,
            'trend' => null,
        ]);

        $this->assertSame(0, $updated->gravity);
        $this->assertSame(4, $updated->urgency);
        $this->assertNull($updated->trend);
        $this->assertNull($updated->gut_score);
        $this->assertSame('#000000', $updated->gut_snapshot['criteria']['gravity']['color']);
        $this->assertSame('defect_category', $updated->gut_snapshot['source']);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('gut_options.gravity.0.score', 0)
                ->where('gut_options.urgency.0.color', '#123456')
                ->where('gut_snapshot.criteria.gravity.score', 0)
                ->where('capabilities.gut_url', route('defect-assessments.gut.update', $assessment)));
    }

    public function test_assessment_gut_rejects_a_score_removed_from_the_category(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Gravity,
            'score' => 1,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();

        $this->expectException(ValidationException::class);
        app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::New->value,
            'gravity' => 5,
        ]);
    }

    public function test_only_configured_criteria_are_required_to_publish(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Gravity,
            'score' => 0,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'condition' => DefectAssessmentCondition::Worsened,
            'comment' => 'Registro suficiente.',
        ]);

        $this->expectException(ValidationException::class);
        app(CompleteDefectAssessment::class)->handle($admin, $assessment);
    }

    /** @return array{0:Organization,1:User,2:User} */
    private function tenant(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member]);

        return [$organization, $admin, $member];
    }
}
