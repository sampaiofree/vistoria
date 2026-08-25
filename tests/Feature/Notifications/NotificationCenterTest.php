<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Notifications\NotifyInspectionImageFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_notifies_distinct_inspection_responsibles_and_uploader(): void
    {
        [$inspection, $uploader, $responsible] = $this->scenario();
        InspectionResponsible::factory()->forInspection($inspection, $responsible)->create([
            'responsibility' => 'reviewer',
        ]);

        app(NotifyInspectionImageFailure::class)->handle(
            $inspection,
            $uploader->id,
            'Falha no processamento',
            'Escolha outra imagem.',
            route('inspections.show', $inspection),
        );

        $this->assertCount(1, $uploader->notifications);
        $this->assertCount(1, $responsible->notifications);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_user_can_view_and_mark_notifications_as_read_without_cross_user_access(): void
    {
        [$inspection, $uploader, $responsible] = $this->scenario();
        app(NotifyInspectionImageFailure::class)->handle(
            $inspection,
            $uploader->id,
            'Falha no processamento',
            'Escolha outra imagem.',
            route('inspections.show', $inspection),
        );

        $notification = $responsible->notifications()->firstOrFail();

        $this->actingAs($responsible)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.read', false)
                ->where('notifications.data.0.title', 'Falha no processamento'));

        $this->actingAs($uploader)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->actingAs($responsible)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('inspections.show', $inspection));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        [$inspection, $uploader, $responsible] = $this->scenario();

        foreach (range(1, 2) as $index) {
            app(NotifyInspectionImageFailure::class)->handle(
                $inspection,
                $uploader->id,
                "Falha {$index}",
                'Escolha outra imagem.',
                route('inspections.show', $inspection),
            );
        }

        $this->assertSame(2, $responsible->unreadNotifications()->count());
        $this->actingAs($responsible)->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $responsible->unreadNotifications()->count());
        $this->assertSame(2, $responsible->notifications()->count());
    }

    /** @return array{Inspection,User,User} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $uploader = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $responsible = User::factory()->for($organization)->create();
        InspectionResponsible::factory()->forInspection($inspection, $responsible)->create();

        return [$inspection, $uploader, $responsible];
    }
}
