<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Enums\DefectAssessmentCondition;
use App\Enums\GutCriterion;
use App\Enums\InspectionResponsibility;
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
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectTaxonomyRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_category_and_classification(): void
    {
        [$organization, $admin] = $this->tenant();

        $this->actingAs($admin)
            ->post(route('defect-categories.store'), [
                'name' => 'Tratamento anticorrosivo',
                'code' => ' ta ',
                'description' => 'Categoria TAC',
            ])
            ->assertRedirect();

        $category = DefectCategory::query()->where('organization_id', $organization->id)->where('code', 'TA')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), [
                'code' => 'ta-1',
                'name' => 'Leve',
                'color' => ' #ab 12cd ',
                'position' => 1,
                'severity_rank' => 1,
                'lower_limit' => 0,
                'upper_limit' => 25,
            ])
            ->assertRedirect(route('defect-categories.show', $category));

        $this->assertDatabaseHas('defect_classifications', [
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'code' => 'TA-1',
            'color' => '#AB12CD',
        ]);
    }

    public function test_classification_color_is_required_and_must_use_full_hexadecimal_format(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        $payload = [
            'code' => 'TA-1',
            'name' => 'Leve',
            'position' => 1,
            'lower_limit' => 0,
            'upper_limit' => 25,
        ];

        $this->actingAs($admin)
            ->post(route('defect-categories.classifications.store', $category), $payload)
            ->assertSessionHasErrors('color');

        foreach (['#ABC', '#GGGGGG', '#1234567'] as $invalidColor) {
            $this->actingAs($admin)
                ->post(route('defect-categories.classifications.store', $category), [
                    ...$payload,
                    'color' => $invalidColor,
                ])
                ->assertSessionHasErrors('color');
        }

        $this->assertDatabaseMissing('defect_classifications', [
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'code' => 'TA-1',
        ]);
    }

    public function test_legacy_classification_stays_without_color_until_an_explicit_edit(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        $classification = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'TA-1',
            'name' => 'Leve',
            'color' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('defect-categories.show', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('category.classifications.0.color', null));

        $this->actingAs($admin)
            ->get(route('defect-classifications.edit', $classification))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('classification.color', null));

        $payload = [
            'code' => $classification->code,
            'name' => 'Leve atualizada',
            'position' => $classification->position,
            'severity_rank' => $classification->severity_rank,
            'lower_limit' => 0,
            'upper_limit' => 25,
        ];

        $this->actingAs($admin)
            ->patch(route('defect-classifications.update', $classification), $payload)
            ->assertSessionHasErrors('color');

        $this->assertNull($classification->fresh()->color);

        $this->actingAs($admin)
            ->patch(route('defect-classifications.update', $classification), [
                ...$payload,
                'color' => '#0f766e',
            ])
            ->assertRedirect(route('defect-categories.show', $category));

        $this->assertDatabaseHas('defect_classifications', [
            'id' => $classification->id,
            'name' => 'Leve atualizada',
            'color' => '#0F766E',
        ]);

        $this->actingAs($admin)
            ->patch(route('defect-classifications.update', $classification), $payload)
            ->assertSessionHasErrors('color');

        $this->assertSame('#0F766E', $classification->fresh()->color);
    }

    public function test_member_cannot_manage_taxonomy(): void
    {
        [, , $member] = $this->tenant();

        $this->actingAs($member)
            ->post(route('defect-categories.store'), [
                'name' => 'Outra',
                'code' => 'OUT',
            ])
            ->assertForbidden();
    }

    public function test_member_and_other_organization_cannot_manage_classification_color(): void
    {
        [$organization, , $member] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        $classification = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'color' => '#0F766E',
        ]);
        $payload = [
            'code' => $classification->code,
            'name' => $classification->name,
            'color' => '#AB12CD',
            'position' => $classification->position,
            'lower_limit' => 0,
            'upper_limit' => 25,
        ];

        $this->actingAs($member)
            ->post(route('defect-categories.classifications.store', $category), $payload)
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('defect-classifications.update', $classification), $payload)
            ->assertForbidden();

        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($otherAdmin)
            ->patch(route('defect-classifications.update', $classification), $payload)
            ->assertForbidden();

        $this->assertSame('#0F766E', $classification->fresh()->color);
    }

    public function test_category_code_is_unique_per_organization_but_can_repeat_in_another(): void
    {
        [$organization, $admin] = $this->tenant();
        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA']);

        $this->actingAs($admin)
            ->post(route('defect-categories.store'), ['name' => 'Duplicada', 'code' => 'TA'])
            ->assertSessionHasErrors('code');

        $this->actingAs($otherAdmin)
            ->post(route('defect-categories.store'), ['name' => 'Outra empresa', 'code' => 'TA'])
            ->assertRedirect();
    }

    public function test_location_requirement_needs_explicit_confirmation(): void
    {
        [$organization, $admin] = $this->tenant();

        $this->actingAs($admin)
            ->post(route('defect-categories.store'), [
                'name' => 'Revestimento',
                'code' => 'MAP',
                'requires_location_map' => true,
            ])
            ->assertSessionHasErrors('confirm_location_map_requirement');

        $this->actingAs($admin)
            ->post(route('defect-categories.store'), [
                'name' => 'Revestimento',
                'code' => 'MAP',
                'requires_location_map' => true,
                'confirm_location_map_requirement' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('defect_categories', [
            'organization_id' => $organization->id,
            'code' => 'MAP',
            'requires_location_map' => true,
        ]);
    }

    public function test_category_edit_shows_activation_impact_before_enabling_requirement(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TA',
            'requires_location_map' => false,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        DefectAssessment::factory()->forDefect($defect, $inspection)->create();

        $this->actingAs($admin)
            ->get(route('defect-categories.edit', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('category.requires_location_map', false)
                ->where('activation_impact.open_inspections', 1)
                ->where('activation_impact.unlocated_assessments', 1));

        $this->actingAs($admin)
            ->patch(route('defect-categories.update', $category), [
                'name' => $category->name,
                'code' => $category->code,
                'requires_location_map' => true,
            ])
            ->assertSessionHasErrors('confirm_location_map_requirement');

        $this->actingAs($admin)
            ->patch(route('defect-categories.update', $category), [
                'name' => $category->name,
                'code' => $category->code,
                'requires_location_map' => true,
                'confirm_location_map_requirement' => true,
            ])
            ->assertRedirect(route('defect-categories.show', $category));

        $this->assertTrue($category->fresh()->requires_location_map);
    }

    public function test_category_code_cannot_change_after_defect_use(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA']);
        $equipment = Equipment::factory()->for($organization)->create();
        Defect::factory()->forEquipment($equipment)->create(['defect_category_id' => $category->id]);

        $this->actingAs($admin)
            ->patch(route('defect-categories.update', $category), [
                'name' => 'Tratamento',
                'code' => 'TB',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_new_defect_is_linked_to_provisioned_civil_category(): void
    {
        [$organization] = $this->tenant();
        $category = app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);

        $this->assertSame('CV', $category->code);
        $this->assertCount(5, $category->classifications);
        $this->assertSame([
            'CV-1' => '#FF0000',
            'CV-2' => '#FFC000',
            'CV-3' => '#FFFF00',
            'CV-4' => '#92D050',
            'CV-5' => '#0070C0',
        ], $category->classifications->pluck('color', 'code')->all());
        $this->assertSame(RegistrationStatus::Active, $category->status);
    }

    public function test_gut_classification_is_selected_automatically_and_snapshotted(): void
    {
        [$organization, $admin] = $this->tenant();
        app(TenantContext::class)->set($organization);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);
        foreach ([GutCriterion::Gravity, GutCriterion::Urgency, GutCriterion::Trend] as $criterion) {
            DefectCategoryGutOption::factory()->create([
                'organization_id' => $organization->id,
                'defect_category_id' => $category->id,
                'criterion' => $criterion,
                'score' => 2,
            ]);
        }
        $classification = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'CV-2',
            'color' => '#FFC000',
            'lower_limit' => 8,
            'upper_limit' => 8,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create(['condition' => DefectAssessmentCondition::Worsened]);

        $updated = app(SaveDefectAssessmentGut::class)->handle($admin, $assessment, [
            'condition' => DefectAssessmentCondition::Worsened->value,
            'gravity' => 2,
            'urgency' => 2,
            'trend' => 2,
            'defect_classification_id' => null,
        ]);

        $this->assertSame($classification->id, $updated->defect_classification_id);
        $this->assertSame('CV-2', $updated->classification_code);
        $this->assertSame(8, $updated->gut_score);
        $this->assertSame('gut_range', $updated->classification_snapshot['source']);
        $this->assertSame('CV-2', $updated->classification_snapshot['code']);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('classification.code', 'CV-2')
                ->where('classification.color', '#FFC000'));
    }

    public function test_new_defect_can_use_a_configured_category_code(): void
    {
        [$organization, $admin] = $this->tenant();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Tratamento anticorrosivo',
            'code' => 'TA',
        ]);
        $equipment = Equipment::factory()->for($organization)->create(['defect_code_prefix' => 'VT009']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('inspections.defects.store', $inspection), [
                'defect_category_id' => $category->id,
                'title' => 'Falha no tratamento',
            ])
            ->assertRedirect();

        $defect = Defect::query()->latest('id')->firstOrFail();
        $this->assertSame('VT009-TA-001', $defect->code);
        $this->assertSame($category->id, $defect->defect_category_id);
        $this->assertSame('TA', $defect->categoryCode());
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
