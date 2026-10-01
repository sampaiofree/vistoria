<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionTechnicalReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_inspector_saves_references_visible_in_overview_classification_and_report(): void
    {
        [$inspection, $inspector] = $this->scenario(InspectionStatus::InProgress, OperationalRole::Inspector, InspectionResponsibility::Reviewer);
        $url = route('inspections.technical-references.update', $inspection);

        $this->actingAs($inspector)->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('technical_references.general_drawing', null)
                ->where('technical_references.procedure_number', null)
                ->where('capabilities.manage_technical_references.action', $url));

        $this->actingAs($inspector)->put($url, [
            'general_drawing' => '  U030600-S-551729  ',
            'procedure_number' => '  T000000-S-2PO006_R-04  ',
        ])->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame('U030600-S-551729', $inspection->general_drawing);
        $this->assertSame('T000000-S-2PO006_R-04', $inspection->procedure_number);
        $this->assertSame($inspector->id, $inspection->updated_by);

        $history = $inspection->statusHistories()->sole();
        $this->assertSame($inspector->id, $history->changed_by);
        $this->assertSame('classification_updated', $history->metadata['event']);
        $this->assertSame('U030600-S-551729', $history->metadata['after']['general_drawing']);
        $this->assertSame('T000000-S-2PO006_R-04', $history->metadata['after']['procedure_number']);

        $this->actingAs($inspector)->get(route('inspections.classifications', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.classification_summary.header.general_drawing', 'U030600-S-551729')
                ->where('content.classification_summary.header.procedure_number', 'T000000-S-2PO006_R-04')
                ->where('content.classification_summary.header_update_url', null));

        $this->actingAs($inspector)->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.classification_summary.header.general_drawing', 'U030600-S-551729')
                ->where('content.classification_summary.header.procedure_number', 'T000000-S-2PO006_R-04'));

        $this->actingAs($inspector)->put($url, ['general_drawing' => '', 'procedure_number' => ''])
            ->assertRedirect(route('inspections.show', $inspection));
        $this->assertNull($inspection->fresh()->general_drawing);
        $this->assertNull($inspection->fresh()->procedure_number);
    }

    public function test_inspector_can_edit_during_correction_and_reviewer_during_review(): void
    {
        [$inspection, $inspector] = $this->scenario(InspectionStatus::InCorrection, OperationalRole::Inspector, InspectionResponsibility::Reviewer);
        $this->actingAs($inspector)->put(route('inspections.technical-references.update', $inspection), [
            'general_drawing' => 'D-1', 'procedure_number' => 'P-1',
        ])->assertRedirect();

        $inspection->update(['status' => InspectionStatus::InReview]);
        $reviewer = User::factory()->for($inspection->organization)->create(['operational_role' => OperationalRole::Reviewer]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create(['responsibility' => InspectionResponsibility::Approver]);

        $this->actingAs($reviewer)->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('technical_references.general_drawing', 'D-1')
                ->where('capabilities.manage_technical_references.action', route('inspections.technical-references.update', $inspection)));
        $this->actingAs($reviewer)->put(route('inspections.technical-references.update', $inspection), [
            'general_drawing' => 'D-2', 'procedure_number' => 'P-2',
        ])->assertRedirect();
        $this->assertSame('D-2', $inspection->fresh()->general_drawing);
        $this->assertSame($reviewer->id, $inspection->statusHistories()->latest('id')->firstOrFail()->changed_by);
    }

    public function test_planner_unassigned_user_and_late_inspector_cannot_edit_references(): void
    {
        [$inspection, $inspector] = $this->scenario(InspectionStatus::InProgress, OperationalRole::Inspector, InspectionResponsibility::Reviewer);
        $planner = User::factory()->for($inspection->organization)->create(['operational_role' => OperationalRole::Planner]);
        InspectionResponsible::factory()->forInspection($inspection, $planner)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $outsider = User::factory()->for($inspection->organization)->create(['operational_role' => OperationalRole::Inspector]);
        $payload = ['general_drawing' => 'D-3', 'procedure_number' => 'P-3'];
        $url = route('inspections.technical-references.update', $inspection);

        foreach ([$planner, $outsider] as $actor) {
            $this->actingAs($actor)->put($url, $payload)->assertForbidden();
        }

        $this->actingAs($planner)->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('capabilities.manage_technical_references', false));

        $inspection->update(['status' => InspectionStatus::AwaitingM2]);
        $this->actingAs($inspector)->put($url, $payload)->assertForbidden();
        $this->actingAs($inspector)->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('capabilities.manage_technical_references', false));
        $this->assertNull($inspection->fresh()->general_drawing);
        $this->assertSame(0, $inspection->statusHistories()->count());
    }

    public function test_references_are_optional_but_cannot_exceed_150_characters(): void
    {
        [$inspection, $inspector] = $this->scenario(InspectionStatus::InProgress, OperationalRole::Inspector, InspectionResponsibility::Reviewer);
        $this->actingAs($inspector)->put(route('inspections.technical-references.update', $inspection), [
            'general_drawing' => str_repeat('A', 151),
            'procedure_number' => str_repeat('B', 151),
        ])->assertSessionHasErrors(['general_drawing', 'procedure_number']);
        $this->actingAs($inspector)->put(route('inspections.technical-references.update', $inspection), [
            'general_drawing' => ['invalid'],
            'procedure_number' => ['invalid'],
        ])->assertSessionHasErrors(['general_drawing', 'procedure_number']);
        $this->assertNull($inspection->fresh()->general_drawing);
        $this->assertNull($inspection->fresh()->procedure_number);
    }

    public function test_classification_date_route_cannot_change_existing_references(): void
    {
        [$inspection, $planner] = $this->scenario(InspectionStatus::AwaitingM2, OperationalRole::Planner, InspectionResponsibility::Preparer);
        $inspection->update(['general_drawing' => 'D-original', 'procedure_number' => 'P-original']);
        $url = route('inspections.classification-header.update', $inspection);

        $this->actingAs($planner)->put($url, [
            'general_drawing' => 'D-alterado',
            'procedure_number' => 'P-alterado',
            'inspected_on' => '2026-05-15',
        ])->assertSessionHasErrors(['general_drawing', 'procedure_number']);
        $this->assertNull($inspection->fresh()->inspected_on);

        $this->actingAs($planner)->put($url, ['inspected_on' => '2026-05-15'])->assertRedirect();
        $inspection->refresh();
        $this->assertSame('D-original', $inspection->general_drawing);
        $this->assertSame('P-original', $inspection->procedure_number);
        $this->assertSame('2026-05-15', $inspection->inspected_on?->toDateString());
    }

    /** @return array{Inspection, User} */
    private function scenario(InspectionStatus $status, OperationalRole $role, InspectionResponsibility $responsibility): array
    {
        $organization = Organization::factory()->create();
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => $status]);
        $actor = User::factory()->for($organization)->create(['operational_role' => $role]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => $responsibility]);

        return [$inspection, $actor];
    }
}
