<?php

namespace Tests\Feature\Inspections;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionPagesTest extends TestCase
{
    public function test_index_exposes_pagination_filters_and_capabilities(): void
    {
        Route::middleware(['web', HandleInertiaRequests::class])->get('/_test/inspections', fn () => Inertia::render('Inspections/Index', [
            'inspections' => ['data' => [], 'links' => [], 'total' => 0],
            'filters' => [
                'search' => 'INS-2026',
                'number' => 'INS-2026',
                'client' => 'client-1',
                'unit' => 'unit-1',
                'equipment' => 'eq-1',
                'status' => 'planned',
                'type' => 'initial',
                'inspection_type' => 'initial',
                'responsible' => 'user-1',
                'scheduled_from' => '2026-01-01',
                'scheduled_to' => '2026-12-31',
                'inspected_from' => '2026-07-01',
                'inspected_to' => '2026-07-31',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
            ],
            'options' => ['clients' => [], 'units' => [], 'equipment' => [], 'statuses' => [], 'types' => [], 'responsibles' => []],
            'capabilities' => ['create' => true],
            'create_url' => '/inspections/create',
        ]));

        $this->get('/_test/inspections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->has('inspections.data')
                ->where('filters.search', 'INS-2026')
                ->where('filters.number', 'INS-2026')
                ->where('filters.client', 'client-1')
                ->where('filters.unit', 'unit-1')
                ->where('filters.equipment', 'eq-1')
                ->where('filters.status', 'planned')
                ->where('filters.type', 'initial')
                ->where('filters.inspection_type', 'initial')
                ->where('filters.responsible', 'user-1')
                ->where('filters.scheduled_from', '2026-01-01')
                ->where('filters.scheduled_to', '2026-12-31')
                ->where('filters.inspected_from', '2026-07-01')
                ->where('filters.inspected_to', '2026-07-31')
                ->where('filters.from', '2026-01-01')
                ->where('filters.to', '2026-12-31')
                ->where('capabilities.create', true));
    }

    public function test_create_exposes_only_released_previous_inspections(): void
    {
        Route::middleware(['web', HandleInertiaRequests::class])->get('/_test/inspections/create', fn () => Inertia::render('Inspections/Create', [
            'action' => '/inspections', 'cancel_url' => '/inspections',
            'equipment' => [['id' => 'eq-1', 'tag' => 'EQ-01', 'name' => 'Bomba']],
            'released_inspections' => [[
                'id' => 'inspection-1', 'equipment_id' => 'eq-1', 'number' => 'INS-2025-000001',
                'status' => 'released', 'released_at' => '10/12/2025',
            ]],
            'inspection_types' => [
                ['value' => 'initial', 'label' => 'Inspeção inicial'],
                ['value' => 'reinspection', 'label' => 'Reinspeção'],
            ],
        ]));

        $this->get('/_test/inspections/create')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inspections/Create')
            ->has('equipment', 1)
            ->has('released_inspections', 1)
            ->has('inspection_types', 2)
            ->where('released_inspections.0.status', 'released')
            ->where('released_inspections.0.equipment_id', 'eq-1'));
    }

    public function test_show_exposes_read_only_snapshot_history_and_independent_capabilities(): void
    {
        Route::middleware(['web', HandleInertiaRequests::class])->get('/_test/inspections/one', fn () => Inertia::render('Inspections/Show', [
            'inspection' => [
                'number' => 'INS-2026-000001', 'status' => 'awaiting_review', 'type' => 'initial',
                'inspection_type_label' => 'Inspeção inicial',
                'equipment' => [
                    'tag' => 'EQ-01',
                    'name' => 'Bomba',
                    'client' => ['name' => 'Cliente A'],
                    'unit' => ['name' => 'Unidade A'],
                ],
                'service_order' => 'OS-123',
                'external_report_number' => 'REL-123',
                'procedure_number' => 'PROC-123',
                'atmospheric_classification' => 'C4',
                'scheduled_at' => '29/07/2026',
                'general_notes' => 'Notas da inspeção',
                'context_snapshot' => ['equipment' => ['tag' => 'EQ-01', 'name' => 'Bomba']],
                'snapshot_version' => 1,
                'responsibles' => [],
                'reference_documents' => [],
                'next_inspections' => [],
                'history' => [['id' => 1, 'to_status' => 'awaiting_review', 'created_at' => '29/07/2026', 'user' => ['name' => 'Ana']]],
            ],
            'capabilities' => [
                'update_planned' => ['action' => '/edit'],
                'assign_responsibles' => ['action' => '/assign'],
                'manage_references' => false,
                'transition' => true,
            ],
            'assignment_options' => ['users' => [], 'roles' => []], 'available_documents' => [],
            'transitions' => [['key' => 'return_for_correction', 'label' => 'Solicitar correção', 'action' => '/correct', 'requires_justification' => true]],
            'index_url' => '/inspections',
        ]));

        $this->get('/_test/inspections/one')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inspections/Show')
            ->has('inspection.context_snapshot')
            ->has('inspection.history', 1)
            ->has('inspection.responsibles')
            ->has('inspection.reference_documents')
            ->has('inspection.next_inspections')
            ->has('capabilities.update_planned.action')
            ->has('capabilities.assign_responsibles.action')
            ->where('capabilities.manage_references', false)
            ->where('capabilities.transition', true)
            ->where('transitions.0.key', 'return_for_correction')
            ->where('transitions.0.requires_justification', true));
    }

    public function test_edit_exposes_planning_context_and_form_state(): void
    {
        Route::middleware(['web', HandleInertiaRequests::class])->get('/_test/inspections/edit', fn () => Inertia::render('Inspections/Edit', [
            'inspection' => [
                'number' => 'INS-2026-000001',
                'status' => 'planned',
                'type' => 'initial',
                'inspection_type_label' => 'Inspeção inicial',
                'equipment' => [
                    'tag' => 'EQ-01',
                    'name' => 'Bomba',
                    'client' => ['name' => 'Cliente A'],
                    'unit' => ['name' => 'Unidade A'],
                ],
                'previous_inspection' => null,
                'service_order' => 'OS-123',
                'external_report_number' => 'REL-123',
                'procedure_number' => 'PROC-123',
                'atmospheric_classification' => 'C4',
                'scheduled_at' => '29/07/2026',
                'scheduled_for_input' => '2026-07-29',
                'general_notes' => 'Notas da inspeção',
            ],
            'action' => '/inspections/1',
            'cancel_url' => '/inspections/1',
            'inspection_types' => [
                ['value' => 'initial', 'label' => 'Inspeção inicial'],
                ['value' => 'reinspection', 'label' => 'Reinspeção'],
            ],
        ]));

        $this->get('/_test/inspections/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Edit')
                ->where('inspection.number', 'INS-2026-000001')
                ->where('inspection.scheduled_for_input', '2026-07-29')
                ->where('inspection.service_order', 'OS-123')
                ->where('inspection.general_notes', 'Notas da inspeção')
                ->has('inspection.equipment.client')
                ->has('inspection_types', 2));
    }

    public function test_correction_and_cancel_forms_require_justification(): void
    {
        $source = file_get_contents(resource_path('js/components/domain/inspections/TransitionForm.vue'));

        $this->assertStringContainsString('requires_justification === true', $source);
        $this->assertStringContainsString('required rows="3"', $source);
    }

    public function test_snapshot_has_no_editable_controls(): void
    {
        $source = file_get_contents(resource_path('js/components/domain/inspections/InspectionSnapshot.vue'));

        $this->assertStringNotContainsString('<input', $source);
        $this->assertStringNotContainsString('<textarea', $source);
        $this->assertStringNotContainsString('<form', $source);
    }
}
