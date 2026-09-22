<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\GeneralAspectsTemplate;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GeneralAspectsTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_update_and_delete_general_aspects_templates(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => '  Bomba em boas condições  ',
                'schema_version' => 1,
                'document' => $this->document('Descrição inicial'),
            ])
            ->assertRedirect(route('settings.inspection-report.general-aspects.index'));

        $template = GeneralAspectsTemplate::query()->firstOrFail();
        $this->assertSame($organization->id, $template->organization_id);
        $this->assertSame('Bomba em boas condições', $template->name);
        $this->assertSame('Descrição inicial', $template->document['content'][0]['content'][0]['text']);
        $this->assertSame($admin->id, $template->created_by);
        $this->assertSame($admin->id, $template->updated_by);

        $this->actingAs($admin)
            ->get(route('settings.inspection-report.general-aspects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/InspectionReports/GeneralAspectsTemplates/Index')
                ->where('templates.0.name', 'Bomba em boas condições'));

        $this->actingAs($admin)
            ->put(route('settings.inspection-report.general-aspects.update', $template), [
                'name' => 'Bomba revisada',
                'schema_version' => 1,
                'document' => $this->document('Descrição atualizada'),
            ])
            ->assertRedirect(route('settings.inspection-report.general-aspects.index'));

        $this->assertSame('Bomba revisada', $template->refresh()->name);
        $this->assertSame('Descrição atualizada', $template->document['content'][0]['content'][0]['text']);

        $this->actingAs($admin)
            ->delete(route('settings.inspection-report.general-aspects.destroy', $template))
            ->assertRedirect(route('settings.inspection-report.general-aspects.index'));

        $this->assertDatabaseMissing('general_aspects_templates', ['id' => $template->id]);
    }

    public function test_templates_require_an_admin_and_are_isolated_by_organization(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->for($organization)->create();
        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $template = GeneralAspectsTemplate::factory()->for($organization)->create();

        $this->actingAs($member)
            ->get(route('settings.inspection-report.general-aspects.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => 'Não permitido',
                'schema_version' => 1,
                'document' => $this->document('Conteúdo'),
            ])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->get(route('settings.inspection-report.general-aspects.edit', $template))
            ->assertNotFound();
    }

    public function test_template_requires_a_name_and_a_valid_non_empty_document(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => '',
                'schema_version' => 1,
                'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => 'Modelo sem conteúdo',
                'schema_version' => 1,
                'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('general_aspects_templates', 0);
    }

    public function test_template_allows_only_the_controlled_red_text_color(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => 'Modelo com preenchimento pendente',
                'schema_version' => 1,
                'document' => $this->documentWithRedText('Preencha a identificação do equipamento'),
            ])
            ->assertRedirect(route('settings.inspection-report.general-aspects.index'));

        $template = GeneralAspectsTemplate::query()->firstOrFail();
        $this->assertSame('#DC2626', $template->document['content'][0]['content'][0]['marks'][0]['attrs']['color']);

        $this->actingAs($admin)
            ->post(route('settings.inspection-report.general-aspects.store'), [
                'name' => 'Modelo com cor não permitida',
                'schema_version' => 1,
                'document' => $this->documentWithRedText('Texto inválido', '#2563EB'),
            ])
            ->assertSessionHasErrors('document');
    }

    public function test_editable_inspection_payload_includes_organization_templates_only(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        GeneralAspectsTemplate::factory()->for($organization)->create([
            'name' => 'Modelo disponível',
            'document' => $this->documentWithRedText('Conteúdo do modelo'),
        ]);
        GeneralAspectsTemplate::factory()->for($otherOrganization)->create([
            'name' => 'Modelo de outra empresa',
            'document' => $this->document('Não deve aparecer'),
        ]);

        $this->actingAs($inspector)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('general_aspects.can_edit', true)
                ->has('general_aspects.templates', 1)
                ->where('general_aspects.templates.0.name', 'Modelo disponível')
                ->where('general_aspects.templates.0.document.content.0.content.0.text', 'Conteúdo do modelo')
                ->where('general_aspects.templates.0.document.content.0.content.0.marks.0.attrs.color', '#DC2626'));

        $inspection->update(['status' => InspectionStatus::Planned]);

        $this->actingAs($inspector)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('general_aspects.can_edit', false)
                ->where('general_aspects.templates', []));
    }

    /** @return array<string, mixed> */
    private function document(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function documentWithRedText(string $text, string $color = '#DC2626'): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => $text,
                    'marks' => [['type' => 'textColor', 'attrs' => ['color' => $color]]],
                ]],
            ]],
        ];
    }
}
