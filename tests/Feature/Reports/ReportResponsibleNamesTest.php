<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Inspections\TransitionInspection;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\EquipmentRevisionChronology;
use App\Services\Reports\ReportResponsibleNames;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ReportResponsibleNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_reports_use_company_names_in_cover_and_revisions_instead_of_assigned_users(): void
    {
        $organization = Organization::factory()->create(['report_reviewer_name' => 'João da Silva', 'report_releaser_name' => 'Lúcia Gonçalves']);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create();
        foreach ([InspectionResponsibility::Approver, InspectionResponsibility::Releaser, InspectionResponsibility::Preparer] as $role) {
            InspectionResponsible::factory()->forInspection($inspection, $admin)->create(['responsibility' => $role, 'is_primary' => true]);
        }
        $url = route('inspections.report-preview', $inspection);
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.0.name', $admin->name)
            ->where('content.cover.approval_flow.2.name', 'João da Silva')
            ->where('content.cover.approval_flow.3.name', 'Lúcia Gonçalves')
            ->where('content.cover.revision_history.0.full_responsibles.approver', 'João da Silva')
            ->where('content.cover.revision_history.0.compact_responsibles.approver', 'JDS')
            ->where('content.cover.revision_history.0.compact_responsibles.releaser', 'LG'));
        $admin->update(['name' => 'Usuário renomeado']);
        $inspection->responsibles()->where('responsibility', 'approver')->delete();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('content.cover.approval_flow.2.name', 'João da Silva'));
        $organization->update(['report_reviewer_name' => 'Nova Revisora', 'report_releaser_name' => null]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('content.cover.approval_flow.2.name', 'Nova Revisora')->where('content.cover.approval_flow.3.name', null)
            ->where('content.cover.revision_history.0.compact_responsibles.releaser', null));
        $this->assertNull($inspection->fresh()->report_responsibles_snapshot);
    }

    #[DataProvider('finalizations')]
    public function test_finalization_captures_names_including_empty_values(InspectionStatus $target, bool $configured): void
    {
        $organization = Organization::factory()->create([
            'report_reviewer_name' => $configured ? 'Revisora documental' : null,
            'report_releaser_name' => $configured ? 'Liberador documental' : null,
        ]);
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingRelease]);
        $expected = ['approver' => $organization->report_reviewer_name, 'releaser' => $organization->report_releaser_name];
        app(TenantContext::class)->set($organization);
        try {
            app(TransitionInspection::class)->handle($actor, $inspection, [InspectionStatus::AwaitingRelease], $target);
        } finally {
            app(TenantContext::class)->clear();
        }
        $this->assertSame($expected, $inspection->fresh()->report_responsibles_snapshot);
        $this->assertSame($target, $inspection->fresh()->status);
        $this->assertSame($actor->id, $inspection->statusHistories()->sole()->changed_by);
        $organization->update(['report_reviewer_name' => 'Nome futuro', 'report_releaser_name' => 'Outro nome']);
        $actor->update(['name' => 'Usuário renomeado']);
        $this->assertSame($expected, app(ReportResponsibleNames::class)->forInspection($inspection->fresh()));
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($admin)->get(route('inspections.report-preview', $inspection))->assertInertia(fn (Assert $page) => $page
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

    public function test_failed_finalization_rolls_back_names_and_status_together(): void
    {
        $organization = Organization::factory()->create(['report_reviewer_name' => 'Nome para captura']);
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingRelease]);
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
