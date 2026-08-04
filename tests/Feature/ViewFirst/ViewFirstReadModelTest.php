<?php

declare(strict_types=1);

namespace Tests\Feature\ViewFirst;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\ViewFirstCivilScenario;
use App\Services\Demo\ViewFirstDemoPresenter;
use Database\Seeders\ViewFirstDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ViewFirstReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_first_routes_require_authentication(): void
    {
        [, , , $inspection, $assessment] = $this->viewFirstScenario();

        foreach ([
            route('inspections.show', $inspection),
            route('inspections.defects', $inspection),
            route('inspections.locations', $inspection),
            route('inspections.photos', $inspection),
            route('inspections.documents', $inspection),
            route('inspections.history', $inspection),
            route('inspections.report-preview', $inspection),
            route('defect-assessments.show', $assessment),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_inspection_hub_exposes_each_tab_with_a_consistent_contract(): void
    {
        [, $admin, , $inspection] = $this->viewFirstScenario();

        $routes = [
            'overview' => route('inspections.show', $inspection),
            'defects' => route('inspections.defects', $inspection),
            'locations' => route('inspections.locations', $inspection),
            'photos' => route('inspections.photos', $inspection),
            'documents' => route('inspections.documents', $inspection),
            'history' => route('inspections.history', $inspection),
            'report' => route('inspections.report-preview', $inspection),
        ];

        foreach ($routes as $activeTab => $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Inspections/Show')
                    ->where('active_tab', $activeTab)
                    ->has('inspection.overview_url')
                    ->has('inspection.defects_url')
                    ->has('inspection.locations_url')
                    ->has('inspection.photos_url')
                    ->has('inspection.documents_url')
                    ->has('inspection.history_url')
                    ->has('inspection.report_url')
                    ->has('summary.total')
                    ->has('summary.completed')
                    ->has('summary.progress_percent')
                    ->has('summary.criticality.code')
                    ->has('summary.condition_breakdown')
                    ->has('summary.classification_breakdown')
                    ->has('tabs', 7)
                    ->has('content')
                    ->where('demo.enabled', true)
                    ->where('demo.report_revision', ViewFirstCivilScenario::REPORT_REVISION));
        }
    }

    public function test_defects_photos_and_report_expose_provisional_read_models(): void
    {
        [$organization, $admin, , $inspection] = $this->viewFirstScenario();

        $this->actingAs($admin)
            ->get(route('inspections.defects', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Show')
                ->where('active_tab', 'defects')
                ->has('content.items', 2)
                ->where('content.items.0.classification.code', 'CV-2')
                ->where('content.items.0.gut.score', 36)
                ->where('content.items.0.gut.provisional', true)
                ->has('content.items.0.characterization')
                ->has('content.items.0.quantities')
                ->has('content.items.0.evidence', 4)
                ->has('content.items.1.evidence', 3)
                ->has('content.filters', 5));

        $this->actingAs($admin)
            ->get(route('inspections.photos', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'photos')
                ->has('content.items', 7)
                ->where('content.counts', ['ready' => 7])
                ->where('content.items.0.illustrative', true)
                ->where('content.items.0.url', null));

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'report')
                ->where('content.number', ViewFirstCivilScenario::REPORT_NUMBER)
                ->where('content.revision', ViewFirstCivilScenario::REPORT_REVISION)
                ->where('content.print_enabled', true)
                ->where('content.pdf_enabled', false)
                ->where('content.validation.blocked', true)
                ->where('content.cover.provider', $organization->name)
                ->has('content.cover')
                ->has('content.executive_summary')
                ->has('content.locations', 1)
                ->has('content.findings', 1)
                ->has('content.sections', 4));
    }

    public function test_assessment_page_exposes_real_fields_and_read_only_technical_data(): void
    {
        [, $admin, , $inspection, $assessment] = $this->viewFirstScenario();

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DefectAssessments/Show')
                ->where('assessment.public_id', $assessment->public_id)
                ->where('assessment.defect.title', 'Fissura longitudinal no pedestal de concreto')
                ->where('classification.code', 'CV-2')
                ->where('classification.provisional', true)
                ->where('gut.formula', '3×4×3 = 36')
                ->has('characterization')
                ->has('quantities')
                ->has('evidence', 4)
                ->where('assessment_navigation.inspection_url', route('inspections.show', $inspection))
                ->where('assessment_navigation.defects_url', route('inspections.defects', $inspection))
                ->where('assessment_navigation.position', 1)
                ->where('assessment_navigation.total', 2)
                ->has('assessment_navigation', 6)
                ->has('condition_options', 7)
                ->where('capabilities.update', true)
                ->where('capabilities.complete', true)
                ->where('demo.enabled', true));
    }

    public function test_shared_presenter_exposes_equipment_criticality_and_inspection_progress(): void
    {
        [, , $equipment, $inspection] = $this->viewFirstScenario();
        $presenter = app(ViewFirstDemoPresenter::class);

        $this->assertSame([
            'criticality' => [
                'value' => 'CV-2',
                'label' => 'Alta',
                'is_provisional' => true,
            ],
        ], $presenter->equipment($equipment));

        $this->assertSame([
            'completed' => 1,
            'total' => 2,
            'percentage' => 50,
        ], $presenter->progress($inspection));
    }

    public function test_empty_inspection_keeps_the_hub_renderable_without_inventing_criticality(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create(['status' => InspectionStatus::InProgress]);

        $this->assertSame(
            ['criticality' => null],
            app(ViewFirstDemoPresenter::class)->equipment($equipment),
        );

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 0)
                ->where('summary.completed', 0)
                ->where('summary.pending', 0)
                ->where('summary.criticality.code', '—')
                ->has('content.highlights', 0));

        $this->actingAs($admin)
            ->get(route('inspections.photos', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items', 0)
                ->where('content.counts', []));
    }

    public function test_new_condition_is_not_offered_for_a_reinspection_assessment(): void
    {
        [, $admin, $equipment, $inspection, $assessment] = $this->viewFirstScenario();
        $previous = Inspection::factory()
            ->forEquipment($equipment)
            ->create(['status' => InspectionStatus::Released]);

        $assessment->defect->update(['first_inspection_id' => $previous->id]);
        $inspection->update(['previous_inspection_id' => $previous->id]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('condition_options', 6)
                ->where('condition_options', fn ($options): bool => collect($options)
                    ->doesntContain('value', DefectAssessmentCondition::New->value)));
    }

    public function test_official_demo_scenario_delivers_fourteen_findings_and_full_photo_gallery(): void
    {
        $this->seed(ViewFirstDemoSeeder::class);

        $admin = User::query()->where('email', 'demo@vistoria.test')->firstOrFail();
        $inspection = Inspection::query()
            ->where('service_order', ViewFirstDemoSeeder::CURRENT_INSPECTION_SERVICE_ORDER)
            ->firstOrFail();
        $previousInspection = Inspection::query()
            ->where('service_order', ViewFirstDemoSeeder::PREVIOUS_INSPECTION_SERVICE_ORDER)
            ->firstOrFail();
        $draft = DefectAssessment::query()
            ->where('inspection_id', $inspection->id)
            ->where('status', DefectAssessmentStatus::Draft->value)
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 14)
                ->where('summary.completed', 13)
                ->where('summary.pending', 1)
                ->where('summary.progress_percent', 93)
                ->where('summary.criticality.code', 'CV-2'));

        $this->actingAs($admin)
            ->get(route('inspections.photos', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items', 36)
                ->where('content.counts', ['ready' => 36]));

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.condition', DefectAssessmentCondition::New->value)
                ->has('condition_options', 7)
                ->where('capabilities.update', true)
                ->where('capabilities.complete', true));

        $previousFissureAssessment = DefectAssessment::query()
            ->where('inspection_id', $previousInspection->id)
            ->whereHas('defect', fn ($query) => $query->where('title', 'Fissura longitudinal no pedestal de concreto'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('inspections.defects', $previousInspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 13)
                ->where('summary.completed', 13)
                ->where('summary.pending', 0)
                ->has('content.items', 13)
                ->where('content.items', fn ($items): bool => collect($items)
                    ->doesntContain('title', 'Desplacamento do cobrimento na base do motor')));

        $this->assertSame([
            'completed' => 13,
            'total' => 13,
            'percentage' => 100,
        ], app(ViewFirstDemoPresenter::class)->progress($previousInspection));

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $previousFissureAssessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment_navigation.total', 13)
                ->has('evidence', 4)
                ->where('evidence.0.location', 'Face norte do pedestal, eixo do motor, entre as cotas +0,10 m e +0,70 m.'));

        $futureInspection = Inspection::factory()
            ->reinspection($inspection)
            ->create([
                'status' => InspectionStatus::InProgress,
                'scheduled_for' => '2027-08-03',
                'inspected_on' => '2027-08-03',
            ]);

        $this->assertSame([
            'completed' => 0,
            'total' => 13,
            'percentage' => 0,
        ], app(ViewFirstDemoPresenter::class)->progress($futureInspection));

        $this->actingAs($admin)
            ->get(route('inspections.defects', $futureInspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 13)
                ->where('summary.pending', 13)
                ->has('content.items', 13)
                ->where('content.items', fn ($items): bool => collect($items)
                    ->doesntContain('title', 'Fissura capilar no bloco de fundação')));
    }

    public function test_view_first_reads_are_isolated_by_tenant(): void
    {
        [, , , $inspection, $assessment] = $this->viewFirstScenario();
        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()
            ->for($otherOrganization)
            ->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        foreach ([
            route('inspections.show', $inspection),
            route('inspections.defects', $inspection),
            route('inspections.locations', $inspection),
            route('inspections.photos', $inspection),
            route('inspections.documents', $inspection),
            route('inspections.history', $inspection),
            route('inspections.report-preview', $inspection),
            route('defect-assessments.show', $assessment),
        ] as $url) {
            $this->actingAs($otherAdmin)->get($url)->assertNotFound();
        }
    }

    public function test_assessment_writes_persist_only_real_fields_and_return_to_dedicated_page(): void
    {
        [, $admin, , , $assessment] = $this->viewFirstScenario();

        $this->actingAs($admin)
            ->patch(route('defect-assessments.update', $assessment), [
                'condition' => DefectAssessmentCondition::Worsened->value,
                'location_description' => 'Face norte do pedestal',
                'comment' => 'Abertura maior do que na inspeção anterior.',
                'recommendation' => 'Executar reparo estrutural prioritário.',
                'reason' => null,
                'internal_notes' => 'Confirmar tratamento com a engenharia.',
                'gut' => ['severity' => 5, 'urgency' => 5, 'tendency' => 5],
                'classification' => 'CV-1',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment));

        $assessment->refresh();

        $this->assertSame(DefectAssessmentCondition::Worsened, $assessment->condition);
        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertSame('Face norte do pedestal', $assessment->location_description);
        $this->assertSame('Abertura maior do que na inspeção anterior.', $assessment->comment);
        $this->assertSame('Executar reparo estrutural prioritário.', $assessment->recommendation);
        $this->assertSame('Confirmar tratamento com a engenharia.', $assessment->internal_notes);
        $this->assertNull($assessment->defect_snapshot);

        $this->actingAs($admin)
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::Worsened->value,
                'location_description' => 'Face norte do pedestal',
                'comment' => 'Abertura maior do que na inspeção anterior.',
                'recommendation' => 'Executar reparo estrutural prioritário.',
                'reason' => null,
                'internal_notes' => 'Confirmar tratamento com a engenharia.',
                'gut_score' => 125,
                'cv' => 'CV-1',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment));

        $assessment->refresh();

        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertNotNull($assessment->assessed_at);
        $this->assertSame($assessment->defect->code, data_get($assessment->defect_snapshot, 'defect.code'));
    }

    /**
     * @return array{Organization, User, Equipment, Inspection, DefectAssessment}
     */
    private function viewFirstScenario(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create(['defect_code_prefix' => 'VT002']);
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'number' => 'INS-2026-000002',
                'status' => InspectionStatus::InProgress,
                'scheduled_for' => '2026-08-04',
                'inspected_on' => '2026-08-04',
            ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $fissure = Defect::factory()
            ->forEquipment($equipment, $inspection)
            ->create([
                'code' => 'VT002-CV-001',
                'sequence_number' => 1,
                'title' => 'Fissura longitudinal no pedestal de concreto',
            ]);
        $assessment = DefectAssessment::factory()
            ->forDefect($fissure, $inspection)
            ->draft()
            ->create([
                'condition' => DefectAssessmentCondition::Worsened,
                'location_description' => 'Face norte do pedestal',
                'comment' => 'Manifestação observada em campo.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

        $moisture = Defect::factory()
            ->forEquipment($equipment, $inspection)
            ->create([
                'code' => 'VT002-CV-002',
                'sequence_number' => 2,
                'title' => 'Umidade superficial na canaleta adjacente',
            ]);
        DefectAssessment::factory()
            ->forDefect($moisture, $inspection)
            ->complete()
            ->create([
                'condition' => DefectAssessmentCondition::Improved,
                'comment' => 'Condição melhorou.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

        return [$organization, $admin, $equipment, $inspection, $assessment];
    }
}
