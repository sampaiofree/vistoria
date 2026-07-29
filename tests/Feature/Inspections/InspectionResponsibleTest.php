<?php

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\AssignInspectionResponsible;
use App\Actions\Inspections\RemoveInspectionResponsible;
use App\Actions\Inspections\SetPrimaryInspectionResponsible;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InspectionResponsibleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Inspection $inspection;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        app(TenantContext::class)->set($this->organization);
        $this->inspection = Inspection::factory()->create(['organization_id' => $this->organization]);
        $this->actor = User::factory()->create(['organization_id' => $this->organization]);
    }

    public function test_it_rejects_cross_tenant_inspections_assignees_and_actors(): void
    {
        $other = Organization::factory()->create();
        $foreignInspection = Inspection::factory()->create(['organization_id' => $other]);
        $foreignUser = User::factory()->create(['organization_id' => $other]);
        $localUser = User::factory()->create(['organization_id' => $this->organization]);

        foreach ([
            [$foreignInspection, $localUser, $this->actor],
            [$this->inspection, $foreignUser, $this->actor],
            [$this->inspection, $localUser, $foreignUser],
        ] as [$inspection, $assignee, $actor]) {
            try {
                app(AssignInspectionResponsible::class)->handle($inspection, $assignee, InspectionResponsibility::Inspector, $actor);
                $this->fail('Uma atribuição entre tenants deveria ter sido rejeitada.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('inspection_responsibles', 0);
            }
        }
    }

    public function test_it_rejects_inactive_and_super_admin_assignees(): void
    {
        $inactive = User::factory()->inactive()->create(['organization_id' => $this->organization]);
        $superAdmin = User::factory()->superAdmin()->create();

        foreach ([$inactive, $superAdmin] as $user) {
            $this->expectValidationFailure(fn () => app(AssignInspectionResponsible::class)
                ->handle($this->inspection, $user, InspectionResponsibility::Inspector, $this->actor));
        }

        $this->assertDatabaseCount('inspection_responsibles', 0);
    }

    public function test_it_rejects_duplicate_but_allows_multiple_inspectors_and_multiple_roles(): void
    {
        $first = User::factory()->create(['organization_id' => $this->organization]);
        $second = User::factory()->create(['organization_id' => $this->organization]);
        $assign = app(AssignInspectionResponsible::class);

        $assign->handle($this->inspection, $first, 'inspector', $this->actor);
        $assign->handle($this->inspection, $second, InspectionResponsibility::Inspector, $this->actor);
        $assign->handle($this->inspection, $first, InspectionResponsibility::Reviewer, $this->actor);
        $this->expectValidationFailure(fn () => $assign->handle($this->inspection, $first, 'inspector', $this->actor));

        $this->assertDatabaseCount('inspection_responsibles', 3);
        $this->assertSame(2, $this->inspection->responsibles()->where('responsibility', 'inspector')->count());
        $this->assertNotNull($this->inspection->responsibles()->first()->assigned_at);
        $this->assertSame($this->actor->id, $this->inspection->responsibles()->first()->assigned_by);
    }

    public function test_changing_primary_is_atomic_and_scoped_to_the_responsibility(): void
    {
        $assign = app(AssignInspectionResponsible::class);
        $first = $assign->handle($this->inspection, User::factory()->create(['organization_id' => $this->organization]), 'inspector', $this->actor, true);
        $second = $assign->handle($this->inspection, User::factory()->create(['organization_id' => $this->organization]), 'inspector', $this->actor);
        $reviewer = $assign->handle($this->inspection, $first->user, 'reviewer', $this->actor, true);

        app(SetPrimaryInspectionResponsible::class)->handle($second, $this->actor);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
        $this->assertTrue($reviewer->refresh()->is_primary);
        $this->assertSame(1, $this->inspection->responsibles()->where('responsibility', 'inspector')->where('is_primary', true)->count());
    }

    public function test_removal_preserves_the_only_responsible_required_by_an_started_stage(): void
    {
        $responsible = app(AssignInspectionResponsible::class)->handle(
            $this->inspection, User::factory()->create(['organization_id' => $this->organization]), 'inspector', $this->actor,
        );
        $this->inspection->update(['status' => InspectionStatus::InProgress]);

        $this->expectValidationFailure(fn () => app(RemoveInspectionResponsible::class)->handle($responsible, $this->actor));
        $this->assertDatabaseHas('inspection_responsibles', ['id' => $responsible->id]);
    }

    private function expectValidationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Era esperada uma falha de validação.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
