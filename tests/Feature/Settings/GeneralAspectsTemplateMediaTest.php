<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\PhotoProcessingStatus;
use App\Enums\UserAccountType;
use App\Jobs\ProcessGeneralAspectsTemplateImage;
use App\Models\Equipment;
use App\Models\GeneralAspectsTemplate;
use App\Models\GeneralAspectsTemplateImage;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class GeneralAspectsTemplateMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_image_is_processed_copied_to_inspection_and_survives_template_deletion(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$admin, $reviewer, $inspection] = $this->scenario();
        $token = (string) Str::ulid();
        $upload = $this->actingAs($admin)->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token,
            'file' => UploadedFile::fake()->image('modelo.jpg', 1200, 800),
        ])->assertStatus(202)->assertJsonPath('status', 'pending');
        $id = $upload->json('assetId');
        $source = GeneralAspectsTemplateImage::query()->where('public_id', $id)->firstOrFail();
        Queue::assertPushed(ProcessGeneralAspectsTemplateImage::class);

        $document = $this->imageDocument($id);
        $this->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Modelo com imagem', 'schema_version' => 2,
            'draft_token' => $token, 'document' => $document,
        ])->assertSessionHasErrors('document');
        (new ProcessGeneralAspectsTemplateImage($source->id))->handle();
        $this->assertSame(PhotoProcessingStatus::Ready, $source->refresh()->processing_status);
        $this->get(route('settings.inspection-report.general-aspects.images.show', [$source, 'thumbnail']))->assertOk();

        $this->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Modelo com imagem', 'schema_version' => 2,
            'draft_token' => $token, 'document' => $document,
        ])->assertRedirect();
        $template = GeneralAspectsTemplate::query()->firstOrFail();
        $this->assertSame($template->id, $source->refresh()->template_id);
        $this->assertNull($source->unreferenced_at);
        $source->update(['unreferenced_at' => now()->subDays(8)]);
        $this->artisan('general-aspects:cleanup-images')->assertExitCode(0);
        $this->assertNull($source->refresh()->unreferenced_at);

        $optimized = Storage::disk('inspection_photos')->get($source->optimized_path);
        Storage::disk('inspection_photos')->delete($source->optimized_path);
        $this->actingAs($reviewer)->postJson(route('inspections.general-aspects.apply-template', [$inspection, $template]))
            ->assertUnprocessable()->assertJsonStructure(['errors' => ['template']]);
        $this->assertDatabaseCount('inspection_general_aspect_images', 0);
        Storage::disk('inspection_photos')->put($source->optimized_path, $optimized);

        $applied = $this->actingAs($reviewer)
            ->postJson(route('inspections.general-aspects.apply-template', [$inspection, $template]))
            ->assertOk()->json();
        $copyId = $applied['document']['content'][1]['attrs']['assetId'];
        $this->assertNotSame($id, $copyId);
        $copy = InspectionGeneralAspectImage::query()->where('public_id', $copyId)->firstOrFail();
        $this->assertSame(PhotoProcessingStatus::Ready, $copy->processing_status);
        $this->assertNotSame($source->optimized_path, $copy->optimized_path);
        Storage::disk('inspection_photos')->assertExists($copy->optimized_path);

        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => $applied['document'],
        ])->assertRedirect();
        $this->actingAs($admin)->delete(route('settings.inspection-report.general-aspects.destroy', $template))->assertRedirect();
        $this->assertDatabaseHas('inspection_general_aspect_images', ['id' => $copy->id, 'unreferenced_at' => null]);
        $source->refresh()->update(['unreferenced_at' => now()->subDays(8)]);
        $this->artisan('general-aspects:cleanup-images')->assertExitCode(0);
        $this->assertDatabaseMissing('general_aspects_template_images', ['id' => $source->id]);
        Storage::disk('inspection_photos')->assertExists($copy->optimized_path);
    }

    public function test_table_fields_and_manual_red_text_resolve_and_require_correction(): void
    {
        [$admin, $reviewer, $inspection] = $this->scenario();
        $document = ['type' => 'doc', 'content' => [[
            'type' => 'table', 'content' => [[
                'type' => 'tableRow', 'content' => [[
                    'type' => 'tableCell', 'content' => [[
                        'type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => 'TAG: '],
                            ['type' => 'equipmentField', 'attrs' => ['key' => 'tag']],
                            ['type' => 'text', 'text' => ' | Verificar', 'marks' => [[
                                'type' => 'textColor', 'attrs' => ['color' => '#DC2626'],
                            ]]],
                        ],
                    ]],
                ]],
            ]],
        ]]];
        $this->actingAs($admin)->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Tabela com campos', 'schema_version' => 2, 'document' => $document,
        ])->assertRedirect();
        $template = GeneralAspectsTemplate::query()->firstOrFail();
        $this->assertSame(2, $template->schema_version);

        $applied = $this->actingAs($reviewer)
            ->postJson(route('inspections.general-aspects.apply-template', [$inspection, $template]))
            ->assertOk()->json('document');
        $cellContent = $applied['content'][0]['content'][0]['content'][0]['content'][0]['content'];
        $this->assertSame($inspection->equipment->tag, $cellContent[1]['text']);
        $this->assertSame('#DC2626', $cellContent[2]['marks'][0]['attrs']['color']);
        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => $applied,
        ])->assertSessionHasErrors('document');
        unset($applied['content'][0]['content'][0]['content'][0]['content'][0]['content'][2]['marks']);
        $this->put(route('inspections.general-aspects.update', $inspection), [
            'schema_version' => 2, 'document' => $applied,
        ])->assertRedirect();
    }

    public function test_template_images_cannot_be_read_or_used_across_organizations(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$admin, $reviewer, $inspection] = $this->scenario();
        $token = (string) Str::ulid();
        $id = $this->actingAs($admin)->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token, 'file' => UploadedFile::fake()->image('privada.png', 800, 600),
        ])->assertStatus(202)->json('assetId');
        $image = GeneralAspectsTemplateImage::query()->where('public_id', $id)->firstOrFail();
        (new ProcessGeneralAspectsTemplateImage($image->id))->handle();

        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($otherAdmin)
            ->get(route('settings.inspection-report.general-aspects.images.show', [$image, 'optimized']))->assertNotFound();
        $this->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Uso indevido', 'schema_version' => 2,
            'draft_token' => $token, 'document' => $this->imageDocument($id),
        ])->assertSessionHasErrors('document');

        $foreignTemplate = GeneralAspectsTemplate::factory()->for($otherOrganization)->create();
        $this->actingAs($reviewer)->postJson(route('inspections.general-aspects.apply-template', [$inspection, $foreignTemplate]))
            ->assertNotFound();
        $this->assertDatabaseCount('inspection_general_aspect_images', 0);
    }

    public function test_upload_rejects_unsupported_or_oversized_files_and_failed_images_cannot_be_saved(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$admin] = $this->scenario();
        $token = (string) Str::ulid();

        $this->actingAs($admin)->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token, 'file' => UploadedFile::fake()->create('modelo.gif', 10, 'image/gif'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token, 'file' => UploadedFile::fake()->create('modelo.jpg', 25601, 'image/jpeg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $id = $this->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token, 'file' => UploadedFile::fake()->image('modelo.jpg', 900, 600),
        ])->assertStatus(202)->json('assetId');
        $image = GeneralAspectsTemplateImage::query()->where('public_id', $id)->firstOrFail();
        (new ProcessGeneralAspectsTemplateImage($image->id))->failed(new RuntimeException('Falha simulada'));
        $this->get(route('settings.inspection-report.general-aspects.images.status', $image))
            ->assertOk()->assertJsonPath('status', 'failed');
        $this->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Modelo inválido', 'schema_version' => 2,
            'draft_token' => $token, 'document' => $this->imageDocument($id),
        ])->assertSessionHasErrors('document');
    }

    public function test_image_from_another_template_cannot_be_reused_and_version_one_remains_editable(): void
    {
        Storage::fake('inspection_photos');
        Queue::fake();
        [$admin] = $this->scenario();
        $token = (string) Str::ulid();
        $id = $this->actingAs($admin)->postJson(route('settings.inspection-report.general-aspects.images.store'), [
            'draft_token' => $token, 'file' => UploadedFile::fake()->image('modelo.jpg', 900, 600),
        ])->assertStatus(202)->json('assetId');
        $image = GeneralAspectsTemplateImage::query()->where('public_id', $id)->firstOrFail();
        (new ProcessGeneralAspectsTemplateImage($image->id))->handle();
        $this->post(route('settings.inspection-report.general-aspects.store'), [
            'name' => 'Modelo origem', 'schema_version' => 2,
            'draft_token' => $token, 'document' => $this->imageDocument($id),
        ])->assertRedirect();

        $legacy = GeneralAspectsTemplate::factory()->for($admin->organization)->create([
            'schema_version' => 1,
            'document' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Modelo legado']],
            ]]],
        ]);
        $this->get(route('settings.inspection-report.general-aspects.edit', $legacy))
            ->assertOk()->assertInertia(fn ($page) => $page->where('template.schema_version', 1));
        $this->put(route('settings.inspection-report.general-aspects.update', $legacy), [
            'name' => 'Modelo legado', 'schema_version' => 2,
            'draft_token' => (string) Str::ulid(), 'document' => $this->imageDocument($id),
        ])->assertSessionHasErrors('document');
        $this->put(route('settings.inspection-report.general-aspects.update', $legacy), [
            'name' => 'Modelo legado', 'schema_version' => 2,
            'document' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Modelo legado editado']],
            ]]],
        ])->assertRedirect();
        $this->assertSame(2, $legacy->refresh()->schema_version);
    }

    /** @return array{User, User, Inspection} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)
            ->create(['responsibility' => InspectionResponsibility::Approver]);

        return [$admin, $reviewer, $inspection];
    }

    private function imageDocument(string $id): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Estrutura geral']]],
            ['type' => 'image', 'attrs' => ['assetId' => $id]],
        ]];
    }
}
