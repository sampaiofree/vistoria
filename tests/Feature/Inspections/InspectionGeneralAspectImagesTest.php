<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessInspectionGeneralAspectImage;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionGeneralAspectImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_processing_private_read_and_document_references(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$user, $inspection] = $this->scenario();

        $response = $this->actingAs($user)->postJson(route('inspections.general-aspects.images.store', $inspection), [
            'file' => UploadedFile::fake()->image('estrutura.jpg', 1200, 800),
        ])->assertStatus(202)->assertJsonPath('status', 'pending');
        $id = $response->json('assetId');
        $image = InspectionGeneralAspectImage::query()->where('public_id', $id)->firstOrFail();
        $this->assertSame($inspection->id, $image->inspection_id);
        $this->assertStringContainsString('/general-aspects/', $image->original_path);
        Queue::assertPushed(ProcessInspectionGeneralAspectImage::class);

        $document = $this->document($id);
        $this->put(route('inspections.general-aspects.update', $inspection), ['schema_version' => 2, 'document' => $document])
            ->assertSessionHasErrors('document');
        (new ProcessInspectionGeneralAspectImage($image->id))->handle();
        $image->refresh();
        $this->assertSame(PhotoProcessingStatus::Ready, $image->processing_status);
        Storage::disk('inspection_photos')->assertMissing($image->original_path);

        $this->put(route('inspections.general-aspects.update', $inspection), ['schema_version' => 2, 'document' => $document])
            ->assertRedirect();
        $this->assertNull($image->refresh()->unreferenced_at);
        $this->get(route('inspection-general-aspect-images.show', [$image, 'optimized']))
            ->assertOk()->assertHeader('content-type', 'image/webp');
        $this->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.general_aspects.schema_version', 2)
                ->where('content.general_aspects.document.content.1.attrs.assetId', $id)
                ->where('content.general_aspects.images.'.$id.'.optimized', route('inspection-general-aspect-images.show', [$image, 'optimized'])));

        $other = User::factory()->for(Organization::factory()->create())->create();
        $this->actingAs($other)->get(route('inspection-general-aspect-images.show', [$image, 'optimized']))->assertNotFound();
    }

    public function test_rejects_foreign_failed_and_external_images_and_accepts_table_text(): void
    {
        [$user, $inspection] = $this->scenario();
        $otherInspection = Inspection::factory()->forEquipment($inspection->equipment)->create();
        $foreign = $this->image($otherInspection, PhotoProcessingStatus::Ready);
        $failed = $this->image($inspection, PhotoProcessingStatus::Failed);

        foreach ([$foreign->public_id, $failed->public_id] as $id) {
            $this->actingAs($user)->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 2, 'document' => $this->document($id),
            ])->assertSessionHasErrors('document');
        }
        foreach (['https://example.com/image.jpg', 'data:image/png;base64,AAAA'] as $url) {
            $doc = $this->document($failed->public_id);
            $doc['content'][1]['attrs']['src'] = $url;
            $this->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 2, 'document' => $doc,
            ])->assertSessionHasErrors('document');
        }
        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2,
            'document' => ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['assetId' => $failed->public_id]]]],
        ])->assertSessionHasErrors('document');

        $table = ['type' => 'table', 'content' => [[
            'type' => 'tableRow', 'content' => [[
                'type' => 'tableHeader', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null, 'align' => null],
                'content' => [['type' => 'paragraph', 'content' => [[
                    'type' => 'text', 'text' => 'Estrutura', 'marks' => [['type' => 'bold']],
                ]]]],
            ]],
        ]]];
        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => ['type' => 'doc', 'content' => [$table]],
        ])->assertRedirect();
        $saved = json_decode($inspection->refresh()->general_notes, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Estrutura', $saved['document']['content'][0]['content'][0]['content'][0]['content'][0]['content'][0]['text']);

        $invalid = $table;
        $invalid['content'][0]['content'][0]['attrs']['colspan'] = 2;
        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => ['type' => 'doc', 'content' => [$invalid]],
        ])->assertSessionHasErrors('document');
    }

    public function test_upload_rejects_unsupported_formats_and_files_above_25_mb(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$user, $inspection] = $this->scenario();

        foreach ([
            UploadedFile::fake()->create('estrutura.gif', 10, 'image/gif'),
            UploadedFile::fake()->create('estrutura.jpg', 25_601, 'image/jpeg'),
        ] as $file) {
            $this->actingAs($user)->post(route('inspections.general-aspects.images.store', $inspection), [
                'file' => $file,
            ])->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('inspection_general_aspect_images', 0);
    }

    public function test_document_rejects_more_than_ten_images_and_invalid_table_shapes(): void
    {
        [$user, $inspection] = $this->scenario();
        $image = $this->image($inspection, PhotoProcessingStatus::Ready);
        $imageNode = ['type' => 'image', 'attrs' => ['assetId' => $image->public_id]];
        $textNode = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Estrutura']]];
        $cell = ['type' => 'tableCell', 'content' => [$textNode]];
        $row = ['type' => 'tableRow', 'content' => [$cell]];
        $invalidDocuments = [
            ['type' => 'doc', 'content' => [$textNode, ...array_fill(0, 11, $imageNode)]],
            ['type' => 'doc', 'content' => [['type' => 'table', 'content' => array_fill(0, 51, $row)]]],
            ['type' => 'doc', 'content' => [['type' => 'table', 'content' => [[
                'type' => 'tableRow', 'content' => array_fill(0, 7, $cell),
            ]]]]],
            ['type' => 'doc', 'content' => [['type' => 'table', 'content' => [[
                'type' => 'tableRow', 'content' => [[
                    'type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [[
                        'type' => 'image', 'attrs' => ['assetId' => $image->public_id],
                    ]]]],
                ]],
            ]]]]],
        ];

        foreach ($invalidDocuments as $document) {
            $this->actingAs($user)->put(route('inspections.general-aspects.update', $inspection), [
                'schema_version' => 2, 'document' => $document,
            ])->assertSessionHasErrors('document');
        }
    }

    public function test_removed_images_are_cleaned_after_seven_days_but_processing_images_survive(): void
    {
        Storage::fake('inspection_photos');
        [$user, $inspection] = $this->scenario();
        $image = $this->image($inspection, PhotoProcessingStatus::Ready);
        $pending = $this->image($inspection, PhotoProcessingStatus::Processing);
        Storage::disk('inspection_photos')->put($image->optimized_path, 'image');

        $this->actingAs($user)->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => $this->document($image->public_id),
        ])->assertRedirect();
        $image->refresh()->update(['unreferenced_at' => now()->subDays(8)]);
        $this->artisan('general-aspects:cleanup-images')->assertExitCode(0);
        $this->assertDatabaseHas('inspection_general_aspect_images', ['id' => $image->id, 'unreferenced_at' => null]);

        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Outro texto']]]]],
        ])->assertRedirect();
        $image->refresh()->update(['unreferenced_at' => now()->subDays(8)]);
        $pending->update(['unreferenced_at' => now()->subDays(8)]);
        $this->artisan('general-aspects:cleanup-images')->assertExitCode(0);
        $this->assertDatabaseMissing('inspection_general_aspect_images', ['id' => $image->id]);
        $this->assertDatabaseHas('inspection_general_aspect_images', ['id' => $pending->id]);
    }

    /** @return array{User, Inspection} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Approver]);

        return [$user, $inspection];
    }

    private function image(Inspection $inspection, PhotoProcessingStatus $status): InspectionGeneralAspectImage
    {
        return InspectionGeneralAspectImage::query()->create([
            'organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id,
            'processing_status' => $status, 'disk' => 'inspection_photos',
            'original_name' => 'estrutura.jpg', 'original_mime_type' => 'image/jpeg', 'original_size' => 100,
            'optimized_path' => 'tests/optimized.webp', 'thumbnail_path' => 'tests/thumbnail.webp',
            'uploaded_at' => now(), 'unreferenced_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function document(string $id): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Estrutura geral']]],
            ['type' => 'image', 'attrs' => ['assetId' => $id]],
        ]];
    }
}
