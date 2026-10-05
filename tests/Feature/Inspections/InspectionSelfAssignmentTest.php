<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\SelfAssignInspectionResponsible;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\User;
use App\Services\Inspections\InspectionSelfAssignment;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InspectionSelfAssignmentTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_queue_and_report_visibility_follow_the_vacancy_in_the_users_role(OperationalRole $role, InspectionResponsibility $responsibility): void
    {
        $user = $this->user($role);
        $mine = $this->inspection($user);
        $this->assign($mine, $user, $responsibility);
        $vacant = $this->inspection($user);
        $older = $this->inspection($user);
        $older->forceFill(['created_at' => now()->subMonth()])->save();
        $vacant->update(['service_order' => 'FILA-EXCLUSIVA']);
        $otherRole = $responsibility === InspectionResponsibility::Approver ? InspectionResponsibility::Releaser : InspectionResponsibility::Approver;
        $otherUser = $this->user($role === OperationalRole::Reviewer ? OperationalRole::Releaser : OperationalRole::Reviewer, $user);
        $this->assign($older, $otherUser, $otherRole);
        $occupied = $this->inspection($user);
        $inactive = $this->user($role, $user);
        $inactive->update(['status' => UserStatus::Inactive]);
        $this->assign($occupied, $inactive, $responsibility);
        $foreign = Inspection::factory()->create();
        $closed = $this->inspection($user, InspectionStatus::Released);
        $this->inspection($user, InspectionStatus::Canceled);

        $this->actingAs($user)->get(route('inspections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('filters.scope', 'mine')
                ->has('inspections.data', 1)->where('inspections.data.0.public_id', $mine->public_id));
        $this->get(route('inspections.index', ['scope' => 'available', 'responsible' => $user->id, 'responsibility' => $responsibility->value]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.scope', 'available')
                ->where('filters.responsible', '')->where('filters.responsibility', '')
                ->has('inspections.data', 2)->where('inspections.data.0.public_id', $older->public_id)
                ->where('inspections.data.1.public_id', $vacant->public_id)
                ->where('inspections.data.0.self_assign.action', route('inspections.self-assign', $older)));
        $this->get(route('inspections.index', ['scope' => 'available', 'search' => 'FILA-EXCLUSIVA', 'status' => 'planned']))
            ->assertInertia(fn (Assert $page) => $page->has('inspections.data', 1)->where('inspections.data.0.public_id', $vacant->public_id));
        $this->get(route('inspections.show', $older))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('capabilities.self_assign.action', route('inspections.self-assign', $older)));
        $this->get(route('inspections.report-preview', $older))->assertOk();
        $this->get(route('inspections.show', $occupied))->assertForbidden();
        $this->get(route('inspections.report-preview', $occupied))->assertForbidden();
        $this->get(route('inspections.show', $closed))->assertForbidden();
        $this->get(route('inspections.show', $foreign))->assertNotFound();
        $mine->update(['status' => InspectionStatus::Released]);
        $this->get(route('inspections.show', $mine))->assertOk();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('links.available_inspections', route('inspections.index', ['scope' => 'available'])));
    }

    #[DataProvider('roles')]
    public function test_company_admin_can_claim_a_vacant_matching_role_without_losing_the_company_index(OperationalRole $role, InspectionResponsibility $responsibility): void
    {
        $admin = $this->user($role);
        $admin->update(['account_type' => UserAccountType::CompanyAdmin]);
        $vacant = $this->inspection($admin, $role === OperationalRole::Reviewer
            ? InspectionStatus::AwaitingReview : InspectionStatus::AwaitingRelease);
        $occupied = $this->inspection($admin);
        $other = $this->user($role, $admin);
        $this->assign($occupied, $other, $responsibility);
        $closed = $this->inspection($admin, InspectionStatus::Released);
        $foreign = Inspection::factory()->create();
        $ability = $role === OperationalRole::Reviewer ? 'startReview' : 'release';
        $this->assertFalse($admin->can($ability, $vacant));

        $this->actingAs($admin)->get(route('inspections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.available_queue', true)
                ->where('capabilities.company_wide_index', true)
                ->has('inspections.data', 3));
        $this->get(route('inspections.index', ['scope' => 'available']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('inspections.data', 1)
                ->where('inspections.data.0.public_id', $vacant->public_id)
                ->where('inspections.data.0.self_assign.label', 'Assumir como '.$responsibility->label()));
        $this->get(route('inspections.show', $vacant))
            ->assertInertia(fn (Assert $page) => $page->where('capabilities.self_assign.action', route('inspections.self-assign', $vacant)));

        $this->post(route('inspections.self-assign', $vacant))
            ->assertRedirect(route('inspections.show', $vacant))->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $vacant->responsibles()->where('responsibility', $responsibility->value)->sole()->user_id);
        $this->assertTrue($admin->can($ability, $vacant));
        $this->post(route('inspections.self-assign', $occupied))
            ->assertRedirect(route('inspections.index', ['scope' => 'available']))->assertSessionHas('error');
        $this->post(route('inspections.self-assign', $closed))->assertForbidden();
        $this->post(route('inspections.self-assign', $foreign))->assertForbidden();

        if ($role === OperationalRole::Releaser) {
            $this->post(route('inspections.cancel', $vacant), ['justification' => 'Cancelamento pelo Liberador responsável.'])
                ->assertSessionHasNoErrors();
            $this->assertSame(InspectionStatus::Canceled, $vacant->fresh()->status);
        }

        $anotherVacancy = $this->inspection($admin);
        $admin->update(['status' => UserStatus::Inactive]);
        $this->post(route('inspections.self-assign', $anotherVacancy))->assertRedirect(route('login'));
        $this->assertFalse($anotherVacancy->hasResponsibility($responsibility));
    }

    #[DataProvider('roles')]
    public function test_claim_is_idempotent_and_a_competing_user_cannot_replace_the_winner(OperationalRole $role, InspectionResponsibility $responsibility): void
    {
        $this->freezeSecond();
        $winner = $this->user($role);
        $loser = $this->user($role, $winner);
        $inspection = $this->inspection($winner);
        $originalInspection = $inspection->fresh()->getRawOriginal();
        $url = route('inspections.self-assign', $inspection);
        $this->actingAs($winner)->post($url)->assertRedirect(route('inspections.show', $inspection))->assertSessionHasNoErrors();
        $assignment = $inspection->responsibles()->sole();
        $original = $assignment->getRawOriginal();
        $this->assertSame($responsibility, $assignment->responsibility);
        $this->assertSame($winner->id, $assignment->user_id);
        $this->assertSame($winner->id, $assignment->assigned_by);
        $this->assertTrue($assignment->is_primary);
        $this->assertSame($originalInspection, $inspection->fresh()->getRawOriginal());
        $history = $inspection->statusHistories()->sole();
        $this->assertSame($history->from_status, $history->to_status);
        $this->assertSame($winner->id, $history->changed_by);
        $this->assertSame('responsibility_self_assigned', $history->metadata['event']);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('dashboard-recent-activities', fn (Assert $deferred) => $deferred
                ->has('recent_activities', 1)
                ->where('recent_activities.0.description', $winner->name.' assumiu como '.$responsibility->label().' na inspeção '.($inspection->number ?? 'inspeção').'.')));

        $this->travel(1)->minutes();
        $this->post($url)->assertRedirect(route('inspections.show', $inspection))->assertSessionHasNoErrors();
        $this->assertSame($original, $assignment->fresh()->getRawOriginal());
        $this->actingAs($loser)->from(route('inspections.show', $inspection))->post($url)
            ->assertRedirect(route('inspections.index', ['scope' => 'available']))->assertSessionHas('error');
        $this->get(route('inspections.index', ['scope' => 'available']))->assertOk();
        $this->assertSame($original, $assignment->fresh()->getRawOriginal());
        $this->assertSame(1, $inspection->responsibles()->count());
        $this->assertSame(1, $inspection->statusHistories()->count());

        $admin = $this->user(OperationalRole::Planner, $winner);
        $admin->update(['account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($admin)->post(route('inspections.responsibles.store', $inspection), [
            'user_id' => $loser->id, 'responsibility' => $responsibility->value,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($loser->id, $inspection->responsibles()->sole()->user_id);
        $this->assertSame($winner->id, $inspection->statusHistories()->sole()->metadata['user_id']);
    }

    #[DataProvider('openStages')]
    public function test_claims_do_not_change_the_stage_and_only_the_assigned_releaser_can_cancel(InspectionStatus $status): void
    {
        $reviewer = $this->user(OperationalRole::Reviewer);
        $releaser = $this->user(OperationalRole::Releaser, $reviewer);
        $inspection = $this->inspection($reviewer, $status);
        $claim = route('inspections.self-assign', $inspection);
        $cancel = route('inspections.cancel', $inspection);
        $this->actingAs($reviewer)->post($claim)->assertSessionHasNoErrors();
        $this->post($cancel, ['justification' => 'Cancelamento solicitado.'])->assertForbidden();
        $this->actingAs($releaser)->post($cancel, ['justification' => 'Cancelamento solicitado.'])->assertForbidden();
        $this->post($claim)->assertSessionHasNoErrors();
        $this->assertSame($status, $inspection->fresh()->status);
        $this->post($cancel, [])->assertSessionHasErrors('justification');
        $this->post($cancel, ['justification' => 'Curta'])->assertSessionHasErrors('justification');
        $this->post($cancel, ['justification' => str_repeat('a', 5001)])->assertSessionHasErrors('justification');
        $this->post($cancel, ['justification' => '  Cancelamento solicitado pelo cliente.  '])->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::Canceled, $inspection->fresh()->status);
        $this->assertNotNull($inspection->fresh()->canceled_at);
        $history = $inspection->statusHistories()->where('to_status', InspectionStatus::Canceled->value)->sole();
        $this->assertSame($releaser->id, $history->changed_by);
        $this->assertSame('Cancelamento solicitado pelo cliente.', $history->reason);
        $this->assertSame(3, $inspection->statusHistories()->count());
        $this->post($cancel, ['justification' => 'Tentativa após o cancelamento.'])->assertForbidden();
    }

    public function test_invalid_claims_and_cancel_attempts_do_not_change_assignments(): void
    {
        $reviewer = $this->user(OperationalRole::Reviewer);
        $inspection = $this->inspection($reviewer);
        $url = route('inspections.self-assign', $inspection);
        $this->actingAs($reviewer);
        foreach (['user_id' => $reviewer->id, 'responsibility' => 'releaser', 'role' => 'releaser'] as $field => $value) {
            $this->post($url, [$field => $value])->assertSessionHasErrors($field);
        }
        foreach ([OperationalRole::Inspector, OperationalRole::Planner] as $role) {
            $actor = $this->user($role, $reviewer);
            $this->actingAs($actor)->post($url)->assertForbidden();
            $this->get(route('inspections.index', ['scope' => 'available']))->assertForbidden();
        }
        $this->actingAs($reviewer)->post(route('inspections.self-assign', Inspection::factory()->create()))->assertForbidden();
        foreach ([InspectionStatus::Released, InspectionStatus::Canceled] as $status) {
            $inspection->update(['status' => $status]);
            $this->post($url)->assertForbidden();
        }
        $inspection->update(['status' => InspectionStatus::Planned]);
        $reviewer->update(['account_type' => UserAccountType::CompanyAdmin, 'operational_role' => OperationalRole::Planner]);
        $this->actingAs($reviewer)->post($url)->assertForbidden();
        $this->assign($inspection, $reviewer, InspectionResponsibility::Releaser);
        $this->post(route('inspections.cancel', $inspection), ['justification' => 'Cancelamento pelo administrador.'])->assertForbidden();
        $this->assertSame(0, $inspection->statusHistories()->count());
        $this->assertSame(InspectionStatus::Planned, $inspection->fresh()->status);
    }

    #[DataProvider('roles')]
    public function test_inactive_or_incompatible_assignees_keep_the_role_occupied(OperationalRole $role, InspectionResponsibility $responsibility): void
    {
        $actor = $this->user($role);
        $inspection = $this->inspection($actor);
        $unavailableUser = $this->user(OperationalRole::Inspector, $actor);
        $assignment = $this->assign($inspection, $unavailableUser, $responsibility);
        foreach ([UserStatus::Active, UserStatus::Inactive] as $status) {
            $unavailableUser->update(['status' => $status]);
            $this->assertFalse(app(InspectionSelfAssignment::class)->isAvailable($actor, $inspection));
            $this->actingAs($actor)->post(route('inspections.self-assign', $inspection))
                ->assertRedirect(route('inspections.index', ['scope' => 'available']))->assertSessionHas('error');
            $this->assertSame($unavailableUser->id, $assignment->fresh()->user_id);
        }
    }

    #[DataProvider('roles')]
    public function test_workflow_requires_the_specific_role_and_manual_assignment_validates_it(OperationalRole $role, InspectionResponsibility $responsibility): void
    {
        $actor = $this->user($role);
        $inspection = $this->inspection($actor, $role === OperationalRole::Reviewer ? InspectionStatus::InReview : InspectionStatus::AwaitingRelease);
        $this->assign($inspection, $actor, InspectionResponsibility::Preparer);
        $abilities = $role === OperationalRole::Reviewer
            ? ['approve', 'returnForCorrection', 'createCorrectionRequests', 'manageReportContent']
            : ['release', 'returnForReview', 'createCorrectionRequests', 'cancel'];
        foreach ($abilities as $ability) {
            $this->assertFalse($actor->can($ability, $inspection), $ability);
        }
        $admin = $this->user(OperationalRole::Planner, $actor);
        $admin->update(['account_type' => UserAccountType::CompanyAdmin]);
        $wrongUser = $this->user(OperationalRole::Inspector, $actor);
        $this->actingAs($admin)->post(route('inspections.responsibles.store', $inspection), [
            'user_id' => $wrongUser->id, 'responsibility' => $responsibility->value,
        ])->assertSessionHasErrors('user_id');
        $this->assertSame(1, $inspection->responsibles()->count());
        $this->post(route('inspections.responsibles.store', $inspection), [
            'user_id' => $actor->id, 'responsibility' => $responsibility->value,
        ])->assertSessionHasNoErrors();
        foreach ($abilities as $ability) {
            $this->assertTrue($actor->can($ability, $inspection), $ability);
        }
        $this->get(route('inspections.team', $inspection))->assertInertia(fn (Assert $page) => $page
            ->has('assignment_options.users.0.operational_role')->has('assignment_options.users.0.account_type'));
    }

    #[DataProvider('staleUserChanges')]
    public function test_action_reloads_the_user_before_authorizing(array $changes): void
    {
        $actor = $this->user(OperationalRole::Reviewer);
        $inspection = $this->inspection($actor);
        User::query()->whereKey($actor->id)->update($changes);
        app(TenantContext::class)->set($actor->organization);
        try {
            app(SelfAssignInspectionResponsible::class)->handle($inspection, $actor);
            $this->fail('Stale user data must not authorize a claim.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $inspection->responsibles()->count());
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    public function test_available_queue_paginates_oldest_first_and_filters_by_stage(): void
    {
        $actor = $this->user(OperationalRole::Reviewer);
        $inspections = collect(range(1, 22))->map(function ($day) use ($actor) {
            $inspection = $this->inspection($actor);
            $inspection->forceFill(['created_at' => now()->subDays(30 - $day)])->save();

            return $inspection;
        });
        $this->inspection($actor, InspectionStatus::InReview);
        $this->actingAs($actor)->get(route('inspections.index', ['scope' => 'available', 'status' => 'planned']))
            ->assertInertia(fn (Assert $page) => $page->has('inspections.data', 20)
                ->where('inspections.total', 22)
                ->where('inspections.data.0.public_id', $inspections->first()->public_id));
        $this->get(route('inspections.index', ['scope' => 'available', 'status' => 'planned', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('inspections.data', 2)
                ->where('inspections.data.1.public_id', $inspections->last()->public_id));
    }

    public function test_inactive_user_cannot_claim_or_read_a_vacant_inspection(): void
    {
        $actor = $this->user(OperationalRole::Releaser);
        $inspection = $this->inspection($actor);
        $actor->update(['status' => UserStatus::Inactive]);
        $this->assertFalse($actor->can('view', $inspection));
        $this->assertFalse($actor->can('selfAssign', $inspection));
        $this->actingAs($actor)->post(route('inspections.self-assign', $inspection))->assertRedirect(route('login'));
        $this->assertSame(0, $inspection->responsibles()->count());
        $this->assertSame(0, $inspection->statusHistories()->count());
    }

    public static function staleUserChanges(): array
    {
        return [
            'role changed' => [['operational_role' => OperationalRole::Inspector]],
            'deactivated' => [['status' => UserStatus::Inactive]],
            'role removed' => [['operational_role' => null]],
            'organization removed' => [['organization_id' => null]],
        ];
    }

    public static function roles(): array
    {
        return [
            'reviewer' => [OperationalRole::Reviewer, InspectionResponsibility::Approver],
            'releaser' => [OperationalRole::Releaser, InspectionResponsibility::Releaser],
        ];
    }

    public static function openStages(): array
    {
        return array_map(fn ($status) => [$status], array_values(array_filter(InspectionStatus::cases(), fn ($status) => ! $status->isFinal())));
    }

    private function user(OperationalRole $role, ?User $sameOrganization = null): User
    {
        return User::factory()->create(array_filter(['organization_id' => $sameOrganization?->organization_id, 'operational_role' => $role]));
    }

    private function inspection(User $user, InspectionStatus $status = InspectionStatus::Planned): Inspection
    {
        return Inspection::factory()->create(['organization_id' => $user->organization_id, 'status' => $status]);
    }

    private function assign(Inspection $inspection, User $user, InspectionResponsibility $role): InspectionResponsible
    {
        return InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => $role]);
    }
}
