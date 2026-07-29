<?php

namespace Tests\Feature\Inspections;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionPagesTest extends TestCase
{
    public function test_index_exposes_pagination_filters_and_capabilities(): void
    {
        Route::get('/_test/inspections', fn () => Inertia::render('Inspections/Index', [
            'inspections' => ['data' => [], 'links' => [], 'total' => 0],
            'filters' => [
                'number' => 'INS-2026', 'equipment' => 'eq-1', 'status' => 'planned',
                'type' => 'initial', 'responsible' => 'user-1', 'from' => '2026-01-01', 'to' => '2026-12-31',
            ],
            'options' => ['equipment' => [], 'statuses' => [], 'responsibles' => []],
            'capabilities' => ['create' => true],
            'create_url' => '/inspections/create',
        ]));

        $this->withHeader('X-Inertia', 'true')->get('/_test/inspections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->has('inspections.data')
                ->where('filters.number', 'INS-2026')
                ->where('filters.equipment', 'eq-1')
                ->where('filters.status', 'planned')
                ->where('filters.type', 'initial')
                ->where('filters.responsible', 'user-1')
                ->where('filters.from', '2026-01-01')
                ->where('filters.to', '2026-12-31')
                ->where('capabilities.create', true));
    }

    public function test_create_exposes_only_released_previous_inspections(): void
    {
        Route::get('/_test/inspections/create', fn () => Inertia::render('Inspections/Create', [
            'action' => '/inspections', 'cancel_url' => '/inspections',
            'equipment' => [['id' => 'eq-1', 'tag' => 'EQ-01', 'name' => 'Bomba']],
            'released_inspections' => [[
                'id' => 'inspection-1', 'equipment_id' => 'eq-1', 'number' => 'INS-2025-000001',
                'status' => 'released', 'released_at' => '10/12/2025',
            ]],
        ]));

        $this->withHeader('X-Inertia', 'true')->get('/_test/inspections/create')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Create')
                ->has('equipment', 1)
                ->has('released_inspections', 1)
                ->where('released_inspections.0.status', 'released')
                ->where('released_inspections.0.equipment_id', 'eq-1'));
    }

    public function test_show_exposes_read_only_snapshot_history_and_independent_capabilities(): void
    {
        Route::get('/_test/inspections/one', fn () => Inertia::render('Inspections/Show', [
            'inspection' => [
                'number' => 'INS-2026-000001', 'status' => 'awaiting_review', 'type' => 'initial',
                'equipment' => ['tag' => 'EQ-01', 'name' => 'Bomba'],
                'context_snapshot' => ['equipment' => ['tag' => 'EQ-01', 'name' => 'Bomba']],
                'snapshot_version' => 1, 'responsibles' => [], 'reference_documents' => [],
                'history' => [['id' => 1, 'to_status' => 'awaiting_review', 'created_at' => '29/07/2026', 'user' => ['name' => 'Ana']]],
            ],
            'capabilities' => [
                'assign_responsibles' => ['action' => '/assign'],
                'manage_references' => false,
                'transition' => true,
            ],
            'assignment_options' => ['users' => [], 'roles' => []], 'available_documents' => [],
            'transitions' => [['key' => 'correct', 'label' => 'Solicitar correção', 'action' => '/correct', 'requires_justification' => true]],
            'index_url' => '/inspections',
        ]));

        $this->withHeader('X-Inertia', 'true')->get('/_test/inspections/one')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Show')
                ->has('inspection.context_snapshot')
                ->has('inspection.history', 1)
                ->has('inspection.responsibles')
                ->has('inspection.reference_documents')
                ->has('capabilities.assign_responsibles.action')
                ->where('capabilities.manage_references', false)
                ->where('capabilities.transition', true)
                ->where('transitions.0.requires_justification', true));
    }

    public function test_correction_and_cancel_forms_require_justification(): void
    {
        $source = file_get_contents(resource_path('js/components/domain/inspections/TransitionForm.vue'));

        $this->assertStringContainsString("['correct', 'cancel']", $source);
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
