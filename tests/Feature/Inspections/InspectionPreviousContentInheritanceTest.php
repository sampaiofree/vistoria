<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\InspectionOverview\DeleteInspectionOverviewPhoto;
use App\Actions\InspectionOverview\StoreInspectionOverviewPhoto;
use App\Actions\Inspections\CreateInspection;
use App\Actions\Inspections\UpdatePlannedInspection;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\PhotoProcessingStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Reports\InspectionOverviewPresenter;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class InspectionPreviousContentInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_reinspection_copies_all_overview_pages_and_independent_files(): void
    {
        Storage::fake('inspection_photos');
        [$organization, $planner, $equipment, $previous] = $this->scenario();
        $sources = [];
        foreach ([1 => 'Primeira página', 2 => 'Primeira página', 3 => 'Segunda página'] as $position => $comment) {
            $block = InspectionOverviewBlock::factory()->forInspection($previous, $position)->create([
                'comment' => $comment,
                'recommendation' => 'Recomendação '.$position,
            ]);
            foreach ([1, 2] as $slot) {
                $sources[] = $this->readyPhoto($block, $slot);
            }
        }
        app(TenantContext::class)->set($organization);

        $inspection = app(CreateInspection::class)->handle($planner, $equipment, $this->dates());
        $overview = app(InspectionOverviewPresenter::class)->present($inspection);

        $this->assertSame($previous->id, $inspection->previous_inspection_id);
        $this->assertNull($inspection->inspected_on);
        $this->assertSame(6, $overview['photo_count']);
        $this->assertSame(6, $overview['ready_count']);
        $this->assertSame('Primeira página', $overview['pages'][0]['comment']);
        $this->assertSame('Segunda página', $overview['pages'][1]['comment']);
        $this->assertSame('Recomendação 3', $overview['pages'][1]['recommendation']);

        foreach ($sources as $source) {
            $copy = $inspection->overviewBlocks()->where('position', $source->block->position)->firstOrFail()
                ->photos()->where('slot', $source->slot)->firstOrFail();
            $this->assertSame(PhotoProcessingStatus::Ready, $copy->processing_status);
            $this->assertNotSame($source->optimized_path, $copy->optimized_path);
            $this->assertNotSame($source->thumbnail_path, $copy->thumbnail_path);
            $this->assertSame(Storage::disk('inspection_photos')->get($source->optimized_path), Storage::disk('inspection_photos')->get($copy->optimized_path));
            Storage::disk('inspection_photos')->assertExists($copy->thumbnail_path);
        }

        Queue::fake();
        app(StoreInspectionOverviewPhoto::class)->handle(
            $planner, $inspection, 1, 2, UploadedFile::fake()->image('nova.jpg'),
        );
        Storage::disk('inspection_photos')->assertExists($sources[1]->optimized_path);
        Storage::disk('inspection_photos')->assertExists($sources[1]->thumbnail_path);

        $copy = $inspection->overviewBlocks()->where('position', 1)->firstOrFail()->photos()->where('slot', 1)->firstOrFail();
        app(DeleteInspectionOverviewPhoto::class)->handle($planner, $copy);
        Storage::disk('inspection_photos')->assertExists($sources[0]->optimized_path);
        Storage::disk('inspection_photos')->assertExists($sources[0]->thumbnail_path);
    }

    public function test_missing_historical_file_leaves_photo_slot_empty(): void
    {
        Storage::fake('inspection_photos');
        [$organization, $planner, $equipment, $previous] = $this->scenario();
        $block = InspectionOverviewBlock::factory()->forInspection($previous)->create();
        InspectionOverviewPhoto::factory()->forBlock($block, 1)->ready()->create([
            'optimized_path' => 'missing/optimized.webp',
            'thumbnail_path' => 'missing/thumbnail.webp',
        ]);
        $source = $this->readyPhoto($block, 2);
        app(TenantContext::class)->set($organization);

        $inspection = app(CreateInspection::class)->handle($planner, $equipment, $this->dates());

        $this->assertSame(1, $inspection->overviewPhotos()->count());
        $this->assertSame(2, $inspection->overviewPhotos()->firstOrFail()->slot);
        $this->assertSame($source->original_name, $inspection->overviewPhotos()->firstOrFail()->original_name);
        $overview = app(InspectionOverviewPresenter::class)->present($inspection);
        $this->assertSame(2, $overview['pages'][0]['photos'][0]['number']);
        $this->assertTrue($overview['has_gaps']);

        Queue::fake();
        app(StoreInspectionOverviewPhoto::class)->append($planner, $inspection, UploadedFile::fake()->image('reposta.jpg'));
        $this->assertSame([1, 2], $inspection->overviewPhotos()->orderBy('slot')->pluck('slot')->all());
        $this->assertSame($source->original_name, $inspection->overviewPhotos()->where('slot', 2)->firstOrFail()->original_name);
        $this->assertFalse(app(InspectionOverviewPresenter::class)->present($inspection->fresh())['has_gaps']);
    }

    public function test_batch_rollback_removes_copied_files(): void
    {
        Storage::fake('inspection_photos');
        [$organization, $planner, $equipment, $previous] = $this->scenario();
        $block = InspectionOverviewBlock::factory()->forInspection($previous)->create();
        $source = $this->readyPhoto($block, 1);
        $other = Equipment::factory()->for($organization)->create();
        Inspection::factory()->forEquipment($other)->create(['status' => InspectionStatus::Released, 'released_at' => now()]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        $this->actingAs($planner)->post(route('inspections.store'), ['inspections' => [
            ['equipment_id' => $equipment->id, 'inspector_id' => $inspector->id, 'service_order' => '0000000001', ...$this->dates()],
            ['equipment_id' => $other->id, 'inspector_id' => $inspector->id, 'service_order' => '0000000002', ...$this->dates(), 'reinspection_defect_ids' => [999999]],
        ]])->assertSessionHasErrors('inspections.1.reinspection_defect_ids');

        $this->assertDatabaseCount('inspections', 2);
        $this->assertSame(
            [$source->optimized_path, $source->thumbnail_path],
            Storage::disk('inspection_photos')->allFiles(),
        );
    }

    public function test_failed_photo_copy_cancels_creation_and_removes_partial_files(): void
    {
        $disk = Storage::fake('inspection_photos');
        [$organization, $planner, $equipment, $previous] = $this->scenario();
        $block = InspectionOverviewBlock::factory()->forInspection($previous)->create();
        $first = $this->readyPhoto($block, 1);
        $second = $this->readyPhoto($block, 2);
        $calls = 0;
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('copy')->andReturnUsing(function (string $from, string $to) use ($disk, &$calls): bool {
            $calls++;

            return $calls === 4 ? false : $disk->copy($from, $to);
        });
        Storage::set('inspection_photos', $mock);
        app(TenantContext::class)->set($organization);

        try {
            app(CreateInspection::class)->handle($planner, $equipment, $this->dates());
            $this->fail('A cópia incompleta deve cancelar a reinspeção.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inspection', $exception->errors());
        }

        $this->assertSame(4, $calls);
        $this->assertDatabaseCount('inspections', 1);
        $this->assertSame(
            [$first->optimized_path, $first->thumbnail_path, $second->optimized_path, $second->thumbnail_path],
            $disk->allFiles(),
        );
    }

    public function test_changing_planned_equipment_replaces_inherited_photos_and_m2(): void
    {
        Storage::fake('inspection_photos');
        [$organization, $planner, $equipment, $previous] = $this->scenario();
        $firstBlock = InspectionOverviewBlock::factory()->forInspection($previous)->create(['comment' => 'Equipamento original']);
        $firstSource = $this->readyPhoto($firstBlock, 1);
        $firstNote = SapM2Note::query()->create(['organization_id' => $organization->id, 'equipment_id' => $equipment->id, 'sap_number' => '11111111']);
        InspectionClassificationM2Link::query()->create([
            'organization_id' => $organization->id, 'inspection_id' => $previous->id,
            'category' => 'CV', 'classification_code' => 'CV-1', 'sap_m2_note_id' => $firstNote->id,
        ]);
        $otherEquipment = Equipment::factory()->for($organization)->create();
        $otherPrevious = Inspection::factory()->forEquipment($otherEquipment)->create(['status' => InspectionStatus::Released, 'released_at' => now()]);
        $otherBlock = InspectionOverviewBlock::factory()->forInspection($otherPrevious)->create(['comment' => 'Novo equipamento']);
        $otherSource = $this->readyPhoto($otherBlock, 1);
        $otherNote = SapM2Note::query()->create(['organization_id' => $organization->id, 'equipment_id' => $otherEquipment->id, 'sap_number' => '22222222']);
        InspectionClassificationM2Link::query()->create([
            'organization_id' => $organization->id, 'inspection_id' => $otherPrevious->id,
            'category' => 'CV', 'classification_code' => 'CV-2', 'sap_m2_note_id' => $otherNote->id,
        ]);
        app(TenantContext::class)->set($organization);
        $inspection = app(CreateInspection::class)->handle($planner, $equipment, $this->dates());
        $oldCopyPath = $inspection->overviewPhotos()->firstOrFail()->optimized_path;
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        InspectionResponsible::factory()->forInspection($inspection, $planner)->create(['responsibility' => InspectionResponsibility::Preparer]);

        $updated = app(UpdatePlannedInspection::class)->handle($inspection, $planner, [
            'equipment_id' => $otherEquipment->id,
            'inspector_id' => $inspector->id,
            ...$this->dates(),
        ]);

        $this->assertSame($otherPrevious->id, $updated->previous_inspection_id);
        $this->assertSame('Novo equipamento', $updated->overviewBlocks()->where('position', 1)->firstOrFail()->comment);
        $this->assertSame($otherNote->id, $updated->classificationM2Links()->sole()->sap_m2_note_id);
        $this->assertSame(1, $updated->overviewPhotos()->count());
        Storage::disk('inspection_photos')->assertMissing($oldCopyPath);
        Storage::disk('inspection_photos')->assertExists($firstSource->optimized_path);
        Storage::disk('inspection_photos')->assertExists($otherSource->optimized_path);
        Storage::disk('inspection_photos')->assertExists($updated->overviewPhotos()->firstOrFail()->optimized_path);
    }

    public function test_initial_inspection_starts_without_inherited_content(): void
    {
        [$organization, $planner] = $this->scenario();
        $equipment = Equipment::factory()->for($organization)->create();
        app(TenantContext::class)->set($organization);

        $inspection = app(CreateInspection::class)->handle($planner, $equipment, $this->dates());

        $this->assertNull($inspection->previous_inspection_id);
        $this->assertSame(2, $inspection->overviewBlocks()->count());
        $this->assertSame(0, $inspection->overviewPhotos()->count());
        $this->assertSame(0, $inspection->classificationM2Links()->count());
    }

    /** @return array{Organization, User, Equipment, Inspection} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $equipment = Equipment::factory()->for($organization)->create();
        $previous = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'released_at' => now(),
            'inspected_on' => '2025-01-01',
        ]);

        return [$organization, $planner, $equipment, $previous];
    }

    private function readyPhoto(InspectionOverviewBlock $block, int $slot): InspectionOverviewPhoto
    {
        $path = "previous/{$block->position}/{$slot}";
        $photo = InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create([
            'optimized_path' => $path.'/optimized.webp',
            'thumbnail_path' => $path.'/thumbnail.webp',
        ]);
        Storage::disk('inspection_photos')->put($photo->optimized_path, "optimized {$path}");
        Storage::disk('inspection_photos')->put($photo->thumbnail_path, "thumbnail {$path}");

        return $photo;
    }

    /** @return array{planned_start_on:string,planned_end_on:string} */
    private function dates(): array
    {
        return ['planned_start_on' => '2026-10-10', 'planned_end_on' => '2026-10-11'];
    }
}
