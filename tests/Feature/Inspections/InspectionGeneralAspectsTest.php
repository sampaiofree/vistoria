<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\GeneralAspectsDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionGeneralAspectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_update_structured_general_aspects_in_any_status(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);

        $document = $this->document();
        foreach ([InspectionStatus::Released, InspectionStatus::Canceled] as $status) {
            $inspection->update(['status' => $status]);
            $workflowTimestamps = $inspection->only(['report_generated_at', 'released_at', 'canceled_at']);

            $this->actingAs($admin)
                ->put(route('inspections.general-aspects.update', $inspection), [
                    'schema_version' => 1,
                    'document' => $document,
                ])
                ->assertForbidden();

            $this->assertNull($inspection->refresh()->general_notes);
            $this->assertSame($workflowTimestamps, $inspection->only(['report_generated_at', 'released_at', 'canceled_at']));
        }

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 1,
                'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            ])
            ->assertForbidden();

        $this->assertNull($inspection->refresh()->general_notes);
    }

    public function test_saving_general_aspects_flattens_legacy_heading_levels(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver]);

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 1,
                'document' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'heading',
                            'attrs' => ['level' => 2],
                            'content' => [['type' => 'text', 'text' => 'Título legado 2']],
                        ],
                        [
                            'type' => 'heading',
                            'attrs' => ['level' => 3],
                            'content' => [['type' => 'text', 'text' => 'Título legado 3']],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $stored = json_decode((string) $inspection->refresh()->general_notes, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $stored['document']['content'][0]['attrs']['level']);
        $this->assertSame(1, $stored['document']['content'][1]['attrs']['level']);
    }

    public function test_members_super_admins_and_other_organizations_cannot_update_general_aspects(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->for($organization)->create();
        $otherAdmin = User::factory()->for(Organization::factory()->create())->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $superAdmin = User::factory()->superAdmin()->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);
        $payload = ['schema_version' => 1, 'document' => $this->document()];

        foreach ([$member, $otherAdmin, $superAdmin] as $actor) {
            $this->actingAs($actor)
                ->put(route('inspections.general-aspects.update', $inspection), $payload)
                ->assertForbidden();
        }

        $this->assertNull($inspection->refresh()->general_notes);
    }

    public function test_general_aspects_reject_invalid_schema_unsupported_content_and_limits(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver]);

        $invalidDocuments = [
            ['schema_version' => 2, 'document' => $this->document()],
            ['schema_version' => 1, 'document' => ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']]]]],
            ['schema_version' => 1, 'document' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', GeneralAspectsDocument::MAX_VISIBLE_CHARACTERS + 1)]],
            ]]]],
        ];

        foreach ($invalidDocuments as $payload) {
            $this->actingAs($admin)
                ->put(route('inspections.general-aspects.update', $inspection), $payload)
                ->assertSessionHasErrors($payload['schema_version'] === 2 ? 'schema_version' : 'document');
        }

        $this->assertNull($inspection->refresh()->general_notes);
    }

    public function test_general_aspects_cannot_be_saved_with_red_text(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver]);
        $redDocument = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Preencha este trecho',
                    'marks' => [['type' => 'textColor', 'attrs' => ['color' => '#DC2626']]],
                ]],
            ]],
        ];

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 1,
                'document' => $redDocument,
            ])
            ->assertSessionHasErrors([
                'document' => 'Conclua todos os trechos destacados em vermelho antes de salvar os aspectos gerais.',
            ]);

        $this->assertNull($inspection->refresh()->general_notes);

        $redDocument['content'][0]['content'][0]['marks'] = [];

        $this->actingAs($admin)
            ->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 1,
                'document' => $redDocument,
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $stored = json_decode((string) $inspection->refresh()->general_notes, true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('marks', $stored['document']['content'][0]['content'][0]);
    }

    public function test_overview_and_report_expose_the_structured_document_with_correct_permissions(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $member = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::Planned,
                'general_notes' => json_encode([
                    'schema_version' => 1,
                    'document' => $this->document(),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        InspectionResponsible::factory()->forInspection($inspection, $member)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('general_aspects.schema_version', 1)
                ->where('general_aspects.has_content', true)
                ->where('general_aspects.can_edit', false)
                ->where('general_aspects.update_url', null)
                ->where('general_aspects.document.content.0.content.0.text', 'Descrição técnica')
                ->where('capabilities.manage_general_aspects', false));

        $this->actingAs($member)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('general_aspects.has_content', true)
                ->where('general_aspects.can_edit', false)
                ->where('general_aspects.update_url', null)
                ->where('capabilities.manage_general_aspects', false));

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'report')
                ->where('content.general_aspects.schema_version', 1)
                ->where('content.general_aspects.document.content.0.content.0.text', 'Descrição técnica'));
    }

    public function test_planning_update_no_longer_overwrites_general_aspects(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner,
        ]);
        $stored = json_encode([
            'schema_version' => 1,
            'document' => $this->document(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['general_notes' => $stored]);
        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create(['responsibility' => InspectionResponsibility::Preparer, 'is_primary' => true]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        $this->actingAs($admin)
            ->put(route('inspections.update', $inspection), [
                'equipment_id' => $inspection->equipment_id,
                'inspector_id' => $inspector->id,
                'planned_start_on' => '2026-08-20',
                'planned_end_on' => '2026-08-20',
                'general_notes' => 'Tentativa de sobrescrita pelo formulário antigo',
            ])
            ->assertRedirect(route('inspections.show', $inspection));

        $this->assertSame($stored, $inspection->refresh()->general_notes);
    }

    public function test_migration_converts_multiline_plain_notes_to_structured_content(): void
    {
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->create())
            ->create(['general_notes' => "Primeira linha\n\nTerceira linha"]);

        $migration = require database_path('migrations/2026_08_13_000023_upgrade_general_notes_to_general_aspects.php');
        $migration->up();

        $stored = json_decode((string) DB::table('inspections')->where('id', $inspection->id)->value('general_notes'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Primeira linha', $stored['document']['content'][0]['content'][0]['text']);
        $this->assertSame(['type' => 'paragraph'], $stored['document']['content'][1]);
        $this->assertSame('Terceira linha', $stored['document']['content'][2]['content'][0]['text']);
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => [
                        'level' => 1,
                        'textAlign' => 'center',
                        'lineHeight' => 1.5,
                        'spaceBefore' => 4,
                        'spaceAfter' => 8,
                        'indent' => 0,
                    ],
                    'content' => [['type' => 'text', 'text' => 'Descrição técnica', 'marks' => [['type' => 'bold']]]],
                ],
                [
                    'type' => 'orderedList',
                    'attrs' => ['start' => 1],
                    'content' => [[
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Primeiro item', 'marks' => [['type' => 'italic']]]],
                        ]],
                    ]],
                ],
            ],
        ];
    }
}
