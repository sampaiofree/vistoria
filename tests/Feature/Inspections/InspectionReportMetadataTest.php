<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
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
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create();
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
                ->has('emission_options'));
    }

    public function test_report_generation_requires_emission_and_preserves_report_date(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::Approved,
                'report_date' => '2026-01-15',
            ]);

        foreach (InspectionResponsibility::cases() as $responsibility) {
            InspectionResponsible::factory()->forInspection($inspection, $admin)->primary()->create([
                'responsibility' => $responsibility,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('inspections.generate-report', $inspection))
            ->assertSessionHasErrors('emission_type');

        $inspection->update(['emission_type' => EquipmentRevisionEmissionType::Approved]);

        $this->actingAs($admin)
            ->post(route('inspections.generate-report', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::ReportGenerated, $inspection->status);
        $this->assertSame('2026-01-15', $inspection->report_date?->toDateString());
    }

    public function test_generated_metadata_cannot_clear_emission_or_date(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::ReportGenerated,
                'report_generated_at' => now(),
                'report_date' => '2026-01-15',
                'emission_type' => EquipmentRevisionEmissionType::Approved,
            ]);

        $this->actingAs($admin)
            ->put(route('inspections.report-metadata.update', $inspection), [
                'emission_type' => null,
                'report_date' => null,
                'service_order' => null,
                'first_page_text_template' => null,
            ])
            ->assertSessionHasErrors(['emission_type', 'report_date']);
    }

    public function test_external_report_number_is_optional_but_limited_to_150_characters(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['external_report_number' => 'REL-ORIGINAL']);

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
            ->create();

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
            ->create();
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
