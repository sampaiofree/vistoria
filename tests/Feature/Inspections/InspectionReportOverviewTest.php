<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\PhotoProcessingStatus;
use App\Enums\UserAccountType;
use App\Jobs\ProcessInspectionOverviewPhoto;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionReportOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_always_exposes_two_fixed_blocks_and_admin_can_update_their_texts(): void
    {
        [$organization, $admin, $inspection] = $this->scenario();

        $this->actingAs($admin)
            ->get(route('inspections.report-overview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/ReportOverview')
                ->where('active_tab', 'report_overview')
                ->where('capabilities.edit', true)
                ->has('overview.blocks', 2)
                ->has('overview.blocks.0.photos', 2)
                ->where('overview.blocks.0.photos.0.number', 1)
                ->where('overview.blocks.1.photos.1.number', 4));

        $this->actingAs($admin)
            ->put(route('inspections.report-overview.blocks.update', [$inspection, 1]), [
                'comment' => '  Vista   frontal do equipamento.  ',
                'recommendation' => '  Manter acompanhamento.  ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inspection_overview_blocks', [
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'position' => 1,
            'comment' => 'Vista frontal do equipamento.',
            'recommendation' => 'Manter acompanhamento.',
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('inspections.report-overview.blocks.update', [$inspection, 1]), [
                'comment' => str_repeat('A', 601),
                'recommendation' => null,
            ])
            ->assertSessionHasErrors('comment');
    }

    public function test_assigned_member_can_edit_but_unassigned_member_and_final_inspection_cannot(): void
    {
        [$organization, , $inspection] = $this->scenario();
        $assigned = User::factory()->for($organization)->create();
        $unassigned = User::factory()->for($organization)->create();
        InspectionResponsible::factory()->forInspection($inspection, $assigned)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        $payload = ['comment' => 'Comentário', 'recommendation' => 'Recomendação'];

        $this->actingAs($assigned)
            ->put(route('inspections.report-overview.blocks.update', [$inspection, 1]), $payload)
            ->assertRedirect();

        $this->actingAs($unassigned)
            ->get(route('inspections.report-overview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.edit', false)
                ->where('overview.blocks.0.update_url', null)
                ->where('overview.blocks.0.photos.0.upload_url', null));

        $this->actingAs($unassigned)
            ->put(route('inspections.report-overview.blocks.update', [$inspection, 1]), $payload)
            ->assertForbidden();

        $inspection->update(['status' => InspectionStatus::Released]);

        $this->actingAs($assigned)
            ->put(route('inspections.report-overview.blocks.update', [$inspection, 1]), $payload)
            ->assertForbidden();
    }

    public function test_upload_uses_fixed_slot_replaces_previous_photo_and_remains_private(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [, $admin, $inspection] = $this->scenario();
        $uploadUrl = route('inspections.report-overview.photos.store', [$inspection, 1, 1]);

        $this->actingAs($admin)
            ->post($uploadUrl, ['file' => UploadedFile::fake()->image('primeira.jpg', 1200, 800)])
            ->assertRedirect();

        $first = InspectionOverviewPhoto::query()->firstOrFail();
        Storage::disk('inspection_photos')->assertExists($first->original_path);
        Queue::assertPushed(ProcessInspectionOverviewPhoto::class, fn ($job): bool => $job->photoId === $first->id
            && $job->queue === 'images');

        $this->actingAs($admin)
            ->post($uploadUrl, ['file' => UploadedFile::fake()->image('substituta.png', 800, 600)])
            ->assertRedirect();

        $active = InspectionOverviewPhoto::query()->firstOrFail();
        $this->assertNotSame($first->id, $active->id);
        $this->assertSame(1, $active->slot);
        $this->assertSame(2, InspectionOverviewPhoto::withTrashed()->count());
        Storage::disk('inspection_photos')->assertMissing($first->original_path);

        $active->update([
            'processing_status' => PhotoProcessingStatus::Ready,
            'optimized_path' => 'overview/ready.webp',
            'thumbnail_path' => 'overview/thumb.webp',
        ]);
        Storage::disk('inspection_photos')->put('overview/ready.webp', 'image');

        auth()->logout();
        $this->get(route('inspection-overview-photos.show', $active))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('inspection-overview-photos.show', $active))->assertOk();

        $other = User::factory()->for(Organization::factory()->create())->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $this->actingAs($other)->get(route('inspection-overview-photos.show', $active))->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('inspection-overview-photos.destroy', $active))
            ->assertRedirect();
        $this->assertSoftDeleted('inspection_overview_photos', ['id' => $active->id, 'slot' => null]);
        Storage::disk('inspection_photos')->assertMissing($active->original_path);
        Storage::disk('inspection_photos')->assertMissing('overview/ready.webp');
    }

    public function test_report_blocks_export_until_overview_is_complete_and_exposes_ready_payload(): void
    {
        [, $admin, $inspection] = $this->scenario();
        $inspection->update([
            'external_report_number' => 'U0306VT-G-6RI002',
            'designer_i_report_number' => 'SM-IIE-1717',
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', false)
                ->has('content.overview.blocks', 2)
                ->where('content.overview.blocks.0.photos.0.number', 1)
                ->where('content.overview.blocks.1.photos.1.number', 4)
                ->where('content.validation.issues', fn ($issues): bool => collect($issues)->contains(
                    'Adicione as quatro fotografias da Vista geral para exportar o relatório.',
                ) && collect($issues)->contains(
                    'Preencha os comentários e recomendações da Vista geral para exportar o relatório.',
                )));

        $pendingPhoto = null;
        foreach ([1, 2] as $position) {
            $block = InspectionOverviewBlock::factory()->forInspection($inspection, $position)->create([
                'comment' => "Comentário do bloco {$position}",
                'recommendation' => "Recomendação do bloco {$position}",
            ]);

            foreach ([1, 2] as $slot) {
                $factory = InspectionOverviewPhoto::factory()->forBlock($block, $slot);
                $photo = $position === 1 && $slot === 1
                    ? $factory->create()
                    : $factory->ready()->create();

                if ($position === 1 && $slot === 1) {
                    $pendingPhoto = $photo;
                }
            }
        }

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', false)
                ->where('content.validation.issues', fn ($issues): bool => collect($issues)->contains(
                    'Aguarde o processamento das quatro fotografias da Vista geral antes de exportar o relatório.',
                )));

        $pendingPhoto->update([
            'processing_status' => PhotoProcessingStatus::Ready,
            'optimized_path' => 'overview/optimized.webp',
            'thumbnail_path' => 'overview/thumbnail.webp',
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.print_enabled', true)
                ->where('content.overview.complete', true)
                ->where('content.overview.title', 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC')
                ->where('content.overview.section_title', 'DOCUMENTAÇÃO FOTOGRÁFICA - TAC')
                ->where('content.overview.blocks.0.photos.0.photo.status', 'ready'));
    }

    /** @return array{Organization, User, Inspection} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InProgress]);

        return [$organization, $admin, $inspection];
    }
}
