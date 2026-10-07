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

    public function test_inspector_must_fill_report_equipment_and_it_appears_in_m2_and_report_preview(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create(['name' => 'Nome do cadastro']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        $url = route('inspections.report-metadata.update', $inspection);
        $payload = [
            'emission_type' => EquipmentRevisionEmissionType::ForKnowledge->value,
            'report_date' => null,
            'service_order' => null,
            'external_report_number' => null,
            'first_page_text_template' => 'Título do relatório',
        ];

        $this->assertNull($inspection->report_equipment_name);
        $this->actingAs($inspector)->put($url, [...$payload, 'report_equipment_name' => '   '])
            ->assertSessionHasErrors('report_equipment_name');
        $this->actingAs($inspector)->put($url, [...$payload, 'report_equipment_name' => str_repeat('X', 151)])
            ->assertSessionHasErrors('report_equipment_name');
        $this->assertNull($inspection->fresh()->report_equipment_name);

        $this->actingAs($inspector)->put($url, [...$payload, 'report_equipment_name' => '  Bomba de alimentação  '])
            ->assertSessionHasNoErrors();
        $this->assertSame('Bomba de alimentação', $inspection->fresh()->report_equipment_name);

        $this->actingAs($inspector)->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('report_metadata.report_equipment_name', 'Bomba de alimentação'));
        $this->actingAs($inspector)->get(route('inspections.classifications', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('content.classification_summary.header.equipment', 'Bomba de alimentação'));
        $this->actingAs($inspector)->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('content.classification_summary.header.equipment', 'Bomba de alimentação'));
    }

    public function test_admin_can_update_report_metadata_and_members_can_only_view_it(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $member = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $member)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
                'report_date' => '2026-08-11',
                'service_order' => '0000000001',
                'external_report_number' => '  REL-EXT-42  ',
                'designer_i_report_number' => '  SM-IIE-1717  ',
                'first_page_text_template' => 'Equipamento: Bomba de alimentação',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame(EquipmentRevisionEmissionType::ForApproval, $inspection->emission_type);
        $this->assertSame('2026-08-11', $inspection->report_date?->toDateString());
        $this->assertSame('0000000001', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA II', $inspection->report_designer);
        $this->assertSame('SM-IIE-1717', $inspection->designer_i_report_number);
        $this->assertSame('Bomba de alimentação', $inspection->report_equipment_name);
        $this->assertSame($admin->id, $inspection->updated_by);

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report_metadata.external_report_number', 'REL-EXT-42')
                ->where('report_metadata.report_designer', 'PROJETISTA II')
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
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector->value]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InProgress,
                'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
                'report_date' => '2026-08-11',
                'service_order' => 'OS-42',
                'external_report_number' => 'REL-EXT-42',
                'report_designer' => 'PROJETISTA III',
                'designer_i_report_number' => 'SM-IIE-1717',
                'first_page_text_template' => 'Texto original',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Preparer,
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
                'first_page_text_template' => 'Texto atualizado',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame('OS-42', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA III', $inspection->report_designer);
        $this->assertSame('SM-IIE-1717', $inspection->designer_i_report_number);
        $this->assertSame('Texto atualizado', $inspection->first_page_text_template);

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
                'report_date' => '2026-08-12',
                'service_order' => '0000000002',
                'external_report_number' => 'REL-EXT-43',
                'first_page_text_template' => 'Não deve persistir',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertSessionHasErrors([
                'emission_type',
                'report_date',
                'service_order',
                'external_report_number',
            ]);

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$unchangedRestrictedFields,
                'designer_i_report_number' => 'SM-IIE-1718',
                'first_page_text_template' => 'Não deve persistir',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertSessionHasErrors('designer_i_report_number');

        $inspection->refresh();
        $this->assertSame(EquipmentRevisionEmissionType::ForKnowledge, $inspection->emission_type);
        $this->assertSame('2026-08-11', $inspection->report_date?->toDateString());
        $this->assertSame('OS-42', $inspection->service_order);
        $this->assertSame('REL-EXT-42', $inspection->external_report_number);
        $this->assertSame('PROJETISTA III', $inspection->report_designer);
        $this->assertSame('SM-IIE-1717', $inspection->designer_i_report_number);
    }

    public function test_assigned_inspector_can_update_the_first_page_title_when_report_date_is_empty(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InProgress,
                'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
                'report_date' => null,
                'service_order' => 'OS-42',
                'first_page_text_template' => 'Texto original',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForKnowledge->value,
                'report_date' => null,
                'service_order' => 'OS-42',
                'external_report_number' => null,
                'first_page_text_template' => 'Texto atualizado',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertNull($inspection->report_date);
        $this->assertSame('Texto atualizado', $inspection->first_page_text_template);
    }

    public function test_assigned_reviewer_can_save_report_metadata_without_a_report_date(): void
    {
        $organization = Organization::factory()->create();
        $reviewer = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InReview,
                'report_date' => null,
                'designer_i_report_number' => 'SM-IIE-1717',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);

        $this->actingAs($reviewer)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
                'report_date' => null,
                'service_order' => '0000000001',
                'external_report_number' => null,
                'designer_i_report_number' => 'SM-IIE-1717',
                'first_page_text_template' => 'Texto revisado',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertNull($inspection->report_date);
        $this->assertSame('Texto revisado', $inspection->first_page_text_template);
    }

    public function test_assigned_reviewer_can_edit_report_metadata_during_review_but_other_users_cannot(): void
    {
        $organization = Organization::factory()->create();
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $unassigned = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);

        $payload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => '2026-09-21',
            'service_order' => '0000000002',
            'external_report_number' => 'REL-REVISADO',
            'designer_i_report_number' => 'SM-IIE-1718',
            'first_page_text_template' => 'Conteúdo revisado',
            'report_equipment_name' => 'Bomba de alimentação',
        ];

        $this->actingAs($reviewer)
            ->put(route('inspections.report-metadata.update', $inspection), $payload)
            ->assertRedirect(route('inspections.show', $inspection));
        $this->assertSame('Bomba de alimentação', $inspection->fresh()->report_equipment_name);

        $this->actingAs($reviewer)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report_metadata.can_edit', true)
                ->where('report_metadata.can_edit_restricted_fields', true)
                ->where('capabilities.manage_report_metadata.action', route('inspections.report-metadata.update', $inspection))
                ->where('capabilities.update_report_revision.action', route('inspections.report-revision.update', $inspection))
                ->where('capabilities.assign_responsibles', false));

        $this->actingAs($unassigned)
            ->put(route('inspections.report-metadata.update', $inspection), $payload)
            ->assertForbidden();
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
                'first_page_text_template' => 'Texto atualizado',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $this->actingAs($inspector)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$unchangedRestrictedFields,
                'service_order' => '0000000002',
                'first_page_text_template' => 'Texto atualizado',
                'report_equipment_name' => 'Bomba de alimentação',
            ])
            ->assertSessionHasErrors('service_order');

        foreach ([$admin, $unassignedInspector] as $user) {
            $this->actingAs($user)
                ->put(route('inspections.report-metadata.update', $inspection), [
                    ...$unchangedRestrictedFields,
                ])
                ->assertForbidden();
        }

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.manage_report_metadata', false)
                ->where('capabilities.manage_general_aspects', false)
                ->where('capabilities.assign_responsibles.action', route('inspections.responsibles.store', $inspection)));

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [])
            ->assertForbidden();
    }

    public function test_assigned_company_admin_reviewer_can_edit_report_content_but_releaser_cannot(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $releaser = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Releaser,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $releaser)->create([
            'responsibility' => InspectionResponsibility::Releaser,
        ]);

        $payload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => null,
            'service_order' => '0000000003',
            'external_report_number' => null,
            'designer_i_report_number' => 'SM-IIE-1717',
            'first_page_text_template' => 'Conteúdo do Revisor administrador',
            'report_equipment_name' => 'Bomba de alimentação',
        ];

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame('Conteúdo do Revisor administrador', $inspection->fresh()->first_page_text_template);

        $inspection->update(['status' => InspectionStatus::AwaitingRelease]);
        $this->actingAs($releaser)
            ->put(route('inspections.report-metadata.update', $inspection), $payload)
            ->assertForbidden();
        $this->actingAs($releaser)
            ->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 1,
                'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            ])
            ->assertForbidden();
    }

    public function test_external_report_number_is_optional_but_limited_to_150_characters(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InReview,
                'external_report_number' => 'REL-ORIGINAL',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver]);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => null,
                'report_date' => null,
                'service_order' => null,
                'external_report_number' => str_repeat('X', 151),
                'designer_i_report_number' => 'SM-IIE-1717',
                'first_page_text_template' => null,
            ])
            ->assertSessionHasErrors('external_report_number');

        $this->assertSame('REL-ORIGINAL', $inspection->fresh()->external_report_number);
    }

    public function test_reviewer_must_provide_a_designer_i_number_within_the_database_limit(): void
    {
        $organization = Organization::factory()->create();
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InReview,
                'designer_i_report_number' => 'SM-IIE-1717',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);

        $payload = [
            'emission_type' => null,
            'report_date' => null,
            'service_order' => null,
            'external_report_number' => null,
            'first_page_text_template' => null,
        ];

        $this->actingAs($reviewer)
            ->put(route('inspections.report-metadata.update', $inspection), $payload)
            ->assertSessionHasErrors('designer_i_report_number');

        $this->actingAs($reviewer)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$payload,
                'designer_i_report_number' => '   ',
            ])
            ->assertSessionHasErrors('designer_i_report_number');

        $this->actingAs($reviewer)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$payload,
                'designer_i_report_number' => str_repeat('X', 101),
            ])
            ->assertSessionHasErrors('designer_i_report_number');

        $this->assertSame('SM-IIE-1717', $inspection->fresh()->designer_i_report_number);
    }

    public function test_reviewer_can_update_designer_i_number_but_report_designer_remains_prohibited(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InReview,
                'report_designer' => 'PROJETISTA II',
                'designer_i_report_number' => 'SM-IIE-1717',
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver]);

        $basePayload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => '2026-08-15',
            'service_order' => '0000000001',
            'external_report_number' => 'U0306VT-G-6RI002',
            'designer_i_report_number' => 'SM-IIE-1718',
            'first_page_text_template' => 'Relatório de inspeção',
            'report_equipment_name' => 'Bomba de alimentação',
        ];

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                ...$basePayload,
                'report_designer' => 'PROJETISTA III',
            ])
            ->assertSessionHasErrors('report_designer');

        $inspection->refresh();
        $this->assertSame('PROJETISTA II', $inspection->report_designer);
        $this->assertSame('SM-IIE-1717', $inspection->designer_i_report_number);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), $basePayload)
            ->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();
        $this->assertSame('PROJETISTA II', $inspection->report_designer);
        $this->assertSame('SM-IIE-1718', $inspection->designer_i_report_number);
    }
}
