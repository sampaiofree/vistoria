<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionReportMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_report_metadata_and_members_can_only_view_it(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $member = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);
        InspectionResponsible::factory()->forInspection($inspection, $member)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
                'report_date' => '2026-08-11',
                'service_order' => 'OS-42',
                'external_report_number' => '  REL-EXT-42  ',
                'report_designer' => '  PROJETISTA   III  ',
                'designer_i_report_number' => '  SM-IIE-1717  ',
                'first_page_text_template' => 'Equipamento: [nome do equipamento]',
                'confirm_revision_reorder' => false,
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame(EquipmentRevisionEmissionType::ForApproval, $inspection->emission_type);
        $this->assertSame('2026-08-11', $inspection->report_date?->toDateString());
        $this->assertSame('OS-42', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA III', $inspection->report_designer);
        $this->assertSame('SM-IIE-1717', $inspection->designer_i_report_number);
        $this->assertSame($admin->id, $inspection->updated_by);

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report_metadata.external_report_number', 'REL-EXT-42')
                ->where('report_metadata.report_designer', 'PROJETISTA III')
                ->where('report_metadata.designer_i_report_number', 'SM-IIE-1717')
                ->where('report_metadata.can_edit', true)
                ->where('report_metadata.can_edit_restricted_fields', true)
                ->where('capabilities.manage_report_metadata.action', route('inspections.report-metadata.update', $inspection)));

        $this->actingAs($member)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::Approved->value,
                'report_date' => '2026-08-12',
                'service_order' => 'OS-43',
                'external_report_number' => 'REL-EXT-43',
                'report_designer' => 'PROJETISTA IV',
                'designer_i_report_number' => 'SM-IIE-1718',
                'first_page_text_template' => 'Outro',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report_metadata.emission_type', EquipmentRevisionEmissionType::ForApproval->value)
                ->where('report_metadata.can_edit', false)
                ->where('report_metadata.can_edit_restricted_fields', false)
                ->has('emission_options'));
    }

    public function test_inspector_admin_can_edit_other_metadata_but_not_restricted_report_fields(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Inspector->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::AwaitingReview,
                'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
                'report_date' => '2026-08-11',
                'service_order' => 'OS-42',
                'external_report_number' => 'REL-EXT-42',
                'report_designer' => 'PROJETISTA II',
                'designer_i_report_number' => 'SM-IIE-1717',
                'first_page_text_template' => 'Texto original',
            ]);

        $this->actingAs($inspector)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report_metadata.can_edit', true)
                ->where('report_metadata.can_edit_restricted_fields', false));

        $unchangedRestrictedFields = [
            'emission_type' => EquipmentRevisionEmissionType::ForKnowledge->value,
            'report_date' => '2026-08-11',
            'service_order' => 'OS-42',
            'external_report_number' => 'REL-EXT-42',
        ];

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$unchangedRestrictedFields,
                'report_designer' => 'PROJETISTA III',
                'designer_i_report_number' => 'SM-IIE-1718',
                'first_page_text_template' => 'Texto atualizado',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame('OS-42', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA III', $inspection->report_designer);
        $this->assertSame('SM-IIE-1718', $inspection->designer_i_report_number);
        $this->assertSame('Texto atualizado', $inspection->first_page_text_template);

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
                'report_date' => '2026-08-12',
                'service_order' => 'OS-43',
                'external_report_number' => 'REL-EXT-43',
                'report_designer' => 'PROJETISTA IV',
                'designer_i_report_number' => 'SM-IIE-1719',
                'first_page_text_template' => 'Não deve persistir',
            ])
            ->assertSessionHasErrors([
                'emission_type',
                'report_date',
                'service_order',
                'external_report_number',
            ]);

        $inspection->refresh();
        $this->assertSame(EquipmentRevisionEmissionType::ForKnowledge, $inspection->emission_type);
        $this->assertSame('2026-08-11', $inspection->report_date?->toDateString());
        $this->assertSame('OS-42', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA III', $inspection->report_designer);
    }

    public function test_only_an_assigned_inspector_can_manage_an_inspection_in_progress(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector->value,
        ]);
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner->value,
        ]);
        $unassignedInspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InProgress,
                'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
                'report_date' => '2026-08-11',
                'service_order' => 'OS-42',
                'external_report_number' => 'REL-EXT-42',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $this->actingAs($inspector)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.manage_report_metadata.action', route('inspections.report-metadata.update', $inspection))
                ->where('capabilities.manage_general_aspects.action', route('inspections.general-aspects.update', $inspection))
                ->where('capabilities.assign_responsibles.action', route('inspections.responsibles.store', $inspection))
                ->where('capabilities.manage_references.action', route('inspections.reference-documents.update', $inspection))
                ->where('report_metadata.can_edit', true)
                ->where('report_metadata.can_edit_restricted_fields', false));

        $unchangedRestrictedFields = [
            'emission_type' => EquipmentRevisionEmissionType::ForKnowledge->value,
            'report_date' => '2026-08-11',
            'service_order' => 'OS-42',
            'external_report_number' => 'REL-EXT-42',
        ];

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$unchangedRestrictedFields,
                'report_designer' => 'PROJETISTA III',
                'designer_i_report_number' => 'SM-IIE-1718',
                'first_page_text_template' => 'Texto atualizado',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$unchangedRestrictedFields,
                'service_order' => 'OS-ALTERADA',
                'report_designer' => 'PROJETISTA IV',
                'first_page_text_template' => 'Texto atualizado',
            ])
            ->assertSessionHasErrors('service_order');

        foreach ([$admin, $unassignedInspector] as $user) {
            $this->actingAs($user)
                ->put(route('inspections.report-metadata.update', $inspection), [
                    ...$unchangedRestrictedFields,
                    'report_designer' => 'NÃO DEVE ATUALIZAR',
                ])
                ->assertForbidden();
        }

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.manage_report_metadata', false)
                ->where('capabilities.manage_general_aspects', false)
                ->where('capabilities.assign_responsibles', false)
                ->where('capabilities.manage_references', false));

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [])
            ->assertForbidden();
    }

    public function test_external_report_number_is_optional_but_limited_to_150_characters(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::AwaitingReview,
                'external_report_number' => 'REL-ORIGINAL',
            ]);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => null,
                'report_date' => null,
                'service_order' => null,
                'external_report_number' => str_repeat('X', 151),
                'first_page_text_template' => null,
            ])
            ->assertSessionHasErrors('external_report_number');

        $this->assertSame('REL-ORIGINAL', $inspection->fresh()->external_report_number);
    }

    public function test_report_designer_defaults_to_projetista_two_and_is_required_when_sent(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);

        $this->assertSame('PROJETISTA II', $inspection->report_designer);

        $basePayload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => '2026-08-15',
            'service_order' => 'OS-COMPAT',
            'external_report_number' => null,
            'first_page_text_template' => 'Relatório de inspeção',
        ];

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), $basePayload)
            ->assertStatus(302);

        $this->assertSame('OS-COMPAT', $inspection->fresh()->service_order);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'report_designer' => '   ',
            ])
            ->assertSessionHasErrors('report_designer');

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'report_designer' => str_repeat('P', 101),
            ])
            ->assertSessionHasErrors('report_designer');

        $this->assertSame('PROJETISTA II', $inspection->fresh()->report_designer);
    }

    public function test_designer_i_report_number_is_optional_normalized_and_limited_to_100_characters(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);
        $basePayload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => '2026-08-15',
            'service_order' => 'OS-42',
            'external_report_number' => 'U0306VT-G-6RI002',
            'report_designer' => 'PROJETISTA II',
            'first_page_text_template' => 'Relatório de inspeção',
        ];

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'designer_i_report_number' => '  SM-IIE-1717  ',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $this->assertSame('SM-IIE-1717', $inspection->fresh()->designer_i_report_number);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'designer_i_report_number' => '   ',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $this->assertNull($inspection->fresh()->designer_i_report_number);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'designer_i_report_number' => str_repeat('X', 101),
            ])
            ->assertSessionHasErrors('designer_i_report_number');

        $this->assertNull($inspection->fresh()->designer_i_report_number);
    }
}
