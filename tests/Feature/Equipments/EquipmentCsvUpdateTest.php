<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentCsvUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_mode_previews_and_applies_only_nonempty_changed_fields(): void
    {
        [$admin, $client] = $this->context();
        $changed = Equipment::factory()->inStructure($client)->create([
            'maintenance_item_code' => '000123',
            'description' => 'Antiga',
            'area_name' => 'Área antiga',
            'numero_interno' => 'INTERNO-1',
        ]);
        $unchanged = Equipment::factory()->inStructure($client)->create([
            'maintenance_item_code' => '000124',
            'description' => 'Preservada',
        ]);
        $beforeCount = Equipment::count();
        $csv = implode("\n", [
            'Item manutenção;Descrição item de manutenção;Area.nome;Número interno',
            '000123;Nova descrição;;INTERNO-2',
            '000124;;;',
            '000999;Inexistente;;',
        ]);

        [$token, $reviewUrl] = $this->plan($admin, $csv, [
            'maintenance_item_code' => 'column_0',
            'description' => 'column_1',
            'area_name' => 'column_2',
            'numero_interno' => 'column_3',
        ]);

        $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'update')
            ->where('review.summary.total', 3)
            ->where('review.summary.ready', 1)
            ->where('review.summary.unchanged', 1)
            ->where('review.summary.rejected', 1)
            ->where('review.rows.0.changes.0.field', 'description')
            ->where('review.rows.0.changes.0.from', 'Antiga')
            ->where('review.rows.0.changes.0.to', 'Nova descrição')
            ->where('review.rows.2.status', 'rejected'));
        $this->assertSame('Antiga', $changed->fresh()->description);

        $result = $this->post(route('equipments.import.update.confirm'), ['token' => $token]);
        $this->get($this->redirectLocation($result))->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'update')
            ->where('result.total', 3)
            ->where('result.updated', 1)
            ->where('result.unchanged', 1)
            ->has('result.rejected', 1));

        $changed->refresh();
        $this->assertSame('Nova descrição', $changed->description);
        $this->assertSame('Área antiga', $changed->area_name);
        $this->assertSame('INTERNO-2', $changed->numero_interno);
        $this->assertSame($admin->id, $changed->updated_by);
        $this->assertSame('Preservada', $unchanged->fresh()->description);
        $this->assertSame($beforeCount, Equipment::count());
        $this->post(route('equipments.import.update.confirm'), ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_update_mapping_requires_the_item_and_another_field_and_cannot_use_the_create_endpoint(): void
    {
        [$admin] = $this->context();
        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'mode' => 'update',
            'file' => UploadedFile::fake()->createWithContent('itens.csv', "Item manutenção;TAG\n000123;NOVA"),
        ]);
        $token = $this->tokenFromUrl($this->redirectLocation($preview), 'preview');

        $this->post(route('equipments.import.update.plan'), [
            'token' => $token,
            'mapping' => ['tag' => 'column_1'],
        ])->assertSessionHasErrors('mapping.maintenance_item_code');
        $this->post(route('equipments.import.update.plan'), [
            'token' => $token,
            'mapping' => ['maintenance_item_code' => 'column_0'],
        ])->assertSessionHasErrors('mapping');
        $this->post(route('equipments.import.confirm'), [
            'token' => $token,
            'mapping' => ['maintenance_item_code' => 'column_0', 'tag' => 'column_1'],
        ])->assertSessionHasErrors('token');
        $this->assertSame(0, Equipment::count());
    }

    public function test_update_rejects_duplicate_missing_deleted_and_other_organization_items(): void
    {
        [$admin, $client] = $this->context();
        $duplicate = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'DUP']);
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'DELETED'])->delete();
        $otherOrganization = Organization::factory()->create();
        $otherClient = Client::factory()->for($otherOrganization)->create();
        $other = Equipment::factory()->inStructure($otherClient)->create(['maintenance_item_code' => 'OTHER']);
        $csv = implode("\n", [
            'Item manutenção;Descrição item de manutenção',
            'DUP;Primeira',
            ' dup ;Segunda',
            'DELETED;Excluído',
            'OTHER;Outra organização',
            'MISSING;Inexistente',
        ]);

        [$token, $reviewUrl] = $this->plan($admin, $csv, [
            'maintenance_item_code' => 'column_0', 'description' => 'column_1',
        ]);
        $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('review.summary.ready', 0)
            ->where('review.summary.rejected', 5)
            ->where('review.rows.0.reason', 'Item manutenção repetido no CSV.')
            ->where('review.rows.1.reason', 'Item manutenção repetido no CSV.'));
        $this->post(route('equipments.import.update.confirm'), ['token' => $token])
            ->assertRedirect();
        $this->assertNull($duplicate->fresh()->description);
        $this->assertNull($other->fresh()->description);
    }

    public function test_update_rejects_identifier_conflict_and_stale_preview_without_blocking_other_rows(): void
    {
        [$admin, $client] = $this->context();
        $conflict = Equipment::factory()->inStructure($client)->create([
            'maintenance_item_code' => 'ONE', 'numero_interno' => 'INTERNAL-ONE',
        ]);
        $owner = Equipment::factory()->inStructure($client)->create([
            'maintenance_item_code' => 'TWO', 'numero_interno' => 'INTERNAL-TWO',
        ]);
        $stale = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'THREE']);
        $valid = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'FOUR']);
        $csv = implode("\n", [
            'Item manutenção;Descrição item de manutenção;Número interno',
            'ONE;Conflito;INTERNAL-TWO',
            'THREE;Prévia antiga;',
            'FOUR;Atualizada;',
        ]);

        [$token, $reviewUrl] = $this->plan($admin, $csv, [
            'maintenance_item_code' => 'column_0', 'description' => 'column_1', 'numero_interno' => 'column_2',
        ]);
        $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('review.summary.ready', 2)
            ->where('review.summary.rejected', 1));
        $stale->update(['description' => 'Alterada depois da prévia']);

        $result = $this->post(route('equipments.import.update.confirm'), ['token' => $token]);
        $this->get($this->redirectLocation($result))->assertInertia(fn (Assert $page) => $page
            ->where('result.updated', 1)
            ->has('result.rejected', 2)
            ->where('result.rejected.1.reason', 'O equipamento mudou desde a prévia. Envie o CSV novamente.'));
        $this->assertNull($conflict->fresh()->description);
        $this->assertSame('INTERNAL-TWO', $owner->fresh()->numero_interno);
        $this->assertSame('Alterada depois da prévia', $stale->fresh()->description);
        $this->assertSame('Atualizada', $valid->fresh()->description);
    }

    public function test_linked_records_require_explicit_confirmation_and_keep_their_snapshot(): void
    {
        [$admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'LINKED']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'context_snapshot' => ['equipment' => ['name' => 'Antigo']],
        ]);
        $snapshot = $inspection->context_snapshot;
        [$token, $reviewUrl] = $this->plan($admin, "Item manutenção;Denominação do loc.instalação\nLINKED;Novo", [
            'maintenance_item_code' => 'column_0', 'name' => 'column_1',
        ]);
        $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page->where('review.summary.related', 1));

        $this->post(route('equipments.import.update.confirm'), ['token' => $token])
            ->assertSessionHasErrors('confirm_related_records_edit');
        $this->assertNotSame('Novo', $equipment->fresh()->name);

        $result = $this->post(route('equipments.import.update.confirm'), [
            'token' => $token, 'confirm_related_records_edit' => true,
        ]);
        $this->get($this->redirectLocation($result))->assertInertia(fn (Assert $page) => $page->where('result.updated', 1));
        $this->assertSame('Novo', $equipment->fresh()->name);
        $this->assertSame($snapshot, $inspection->fresh()->context_snapshot);
    }

    public function test_update_preview_rejects_repeated_new_unique_identifiers_in_the_csv(): void
    {
        [$admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'FIRST']);
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'SECOND']);
        [, $reviewUrl] = $this->plan($admin, implode("\n", [
            'Item manutenção;Número interno',
            'FIRST;NEW-INTERNAL',
            'SECOND;NEW-INTERNAL',
        ]), [
            'maintenance_item_code' => 'column_0', 'numero_interno' => 'column_1',
        ]);

        $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('review.summary.ready', 0)
            ->where('review.summary.rejected', 2)
            ->where('review.rows.0.reason', 'Número interno repetido no CSV.')
            ->where('review.rows.1.reason', 'Número interno repetido no CSV.'));
    }

    public function test_only_the_uploading_administrator_can_review_and_confirm_an_update(): void
    {
        [$admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => 'PRIVATE']);
        [$token, $reviewUrl] = $this->plan($admin, "Item manutenção;Descrição item de manutenção\nPRIVATE;Nova", [
            'maintenance_item_code' => 'column_0', 'description' => 'column_1',
        ]);
        $otherAdmin = User::factory()->for($client->organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $this->actingAs($otherAdmin)->get($reviewUrl)->assertRedirect(route('equipments.import.create'));
        $this->post(route('equipments.import.update.confirm'), ['token' => $token])->assertSessionHasErrors('token');

        $member = User::factory()->for($client->organization)->create(['account_type' => UserAccountType::Member->value]);
        $this->actingAs($member)->get(route('equipments.import.create', ['mode' => 'update']))->assertForbidden();
        $this->post(route('equipments.import.update.confirm'), ['token' => $token])->assertForbidden();
        $this->assertNull($equipment->fresh()->description);
    }

    /** @return array{0:User,1:Client} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create();

        return [$admin, $client];
    }

    /** @param array<string, string> $mapping
     * @return array{0:string,1:string}
     */
    private function plan(User $admin, string $csv, array $mapping): array
    {
        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'mode' => 'update',
            'file' => UploadedFile::fake()->createWithContent('itens.csv', $csv),
        ]);
        $token = $this->tokenFromUrl($this->redirectLocation($preview), 'preview');
        $plan = $this->post(route('equipments.import.update.plan'), ['token' => $token, 'mapping' => $mapping]);

        return [$token, $this->redirectLocation($plan)];
    }

    private function redirectLocation($response): string
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertIsString($location);

        return $location;
    }

    private function tokenFromUrl(string $url, string $key): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = $query[$key] ?? null;
        $this->assertIsString($token);

        return $token;
    }
}
