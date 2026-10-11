<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Inspections\TransitionInspection;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\EquipmentRevisionChronology;
use App\Services\Reports\ReportResponsibleNames;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ReportResponsibleNamesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('missingCoverNames')]
    public function test_release_rejects_each_missing_cover_name_in_both_modalities(bool $isM3, string $missingRole, string $missingLabel): void
    {
        $attributes = [
            'report_verifier_name' => 'Verificador padrão',
            'report_reviewer_name' => 'Revisor padrão',
            'report_releaser_name' => 'Liberador padrão',
            'report_m3_verifier_name' => 'Verificador M3',
            'report_m3_reviewer_name' => 'Revisor M3',
            'report_m3_releaser_name' => 'Liberador M3',
        ];
        if ($missingRole !== 'preparer') {
            $field = ($isM3 ? 'report_m3_' : 'report_').[
                'reviewer' => 'verifier_name',
                'approver' => 'reviewer_name',
                'releaser' => 'releaser_name',
            ][$missingRole];
            $attributes[$field] = '   ';
        }
        $organization = Organization::factory()->create($attributes);
        $actor = User::factory()->for($organization)->create(['name' => 'Inspetor vinculado']);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create([
            'status' => InspectionStatus::AwaitingRelease,
            'is_m3' => $isM3,
        ]);
        if ($missingRole !== 'preparer') {
            InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
                'responsibility' => InspectionResponsibility::Reviewer,
                'is_primary' => true,
            ]);
        }

        app(TenantContext::class)->set($organization);
        try {
            app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], InspectionStatus::Released);
            $this->fail('A liberação sem '.$missingLabel.' deveria falhar.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($missingLabel, $exception->errors()['inspection'][0]);
        } finally {
            app(TenantContext::class)->clear();
        }

        $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);
        $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
        $this->assertSame(0, $inspection->statusHistories()->count());
    }

    #[DataProvider('modalities')]
    public function test_open_report_requires_names_for_export_but_legacy_finalized_report_remains_exportable(bool $isM3): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create([
            'status' => InspectionStatus::InProgress,
            'is_m3' => $isM3,
            'external_report_number' => 'REL-TESTE',
            'designer_i_report_number' => 'PROJ-TESTE',
        ]);
        $block = InspectionOverviewBlock::factory()->forInspection($inspection, 1)->create([
            'comment' => 'Vista geral',
            'recommendation' => 'Acompanhar',
        ]);
        foreach ([1, 2] as $slot) {
            InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create();
        }

        $preview = route('inspections.report-preview', $inspection);
        $this->actingAs($admin)->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('content.print_enabled', false)
            ->where('content.export_disabled_reason', fn ($reason): bool => str_contains((string) $reason, 'Preparado')
                && str_contains((string) $reason, 'Verificado, Aprovado, Liberado')));

        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);
        $prefix = $isM3 ? 'report_m3_' : 'report_';
        $organization->update([
            $prefix.'verifier_name' => 'Verificador documental',
            $prefix.'reviewer_name' => 'Revisor documental',
            $prefix.'releaser_name' => 'Liberador documental',
        ]);
        $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('content.print_enabled', true));

        $inspection->update(['status' => InspectionStatus::Released, 'report_responsibles_snapshot' => null]);
        $inspection->responsibles()->delete();
        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.0.name', null)
            ->where('content.cover.approval_flow.1.name', null)
            ->where('content.print_enabled', true));
    }

    public static function missingCoverNames(): array
    {
        $cases = [];
        foreach (['preparer' => 'Preparado', 'reviewer' => 'Verificado', 'approver' => 'Aprovado', 'releaser' => 'Liberado'] as $role => $label) {
            $cases['padrão '.$role] = [false, $role, $label];
            $cases['M3 '.$role] = [true, $role, $label];
        }

        return $cases;
    }

    public static function modalities(): array
    {
        return ['padrão' => [false], 'M3' => [true]];
    }

    public function test_release_validation_checks_the_name_as_displayed_on_the_cover(): void
    {
        $missing = app(ReportResponsibleNames::class)->missingCoverLabels([
            'preparer' => 'Inspetor',
            'reviewer' => '— Identificação profissional',
            'approver' => 'Revisor',
            'releaser' => 'Liberador',
        ]);

        $this->assertSame(['Verificado'], $missing);
    }

    public function test_open_reports_use_company_names_in_cover_and_revisions_instead_of_assigned_users(): void
    {
        $organization = Organization::factory()->create([
            'report_verifier_name' => 'Ana Costa',
            'report_reviewer_name' => 'João da Silva',
            'report_releaser_name' => 'Lúcia Gonçalves',
        ]);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $planner = User::factory()->for($organization)->create(['name' => 'Paulo Planejador']);
        $inspector = User::factory()->for($organization)->create(['name' => 'Iara Inspetora — Técnica']);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create();
        InspectionResponsible::factory()->forInspection($inspection, $planner)->create(['responsibility' => InspectionResponsibility::Preparer, 'is_primary' => true]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create(['responsibility' => InspectionResponsibility::Reviewer, 'is_primary' => true]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Approver, 'is_primary' => true]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => InspectionResponsibility::Releaser, 'is_primary' => true]);
        $url = route('inspections.report-preview', $inspection);
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.0.name', 'Iara Inspetora')
            ->where('content.cover.approval_flow.1.name', 'Ana Costa')
            ->where('content.cover.approval_flow.2.name', 'João da Silva')
            ->where('content.cover.approval_flow.3.name', 'Lúcia Gonçalves')
            ->where('content.cover.revision_history.0.full_responsibles.preparer', 'Iara Inspetora')
            ->where('content.cover.revision_history.0.compact_responsibles.preparer', 'II')
            ->where('content.cover.revision_history.0.full_responsibles.reviewer', 'Ana Costa')
            ->where('content.cover.revision_history.0.compact_responsibles.reviewer', 'AC')
            ->where('content.cover.revision_history.0.full_responsibles.approver', 'João da Silva')
            ->where('content.cover.revision_history.0.compact_responsibles.approver', 'JDS')
            ->where('content.cover.revision_history.0.compact_responsibles.releaser', 'LG'));
        $admin->update(['name' => 'Usuário renomeado']);
        $inspection->responsibles()->where('responsibility', 'approver')->delete();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('content.cover.approval_flow.2.name', 'João da Silva'));
        $organization->update(['report_verifier_name' => 'Nova Verificadora', 'report_reviewer_name' => 'Nova Revisora', 'report_releaser_name' => null]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.0.name', 'Iara Inspetora')
            ->where('content.cover.approval_flow.1.name', 'Nova Verificadora')
            ->where('content.cover.approval_flow.2.name', 'Nova Revisora')
            ->where('content.cover.approval_flow.3.name', null)
            ->where('content.cover.revision_history.0.compact_responsibles.releaser', null));
        $inspection->responsibles()->where('responsibility', 'reviewer')->delete();
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.0.name', null)
            ->where('content.cover.approval_flow.1.name', 'Nova Verificadora')
            ->where('content.cover.revision_history.0.compact_responsibles.preparer', null)
            ->where('content.validation.issues', fn ($issues): bool => collect($issues)
                ->contains('Vincule um Inspetor à inspeção para preencher Preparado.')));
        $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
    }

    public function test_m3_report_uses_its_own_names_and_preserves_each_previous_revisions_modality(): void
    {
        $organization = Organization::factory()->create([
            'report_verifier_name' => 'Verificador padrão',
            'report_reviewer_name' => 'Revisor padrão',
            'report_releaser_name' => 'Liberador padrão',
            'report_m3_verifier_name' => 'Verificador M3',
            'report_m3_reviewer_name' => 'Revisor M3',
            'report_m3_releaser_name' => 'Liberador M3',
        ]);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $inspector = User::factory()->for($organization)->create(['name' => 'Inspetora de Campo']);
        $equipment = Equipment::factory()->for($organization)->create();
        $common = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'is_m3' => false,
            'report_revision' => 0,
            'report_responsibles_snapshot' => [
                'reviewer' => 'Verificador padrão antigo',
                'approver' => 'Revisor padrão antigo',
                'releaser' => 'Liberador padrão antigo',
            ],
        ]);
        $previousM3 = Inspection::factory()->forEquipment($equipment, $common)->create([
            'status' => InspectionStatus::Released,
            'is_m3' => true,
            'report_revision' => 1,
            'report_responsibles_snapshot' => [
                'reviewer' => 'Verificador M3 antigo',
                'approver' => 'Revisor M3 antigo',
                'releaser' => 'Liberador M3 antigo',
            ],
        ]);
        $current = Inspection::factory()->forEquipment($equipment, $previousM3)->create([
            'is_m3' => true,
            'report_revision' => 2,
        ]);
        InspectionResponsible::factory()->forInspection($current, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->get(route('inspections.report-preview', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.cover.approval_flow.0.name', 'Inspetora de Campo')
                ->where('content.cover.approval_flow.1.name', 'Verificador M3')
                ->where('content.cover.approval_flow.2.name', 'Revisor M3')
                ->where('content.cover.approval_flow.3.name', 'Liberador M3')
                ->where('content.cover.revision_history.0.full_responsibles.reviewer', 'Verificador M3')
                ->where('content.cover.revision_history.1.full_responsibles.reviewer', 'Verificador M3 antigo')
                ->where('content.cover.revision_history.2.full_responsibles.reviewer', 'Verificador padrão antigo'));

        $organization->update(['report_m3_verifier_name' => null]);
        $this->assertNull(app(ReportResponsibleNames::class)->forInspection($current->fresh())['reviewer']);
        $this->assertSame('Verificador padrão', app(ReportResponsibleNames::class)->forOrganization($organization->id)['reviewer']);
        $this->assertSame('Verificador M3 antigo', app(ReportResponsibleNames::class)->forInspection($previousM3->fresh())['reviewer']);
        $this->get(route('inspections.report-preview', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.cover.approval_flow.1.name', null)
                ->where('content.cover.revision_history.1.full_responsibles.reviewer', 'Verificador M3 antigo')
                ->where('content.cover.revision_history.2.full_responsibles.reviewer', 'Verificador padrão antigo')
                ->where('content.export_disabled_reason', fn ($reason): bool => str_contains((string) $reason, 'Verificado')));
    }

    #[DataProvider('finalizations')]
    public function test_m3_finalization_captures_its_own_names(InspectionStatus $target, bool $configured): void
    {
        $organization = Organization::factory()->create([
            'report_verifier_name' => 'Verificador padrão',
            'report_reviewer_name' => 'Revisor padrão',
            'report_releaser_name' => 'Liberador padrão',
            'report_m3_verifier_name' => $configured ? 'Verificador M3' : null,
            'report_m3_reviewer_name' => $configured ? 'Revisor M3' : null,
            'report_m3_releaser_name' => $configured ? 'Liberador M3' : null,
        ]);
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create([
            'organization_id' => $organization->id,
            'status' => InspectionStatus::AwaitingRelease,
            'is_m3' => true,
        ]);
        if ($target === InspectionStatus::Released) {
            InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
                'responsibility' => InspectionResponsibility::Reviewer,
                'is_primary' => true,
            ]);
        }
        app(TenantContext::class)->set($organization);
        try {
            if ($target === InspectionStatus::Released && ! $configured) {
                try {
                    app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], $target);
                    $this->fail('A liberação sem nomes M3 deveria falhar.');
                } catch (ValidationException $exception) {
                    $this->assertStringContainsString('Verificado, Aprovado, Liberado', $exception->errors()['inspection'][0]);
                    $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);
                    $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
                }

                return;
            }
            app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], $target);
        } finally {
            app(TenantContext::class)->clear();
        }
        $expected = [
            'reviewer' => $configured ? 'Verificador M3' : null,
            'approver' => $configured ? 'Revisor M3' : null,
            'releaser' => $configured ? 'Liberador M3' : null,
        ];
        $this->assertSame($expected, $inspection->fresh()->report_responsibles_snapshot);

        $organization->update([
            'report_m3_verifier_name' => 'Novo verificador',
            'report_m3_reviewer_name' => 'Novo revisor',
            'report_m3_releaser_name' => 'Novo liberador',
        ]);
        $this->assertSame($expected, app(ReportResponsibleNames::class)->forInspection($inspection->fresh()));
    }

    #[DataProvider('finalizations')]
    public function test_finalization_captures_names_including_empty_values(InspectionStatus $target, bool $configured): void
    {
        $organization = Organization::factory()->create([
            'report_verifier_name' => $configured ? 'Verificadora documental' : null,
            'report_reviewer_name' => $configured ? 'Revisora documental' : null,
            'report_releaser_name' => $configured ? 'Liberador documental' : null,
        ]);
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingRelease]);
        if ($target === InspectionStatus::Released) {
            InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
                'responsibility' => InspectionResponsibility::Reviewer,
                'is_primary' => true,
            ]);
        }
        $expected = [
            'reviewer' => $organization->report_verifier_name,
            'approver' => $organization->report_reviewer_name,
            'releaser' => $organization->report_releaser_name,
        ];
        app(TenantContext::class)->set($organization);
        try {
            if ($target === InspectionStatus::Released && ! $configured) {
                try {
                    app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], $target);
                    $this->fail('A liberação sem nomes documentais deveria falhar.');
                } catch (ValidationException $exception) {
                    $this->assertStringContainsString('Verificado, Aprovado, Liberado', $exception->errors()['inspection'][0]);
                    $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);
                    $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
                }

                return;
            }
            app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], $target);
        } finally {
            app(TenantContext::class)->clear();
        }
        $this->assertSame($expected, $inspection->fresh()->report_responsibles_snapshot);
        $this->assertSame($target, $inspection->fresh()->status);
        $this->assertSame($actor->id, $inspection->statusHistories()->sole()->changed_by);
        $organization->update(['report_verifier_name' => 'Verificadora futura', 'report_reviewer_name' => 'Nome futuro', 'report_releaser_name' => 'Outro nome']);
        $actor->update(['name' => 'Usuário renomeado']);
        $this->assertSame($expected, app(ReportResponsibleNames::class)->forInspection($inspection->fresh()));
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($admin)->get(route('inspections.report-preview', $inspection))->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.1.name', $expected['reviewer'])
            ->where('content.cover.approval_flow.2.name', $expected['approver'])
            ->where('content.cover.approval_flow.3.name', $expected['releaser']));
    }

    public function test_legacy_migration_preserves_finalized_names_and_previous_revisions_without_changing_timestamps(): void
    {
        $migration = require database_path('migrations/2026_09_27_000066_add_report_responsible_names.php');
        $migration->down();
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $reviewer = User::factory()->for($organization)->create(['name' => 'Revisora Antiga — Engenheira']);
        $releaser = User::factory()->for($organization)->create(['name' => 'Liberador Antigo']);
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released, 'report_revision' => 0]);
        $canceled = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Canceled]);
        $open = Inspection::factory()->forEquipment($equipment)->create(['report_revision' => 1]);
        foreach ([$released, $canceled, $open] as $inspection) {
            InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create(['responsibility' => InspectionResponsibility::Approver]);
        }
        InspectionResponsible::factory()->forInspection($released, $releaser)->create(['responsibility' => InspectionResponsibility::Releaser]);
        $before = $released->fresh()->getRawOriginal();
        $migration->up();
        $this->assertSame(['approver' => $reviewer->name, 'releaser' => $releaser->name], $released->fresh()->report_responsibles_snapshot);
        $this->assertSame(['approver' => $reviewer->name, 'releaser' => null], $canceled->fresh()->report_responsibles_snapshot);
        $this->assertNull($open->fresh()->report_responsibles_snapshot);
        $after = $released->fresh()->getRawOriginal();
        unset($after['report_responsibles_snapshot']);
        $this->assertSame($before, $after);
        $organization->update(['report_reviewer_name' => 'Revisora Nova', 'report_releaser_name' => 'Liberadora Nova']);
        $reviewer->update(['name' => 'Conta renomeada']);
        $released->responsibles()->delete();
        $history = app(EquipmentRevisionChronology::class)->forInspectionReport($open->fresh());
        $this->assertSame('Revisora Nova', $history['rows'][0]['full_responsibles']['approver']);
        $this->assertSame('Revisora Antiga', $history['rows'][1]['full_responsibles']['approver']);
        $this->assertSame('RA', $history['rows'][1]['compact_responsibles']['approver']);
        $this->assertSame('Liberador Antigo', $history['rows'][1]['full_responsibles']['releaser']);
    }

    public function test_verifier_migration_preserves_names_already_shown_on_finalized_reports(): void
    {
        $migration = require database_path('migrations/2026_10_02_000068_add_report_verifier_name.php');
        $migration->down();

        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspector = User::factory()->for($organization)->create(['name' => 'Inspetora Antiga — Técnica']);
        $released = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'report_revision' => 0,
            'report_responsibles_snapshot' => ['approver' => 'Revisor Antigo', 'releaser' => 'Liberador Antigo'],
        ]);
        $canceled = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Canceled,
            'report_responsibles_snapshot' => ['approver' => null, 'releaser' => null],
        ]);
        $open = Inspection::factory()->forEquipment($equipment)->create(['report_revision' => 1]);
        InspectionResponsible::factory()->forInspection($released, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);
        $before = $released->fresh()->getRawOriginal();

        $migration->up();

        $this->assertSame([
            'approver' => 'Revisor Antigo',
            'releaser' => 'Liberador Antigo',
            'reviewer' => 'Inspetora Antiga — Técnica',
        ], $released->fresh()->report_responsibles_snapshot);
        $this->assertSame([
            'approver' => null,
            'releaser' => null,
            'reviewer' => null,
        ], $canceled->fresh()->report_responsibles_snapshot);
        $this->assertNull($open->fresh()->report_responsibles_snapshot);
        $after = $released->fresh()->getRawOriginal();
        unset($before['report_responsibles_snapshot'], $after['report_responsibles_snapshot']);
        $this->assertSame($before, $after);

        $organization->update(['report_verifier_name' => 'Verificadora Nova']);
        $inspector->update(['name' => 'Inspetora Renomeada']);
        $history = app(EquipmentRevisionChronology::class)->forInspectionReport($open->fresh());
        $this->assertSame('Verificadora Nova', $history['rows'][0]['full_responsibles']['reviewer']);
        $this->assertSame('Inspetora Antiga', $history['rows'][1]['full_responsibles']['reviewer']);
        $this->assertSame('IA', $history['rows'][1]['compact_responsibles']['reviewer']);
        $this->assertSame('Inspetora Renomeada', $history['rows'][1]['full_responsibles']['preparer']);

        $migration->down();
        $this->assertSame([
            'approver' => 'Revisor Antigo',
            'releaser' => 'Liberador Antigo',
        ], $released->fresh()->report_responsibles_snapshot);
    }

    public function test_failed_finalization_rolls_back_names_and_status_together(): void
    {
        $organization = Organization::factory()->create([
            'report_verifier_name' => 'Verificador para captura',
            'report_reviewer_name' => 'Nome para captura',
            'report_releaser_name' => 'Liberador para captura',
        ]);
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingRelease]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);
        app(TenantContext::class)->set($organization);
        try {
            DB::transaction(function () use ($actor, $inspection): void {
                app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], InspectionStatus::Released);
                throw new \RuntimeException('Falha posterior na mesma transação.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha posterior na mesma transação.', $exception->getMessage());
        } finally {
            app(TenantContext::class)->clear();
        }
        $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);
        $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
        $this->assertSame(0, $inspection->statusHistories()->count());
    }

    public static function finalizations(): array
    {
        return [
            'release names' => [InspectionStatus::Released, true],
            'release empty' => [InspectionStatus::Released, false],
            'cancel names' => [InspectionStatus::Canceled, true],
            'cancel empty' => [InspectionStatus::Canceled, false],
        ];
    }
}
