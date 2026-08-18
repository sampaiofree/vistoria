<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionContextNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspection_page_exposes_the_flat_location_context_navigation(): void
    {
        [$user, $inspection, $assessment, $map] = $this->context();

        $this->actingAs($user)
            ->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($inspection, $assessment, $map): void {
                $page
                    ->where('inspection_navigation.mode', 'inspection')
                    ->where('inspection_navigation.inspection.public_id', $inspection->public_id)
                    ->where('inspection_navigation.inspection.equipment_tag', 'U03-06VT002')
                    ->where('inspection_navigation.inspection.status_label', 'Em inspeção')
                    ->where('inspection_navigation.items.0.label', 'Visão geral')
                    ->where('inspection_navigation.items.0.active', true)
                    ->where('inspection_navigation.items.1.label', 'Vista geral')
                    ->where('inspection_navigation.items.2.label', 'Avarias')
                    ->where('inspection_navigation.items.2.badge', '1')
                    ->where('inspection_navigation.items.2.children.0.label', '+ Adicionar avaria')
                    ->where('inspection_navigation.items.2.children.0.href', route('inspections.defects.create', $inspection))
                    ->where('inspection_navigation.items.2.children.1.label', 'CV')
                    ->where('inspection_navigation.items.2.children.1.children.0.label', 'VT002-CV-001')
                    ->where('inspection_navigation.items.2.children.1.children.0.href', route('defect-assessments.show', $assessment))
                    ->where('inspection_navigation.items.3.label', 'Localização')
                    ->where('inspection_navigation.items.3.children.0.label', '+ Novo mapa')
                    ->where('inspection_navigation.items.3.children.1.label', 'CV · Vista A-A')
                    ->where('inspection_navigation.items.3.children.1.href', route('inspection-location-maps.editor', $map))
                    ->where('inspection_navigation.items.4.label', 'Equipe e responsáveis')
                    ->where('inspection_navigation.items.4.href', route('inspections.team', $inspection))
                    ->has('navigation', 6);
            });

        $this->actingAs($user)
            ->get(route('inspections.team', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Team')
                ->where('inspection_navigation.items.0.active', false)
                ->where('inspection_navigation.items.4.active', true)
                ->where('active_tab', 'team')
                ->has('responsibles'));
    }

    public function test_assessment_and_map_routes_keep_their_inspection_context_and_active_branch(): void
    {
        [$user, , $assessment, $map, $defect] = $this->context();

        $this->actingAs($user)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.active', true)
                ->where('inspection_navigation.items.2.children.0.active', false)
                ->where('inspection_navigation.items.2.children.1.active', true)
                ->where('inspection_navigation.items.2.children.1.children.0.active', true)
                ->where('inspection_navigation.items.3.active', false));

        $this->actingAs($user)
            ->get(route('inspection-location-maps.editor', $map))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.active', false)
                ->where('inspection_navigation.items.3.active', true)
                ->where('inspection_navigation.items.3.children.0.active', false)
                ->where('inspection_navigation.items.3.children.1.active', true));

        $this->actingAs($user)
            ->get(route('defects.show', $defect))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.active', true)
                ->where('inspection_navigation.items.2.children.1.children.0.active', true));
    }

    public function test_create_defect_route_exposes_the_dedicated_form_and_active_menu_item(): void
    {
        [$user, $inspection] = $this->context();

        $this->actingAs($user)
            ->get(route('inspections.defects.create', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Create')
                ->where('inspection.number', $inspection->number)
                ->has('categories')
                ->where('action', route('inspections.defects.store', $inspection))
                ->where('cancel_url', route('inspections.defects', $inspection))
                ->where('inspection_navigation.items.2.active', true)
                ->where('inspection_navigation.items.2.children.0.label', '+ Adicionar avaria')
                ->where('inspection_navigation.items.2.children.0.active', true));
    }

    public function test_create_defect_route_respects_responsibility_and_organization_boundaries(): void
    {
        [$user, $inspection] = $this->context();
        $sameOrganizationUser = User::factory()->for($user->organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $otherOrganizationUser = User::factory()->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($sameOrganizationUser)
            ->get(route('inspections.defects.create', $inspection))
            ->assertForbidden();

        $this->actingAs($otherOrganizationUser)
            ->get(route('inspections.defects.create', $inspection))
            ->assertNotFound();
    }

    public function test_global_pages_do_not_receive_an_inspection_context(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation', null));
    }

    /**
     * @return array{User, Inspection, DefectAssessment, InspectionLocationMap, Defect}
     */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $equipment = Equipment::factory()->for($organization)->create([
            'tag' => 'U03-06VT002',
            'normalized_tag' => 'U03-06VT002',
        ]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000002',
            'status' => InspectionStatus::InProgress,
            'started_at' => now(),
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);
        $category = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'CV')
            ->firstOrFail();
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'defect_category_id' => $category->id,
            'code' => 'VT002-CV-001',
            'sequence_number' => 1,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'condition' => DefectAssessmentCondition::Worsened,
        ]);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'title' => 'Vista A-A',
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_path' => 'maps/test/background.webp',
            'background_mime_type' => 'image/webp',
            'background_width' => 1600,
            'background_height' => 900,
        ]);

        return [$user, $inspection, $assessment, $map, $defect];
    }
}
