<?php

declare(strict_types=1);

namespace Tests\Feature\ViewFirst;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\ViewFirstDemoPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            route('inspections.report-overview', $inspection),
            route('inspections.defects', $inspection),
            route('inspections.photos', $inspection),
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
            'report_overview' => route('inspections.report-overview', $inspection),
            'defects' => route('inspections.defects', $inspection),
            'photos' => route('inspections.photos', $inspection),
            'history' => route('inspections.history', $inspection),
            'report' => route('inspections.report-preview', $inspection),
        ];

        foreach ($routes as $activeTab => $url) {
            if ($activeTab === 'report_overview') {
                $this->actingAs($admin)
                    ->get($url)
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component('Inspections/ReportOverview')
                        ->where('active_tab', 'report_overview')
                        ->has('overview.blocks', 2)
                        ->has('tabs', 6));

                continue;
            }

            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Inspections/Show')
                    ->where('active_tab', $activeTab)
                    ->has('inspection.overview_url')
                    ->has('inspection.defects_url')
                    ->missing('inspection.locations_url')
                    ->has('inspection.photos_url')
                    ->has('inspection.history_url')
                    ->has('inspection.report_url')
                    ->has('summary.total')
                    ->has('summary.completed')
                    ->has('summary.progress_percent')
                    ->has('summary.criticality.code')
                    ->has('summary.condition_breakdown')
                    ->has('summary.classification_breakdown')
                    ->has('tabs', 6)
                    ->where('tabs', fn ($tabs): bool => collect($tabs)->doesntContain('key', 'locations'))
                    ->has('content')
                    ->missing('demo'));
        }
    }

    public function test_regular_organization_does_not_receive_demo_fallback_data(): void
    {
        [$organization, $admin, , $inspection] = $this->viewFirstScenario();

        $this->actingAs($admin)
            ->get(route('inspections.defects', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Show')
                ->where('active_tab', 'defects')
                ->has('content.items', 2)
                ->where('content.items.0.classification.code', '—')
                ->where('content.items.0.gut', null)
                ->has('content.items.0.characterization')
                ->has('content.items.0.quantities', 0)
                ->has('content.items.0.evidence', 0)
                ->has('content.items.1.evidence', 0)
                ->has('content.filters', 7)
                ->where('content.filters.0.key', 'active'));

        $this->actingAs($admin)
            ->get(route('inspections.photos', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'photos')
                ->has('content.items', 0)
                ->where('content.counts', []));

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'report')
                ->where('content.number', 'U0306VT-G-6RI002')
                ->where('content.external_report_number', 'U0306VT-G-6RI002')
                ->where('content.report_designer', 'PROJETISTA II')
                ->where('content.designer_i_report_number', 'SM-IIE-1717')
                ->where('content.revision', '0')
                ->where('content.print_enabled', false)
                ->where('content.validation.blocked', true)
                ->where('content.cover.provider', $organization->name)
                ->where('content.cover.client_logo_url', null)
                ->where('content.cover.provider_logo_url', null)
                ->where('content.cover.external_report_number', 'U0306VT-G-6RI002')
                ->where('content.cover.report_designer', 'PROJETISTA II')
                ->where('content.cover.designer_i_report_number', 'SM-IIE-1717')
                ->has('content.cover.approval_flow', 4)
                ->where('content.cover.approval_flow.0.label', 'Preparado')
                ->where('content.cover.approval_flow.0.name', $admin->name)
                ->where('content.cover.approval_flow.1.label', 'Verificado')
                ->where('content.cover.approval_flow.1.name', null)
                ->where('content.cover.approval_date', '—')
                ->has('content.cover')
                ->has('content.executive_summary')
                ->where('content.location_source', 'inspection_maps')
                ->has('content.locations', 0)
                ->has('content.findings', 1)
                ->has('content.sections', 3));
    }

    public function test_report_header_payload_exposes_client_and_provider_logos(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->viewFirstScenario();
        $organization->update(['logo_path' => 'organizations/provider-logo.png']);
        $equipment->client->update(['logo_path' => 'clients/client-logo.png']);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'content.cover.client_logo_url',
                    Storage::disk('public')->url('clients/client-logo.png'),
                )
                ->where(
                    'content.cover.provider_logo_url',
                    Storage::disk('public')->url('organizations/provider-logo.png'),
                )
                ->where('content.cover.external_report_number', 'U0306VT-G-6RI002')
                ->where('content.cover.report_designer', 'PROJETISTA II')
                ->where('content.cover.designer_i_report_number', 'SM-IIE-1717')
                ->where('content.cover.current_revision', '0'));
    }

    public function test_report_export_is_disabled_when_external_report_number_is_missing(): void
    {
        [, $admin, , $inspection] = $this->viewFirstScenario();
        $inspection->update(['external_report_number' => null]);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', false)
                ->where('content.number', $inspection->number)
                ->where('content.external_report_number', null)
                ->where('content.cover.external_report_number', null)
                ->where(
                    'content.export_disabled_reason',
                    'Informe o Número do relatório externo para exportar o relatório. 1 registro(s) ainda não foram consolidados.',
                )
                ->where('content.validation.issues', fn ($issues): bool => collect($issues)->contains(
                    'Informe o Número do relatório externo para exportar o relatório.',
                )));
    }

    public function test_report_export_is_disabled_when_designer_i_report_number_is_missing(): void
    {
        [, $admin, , $inspection] = $this->viewFirstScenario();
        $inspection->update(['designer_i_report_number' => null]);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', false)
                ->where('content.external_report_number', 'U0306VT-G-6RI002')
                ->where('content.designer_i_report_number', null)
                ->where('content.cover.designer_i_report_number', null)
                ->where(
                    'content.export_disabled_reason',
                    'Informe o Nº Projetista I para exportar o relatório. 1 registro(s) ainda não foram consolidados.',
                )
                ->where('content.validation.issues', fn ($issues): bool => collect($issues)->contains(
                    'Informe o Nº Projetista I para exportar o relatório.',
                )));
    }

    public function test_report_export_accumulates_missing_external_and_designer_numbers(): void
    {
        [, $admin, , $inspection] = $this->viewFirstScenario();
        $inspection->update([
            'external_report_number' => null,
            'designer_i_report_number' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', false)
                ->where(
                    'content.export_disabled_reason',
                    'Informe o Número do relatório externo para exportar o relatório. Informe o Nº Projetista I para exportar o relatório. 1 registro(s) ainda não foram consolidados.',
                )
                ->where('content.validation.issues', fn ($issues): bool => collect($issues)->contains(
                    'Informe o Número do relatório externo para exportar o relatório.',
                ) && collect($issues)->contains(
                    'Informe o Nº Projetista I para exportar o relatório.',
                )));
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
                ->where('classification.code', '—')
                ->where('classification.provisional', false)
                ->where('gut', null)
                ->has('characterization')
                ->has('quantities', 0)
                ->where('quantity_summary.total', null)
                ->has('gut_classification_ranges', 5)
                ->has('evidence', 0)
                ->has('measurement_units', 9)
                ->where('assessment_navigation.inspection_url', route('inspections.show', $inspection))
                ->where('assessment_navigation.defects_url', route('inspections.defects', $inspection))
                ->missing('assessment_navigation.locations_url')
                ->where('assessment_navigation.position', 1)
                ->where('assessment_navigation.total', 2)
                ->has('assessment_navigation', 6)
                ->has('condition_options', 6)
                ->where('capabilities.update', true)
                ->where('capabilities.complete', true)
                ->missing('capabilities.locations_url')
                ->has('capabilities.quantity_store_url')
                ->missing('demo'));
    }

    public function test_assessment_page_exposes_native_category_gut_ranges_with_read_only_capabilities(): void
    {
        [$organization, $admin, , , $assessment] = $this->viewFirstScenario();
        $category = DefectCategory::Civil;
        $assessment->defect->update(['category' => $category->value]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gut_classification_ranges', 5)
                ->where('gut_classification_ranges.0.code', 'CV-1')
                ->missing('capabilities.manual_classification_url')
                ->has('capabilities.status_url'));

        $viewer = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Member,
        ]);

        $this->actingAs($viewer)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gut_classification_ranges', 5)
                ->where('capabilities.update', false)
                ->missing('capabilities.manual_classification_url')
                ->where('capabilities.quantity_store_url', null)
                ->where('capabilities.photo_upload_url', null)
                ->where('capabilities.status_url', null));
    }

    public function test_report_uses_persisted_ready_photo_when_available(): void
    {
        [, $admin, , $inspection] = $this->viewFirstScenario();
        $assessment = DefectAssessment::query()
            ->where('inspection_id', $inspection->getKey())
            ->where('status', DefectAssessmentStatus::Complete)
            ->firstOrFail();
        $category = DefectCategory::Civil;
        $assessment->defect->update(['category' => $category->value]);
        $mapVersion = $this->locateAssessment($assessment);
        Storage::fake('inspection_photos');
        Storage::disk('inspection_photos')->put('photos/optimized.webp', 'optimized');
        Storage::disk('inspection_photos')->put('photos/thumbnail.webp', 'thumbnail');
        Storage::disk('inspection_photos')->put('photos/original.jpg', 'original');

        $photo = AssessmentPhoto::factory()
            ->for($inspection)
            ->for($assessment, 'assessment')
            ->ready()
            ->create([
                'organization_id' => $inspection->organization_id,
                'original_path' => 'photos/original.jpg',
                'optimized_path' => 'photos/optimized.webp',
                'thumbnail_path' => 'photos/thumbnail.webp',
            ]);
        $unmappedDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => $category->value,
            'code' => 'VT002-CV-003',
            'sequence_number' => 3,
            'title' => 'Avaria sem localização no mapa',
        ]);
        $unmappedAssessment = DefectAssessment::factory()
            ->forDefect($unmappedDefect, $inspection)
            ->complete()
            ->create();
        $unmappedPhoto = AssessmentPhoto::factory()
            ->for($inspection)
            ->for($unmappedAssessment, 'assessment')
            ->ready()
            ->create(['organization_id' => $inspection->organization_id]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('evidence.0.id', $photo->public_id)
                ->where('evidence.0.reorder_url', null)
                ->where('evidence.0.report_number', 1)
                ->where('evidence.0.report_category', 'CV')
                ->where('evidence.0.delete_url', null));

        $viewer = User::factory()->for($inspection->organization)->create([
            'account_type' => UserAccountType::Member,
        ]);

        $this->actingAs($viewer)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('evidence.0.id', $photo->public_id)
                ->where('evidence.0.reorder_url', null)
                ->where('evidence.0.report_number', 1)
                ->where('evidence.0.report_category', 'CV')
                ->where('evidence.0.delete_url', null));

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $unmappedAssessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('evidence.0.id', $unmappedPhoto->public_id)
                ->where('evidence.0.report_number', null)
                ->where('evidence.0.report_category', 'CV'));

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.sections.1.items.0.id', $photo->public_id)
                ->where('content.sections.1.items.0.url', route('assessment-photos.show', [$photo, 'optimized']))
                ->has('content.sections.1.items', 1)
                ->has('content.photographic_documentation.blocks', 1)
                ->where('content.photographic_documentation.photo_count', 1)
                ->where('content.photographic_documentation.blocks.0.assessment_public_id', $assessment->public_id)
                ->has('content.location_sequence', 1)
                ->where('content.location_sequence.0.map.public_id', $mapVersion->map->public_id)
                ->where('content.location_sequence.0.annex_title', fn ($title): bool => is_string($title)
                    && str_starts_with($title, 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - '))
                ->where('content.location_sequence.0.photographic_blocks.0.assessment_public_id', $assessment->public_id)
                ->where('content.photographic_documentation.blocks.0.photos.0.id', $photo->public_id)
                ->where('content.photographic_documentation.blocks.0.photos.0.title', 'Umidade superficial na canaleta adjacente')
                ->where('content.photographic_documentation.blocks.0.comment', 'Condição melhorou.')
                ->where('content.photographic_documentation.blocks', fn ($blocks): bool => collect($blocks)
                    ->flatMap(fn (array $block): array => $block['photos'])
                    ->doesntContain('id', $unmappedPhoto->public_id)));

        $inspection->update(['status' => InspectionStatus::Released]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.update', false)
                ->where('evidence.0.id', $photo->public_id)
                ->where('evidence.0.reorder_url', null)
                ->where('evidence.0.report_number', 1)
                ->where('evidence.0.report_category', 'CV')
                ->where('evidence.0.delete_url', null));
    }

    public function test_shared_presenter_exposes_equipment_criticality_and_inspection_progress(): void
    {
        [, , $equipment, $inspection] = $this->viewFirstScenario();
        $presenter = app(ViewFirstDemoPresenter::class);

        $this->assertSame([
            'criticality' => [
                'value' => '—',
                'label' => 'Não classificada',
                'is_critical' => false,
                'is_provisional' => false,
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
            ->create([
                'operational_role' => OperationalRole::Inspector->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);

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
                ->has('condition_options', 5)
                ->where('condition_options', fn ($options): bool => collect($options)
                    ->doesntContain('value', DefectAssessmentCondition::New->value)));
    }

    public function test_official_demo_scenario_delivers_fourteen_findings_and_full_photo_gallery(): void
    {
        $this->markTestSkipped('O cenário demonstrativo foi removido; os dados operacionais são criados por factories.');

        Storage::fake('inspection_photos');
        Storage::fake('inspection_maps');
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
                ->where('content.counts', ['ready' => 36])
                ->where('content.items.0.illustrative', false)
                ->where('content.items.0.url', fn ($url): bool => is_string($url) && $url !== ''));

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.location_source', 'inspection_maps')
                ->has('content.locations', 2)
                ->has('content.locations.0.maps', 1)
                ->has('content.locations.1.maps', 1)
                ->has('content.locations.0.maps.0.markers', 6)
                ->has('content.locations.1.maps.0.markers', 7)
                ->where('content.location_snapshot.map_count', 2)
                ->where('content.location_snapshot.marker_count', 13)
                ->has('content.cover.approval_flow', 4)
                ->where('content.cover.approval_flow.0.label', 'Preparado')
                ->where('content.cover.approval_flow.0.name', 'Ricardo Almeida — Diretor Técnico')
                ->where('content.cover.approval_flow.1.label', 'Verificado')
                ->where('content.cover.approval_flow.1.name', 'Ana Paula Mendes — Verificadora Técnica')
                ->where('content.cover.approval_flow.2.label', 'Aprovado')
                ->where('content.cover.approval_flow.3.label', 'Liberado')
                ->where('content.cover.service_order', ViewFirstDemoSeeder::CURRENT_INSPECTION_SERVICE_ORDER)
                ->where('content.photographic_documentation.photo_count', 33)
                ->has('content.photographic_documentation.blocks', 18)
                ->where('content.photographic_documentation.blocks.0.category', 'CV')
                ->where('content.photographic_documentation.blocks.0.photos.0.sequence', 1)
                ->where('content.photographic_documentation.blocks.0.photos.1.sequence', 2));

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.condition', DefectAssessmentCondition::New->value)
                ->has('condition_options', 6)
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
                ->has('evidence', 0));

        $futureInspection = Inspection::factory()
            ->reinspection($inspection)
            ->create([
                'status' => InspectionStatus::InProgress,
                'planned_start_on' => '2027-08-03',
                'planned_end_on' => '2027-08-03',
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
            route('inspections.photos', $inspection),
            route('inspections.history', $inspection),
            route('inspections.report-preview', $inspection),
            route('defect-assessments.show', $assessment),
        ] as $url) {
            $this->actingAs($otherAdmin)->get($url)->assertNotFound();
        }
    }

    public function test_assessment_writes_persist_only_real_fields_and_return_to_dedicated_page(): void
    {
        [$organization, $admin, , , $assessment] = $this->viewFirstScenario();
        $previousInspection = Inspection::factory()
            ->forEquipment($assessment->inspection->equipment)
            ->create([
                'number' => 'INS-2026-000001',
                'status' => InspectionStatus::Released,
            ]);
        $assessment->inspection->update([
            'previous_inspection_id' => $previousInspection->id,
            'inspection_type' => InspectionType::Reinspection,
        ]);
        $assessment->defect->update(['first_inspection_id' => $previousInspection->id]);
        $previousAssessment = DefectAssessment::factory()
            ->forDefect($assessment->defect, $previousInspection)
            ->complete()
            ->create(['condition' => DefectAssessmentCondition::New]);
        $assessment->update(['previous_assessment_id' => $previousAssessment->id]);
        $category = DefectCategory::Civil;
        $assessment->defect->update(['category' => $category->value]);

        $this->actingAs($admin)
            ->patch(route('defect-assessments.update', $assessment), [
                'condition' => DefectAssessmentCondition::Reclassified->value,
                'location_description' => 'Face norte do pedestal',
                'comment' => 'Abertura maior do que na inspeção anterior.',
                'recommendation' => 'Executar reparo estrutural prioritário.',
                'reason' => null,
                'internal_notes' => 'Confirmar tratamento com a engenharia.',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment));

        $assessment->refresh();

        $this->assertSame(DefectAssessmentCondition::Reclassified, $assessment->condition);
        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertSame('Face norte do pedestal', $assessment->location_description);
        $this->assertSame('Abertura maior do que na inspeção anterior.', $assessment->comment);
        $this->assertSame('Executar reparo estrutural prioritário.', $assessment->recommendation);
        $this->assertSame('Confirmar tratamento com a engenharia.', $assessment->internal_notes);
        $this->assertNull($assessment->defect_snapshot);

        $this->satisfyAssessmentPublicationRequirements($assessment);

        $this->actingAs($admin)
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::Reclassified->value,
                'location_description' => 'Face norte do pedestal',
                'comment' => 'Abertura maior do que na inspeção anterior.',
                'recommendation' => 'Executar reparo estrutural prioritário.',
                'reason' => null,
                'internal_notes' => 'Confirmar tratamento com a engenharia.',
                'safety_impact_code' => 'primary_above_2m',
                'asset_impact_code' => 'primary_general_high_criticality',
                'urgency_context_code' => 'function',
                'urgency_option_code' => 'building_support_column',
                'trend_group_code' => 'cracking',
                'trend_option_code' => 'prestressed_or_structural_mechanism',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment));

        $assessment->refresh();

        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertSame(125, $assessment->gut_score);
        $this->assertSame('CV-1', $assessment->classification_code);
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
            ->create([
                'operational_role' => OperationalRole::Inspector->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create(['defect_code_prefix' => 'VT002']);
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'number' => 'INS-2026-000002',
                'external_report_number' => 'U0306VT-G-6RI002',
                'report_designer' => 'PROJETISTA II',
                'designer_i_report_number' => 'SM-IIE-1717',
                'status' => InspectionStatus::InProgress,
                'planned_start_on' => '2026-08-04',
                'planned_end_on' => '2026-08-04',
                'inspected_on' => '2026-08-04',
                'report_revision' => 0,
            ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        foreach ([1, 2] as $position) {
            $overviewBlock = InspectionOverviewBlock::factory()
                ->forInspection($inspection, $position)
                ->create([
                    'comment' => "Comentário da Vista geral {$position}.",
                    'recommendation' => "Recomendação da Vista geral {$position}.",
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);

            foreach ([1, 2] as $slot) {
                InspectionOverviewPhoto::factory()
                    ->forBlock($overviewBlock, $slot)
                    ->ready()
                    ->create(['uploaded_by' => $admin->id]);
            }
        }

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
                'condition' => DefectAssessmentCondition::Reclassified,
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
                'condition' => DefectAssessmentCondition::Reclassified,
                'comment' => 'Condição melhorou.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

        return [$organization, $admin, $equipment, $inspection, $assessment];
    }
}
