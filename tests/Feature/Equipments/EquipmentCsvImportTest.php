<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\RegistrationStatus;
use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_previews_imports_valid_rows_and_reports_invalid_ones(): void
    {
        [$admin, $organization] = $this->context();
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;000123;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
            '000013;000124;;Descrição;LOC-02;U00;Geral;08;Pátio;Motor;GRP;T6;C;AV-002;SAM-002;SEND-002',
        ]);

        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $previewUrl = $this->redirectLocation($preview);

        $this->get($previewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('preview.total', 2)
            ->where('preview.mapping.defect_code_prefix', 'column_13')
            ->where('preview.mapping.numero_cliente', 'column_14')
            ->where('preview.mapping.numero_interno', 'column_15'));

        $token = $this->tokenFromUrl($previewUrl, 'preview');

        $confirmation = $this->post(route('equipments.import.confirm'), [
            'token' => $token,
            'mapping' => $this->mapping(),
        ]);
        $resultUrl = $this->redirectLocation($confirmation);
        $otherAdmin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $this->actingAs($otherAdmin)->get($resultUrl)->assertRedirect(route('equipments.import.create'));

        $this->actingAs($admin)->get($resultUrl)->assertInertia(fn (Assert $page) => $page
            ->where('result.total', 2)
            ->where('result.created', 1)
            ->has('result.rejected', 1)
            ->where('result.rejected.0.line', 3)
            ->where('result.rejected.0.reason', 'O campo Campo de ordenação (TAG) é obrigatório.'));

        $this->assertDatabaseHas('equipments', [
            'maintenance_item_code' => '000123',
            'defect_code_prefix' => 'AV-001',
            'numero_cliente' => 'SAM-001',
            'numero_interno' => 'SEND-001',
            'tag' => 'EQ-01',
        ]);
        $this->assertDatabaseMissing('equipments', ['maintenance_item_code' => '000124']);
    }

    public function test_prefix_mapping_is_required_and_members_cannot_import(): void
    {
        [$admin, $organization] = $this->context();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member->value]);
        $csv = implode("\n", [
            'Item manutenção;Campo de ordenação (TAG);Denominação do loc.instalação',
            '000123;EQ-01;Bomba',
        ]);

        $this->actingAs($member)->get(route('equipments.import.create'))->assertForbidden();

        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $previewUrl = $this->redirectLocation($preview);
        $this->get($previewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('preview.mapping.defect_code_prefix', null));

        $token = $this->tokenFromUrl($previewUrl, 'preview');
        $this->post(route('equipments.import.confirm'), [
            'token' => $token,
            'mapping' => $this->mapping(['defect_code_prefix' => null]),
        ])->assertSessionHasErrors('mapping.defect_code_prefix');
        $this->assertSame(0, Equipment::count());
    }

    public function test_import_reports_friendly_messages_for_required_and_too_long_fields(): void
    {
        [$admin] = $this->context();
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
            '000012;000124;;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-002;SAM-002;SEND-002',
            '000012;000125;EQ-03;Descrição;LOC-01;U00;Geral;08;Pátio;;GRP;T5;D;AV-003;SAM-003;SEND-003',
            '000012;000126;EQ-04;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;;SAM-004;SEND-004',
            '000012;000127;'.str_repeat('T', 121).';Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-005;SAM-005;SEND-005',
        ]);

        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);

        $confirmation = $this->post(route('equipments.import.confirm'), [
            'token' => $this->tokenFromUrl($this->redirectLocation($preview), 'preview'),
            'mapping' => $this->mapping(),
        ]);

        $this->get($this->redirectLocation($confirmation))->assertInertia(fn (Assert $page) => $page
            ->where('result.created', 0)
            ->has('result.rejected', 5)
            ->where('result.rejected.0.reason', 'O campo Item manutenção é obrigatório.')
            ->where('result.rejected.1.reason', 'O campo Campo de ordenação (TAG) é obrigatório.')
            ->where('result.rejected.2.reason', 'O campo Denominação do loc.instalação é obrigatório.')
            ->where('result.rejected.3.reason', 'O campo Prefixo de avaria é obrigatório.')
            ->where('result.rejected.4.reason', 'O campo Campo de ordenação (TAG) não pode ter mais que 120 caracteres.'));
    }

    public function test_import_upload_and_confirmation_errors_are_friendly(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->post(route('equipments.import.preview'))
            ->assertSessionHasErrors(['file' => 'Selecione um arquivo CSV para importar.']);

        $this->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->create('ativos.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['file' => 'Envie um arquivo CSV válido.']);

        $this->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->create('ativos.csv', 10241, 'text/csv'),
        ])->assertSessionHasErrors(['file' => 'O arquivo CSV deve ter no máximo 10 MB.']);

        $this->post(route('equipments.import.confirm'))
            ->assertSessionHasErrors([
                'token' => 'A prévia de importação expirou. Envie o arquivo novamente.',
                'mapping' => 'Confira o mapeamento das colunas antes de confirmar.',
            ]);
    }

    public function test_global_validation_messages_are_translated_to_portuguese(): void
    {
        $errors = Validator::make([
            'name' => '',
            'email' => 'inválido',
            'tag' => str_repeat('T', 121),
        ], [
            'name' => ['required'],
            'email' => ['email'],
            'tag' => ['max:120'],
        ])->errors()->all();

        $this->assertSame([
            'O campo nome é obrigatório.',
            'O campo e-mail deve conter um e-mail válido.',
            'O campo TAG não pode ter mais que 120 caracteres.',
        ], $errors);
        $this->assertDoesNotMatchRegularExpression('/^validation\./', implode(' ', $errors));
    }

    public function test_csv_duplicates_are_rejected_without_blocking_distinct_rows(): void
    {
        [$admin] = $this->context();
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;000123;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
            '000012;000123;EQ-02;Descrição;LOC-02;U00;Geral;08;Pátio;Bomba reserva;GRP;T5;D;AV-002;SAM-001;SEND-002',
            '000013;000124;EQ-03;Descrição;LOC-03;U00;Geral;08;Pátio;Motor;GRP;T6;C;AV-003;SAM-003;SEND-003',
        ]);

        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $token = $this->tokenFromUrl($this->redirectLocation($preview), 'preview');

        $confirmation = $this->post(route('equipments.import.confirm'), [
            'token' => $token,
            'mapping' => $this->mapping(),
        ]);

        $this->get($this->redirectLocation($confirmation))->assertInertia(fn (Assert $page) => $page
            ->where('result.created', 1)
            ->has('result.rejected', 2));

        $this->assertDatabaseHas('equipments', ['maintenance_item_code' => '000124']);
        $this->assertDatabaseMissing('equipments', ['maintenance_item_code' => '000123']);
    }

    public function test_manual_creation_requires_an_explicit_prefix(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->post(route('equipments.store'), [
            'numero_cliente' => 'SAM-001',
            'numero_interno' => 'SEND-001',
            'maintenance_item_code' => '000123',
            'tag' => 'EQ-01',
            'name' => 'Bomba',
        ])->assertSessionHasErrors('defect_code_prefix');
    }

    public function test_import_requires_the_automatic_client_to_be_active(): void
    {
        [$admin, $organization] = $this->context();
        Client::query()->where('organization_id', $organization->id)->sole()->update(['status' => RegistrationStatus::Inactive]);
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;000123;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
        ]);
        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $previewUrl = $this->redirectLocation($preview);

        $this->post(route('equipments.import.confirm'), [
            'token' => $this->tokenFromUrl($previewUrl, 'preview'),
            'mapping' => $this->mapping(),
        ])->assertSessionHasErrors('client');
        $this->assertSame(0, Equipment::count());
    }

    public function test_import_exposes_the_client_creation_action_when_the_client_is_missing(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($admin)
            ->get(route('equipments.import.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('client_action.url', route('clients.create'))
                ->where('client_action.label', 'Cadastrar cliente'));
    }

    public function test_import_reports_a_missing_client_without_discarding_the_preview(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;000123;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
        ]);

        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $previewUrl = $this->redirectLocation($preview);

        $this->post(route('equipments.import.confirm'), [
            'token' => $this->tokenFromUrl($previewUrl, 'preview'),
            'mapping' => $this->mapping(),
        ])->assertSessionHasErrors(['client' => 'Cadastre o cliente em Configurações antes de importar equipamentos.']);

        $this->get($previewUrl)->assertInertia(fn (Assert $page) => $page
            ->where('preview.total', 1)
            ->where('client_action.url', route('clients.create')));
    }

    public function test_import_exposes_the_client_management_action_when_the_client_is_inactive(): void
    {
        [$admin, $organization] = $this->context();
        $client = Client::query()->where('organization_id', $organization->id)->sole();
        $client->update(['status' => RegistrationStatus::Inactive]);

        $this->actingAs($admin)
            ->get(route('equipments.import.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('client_action.url', route('clients.show', $client))
                ->where('client_action.label', 'Gerenciar cliente'));
    }

    public function test_import_has_no_client_action_when_the_client_is_active(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)
            ->get(route('equipments.import.create'))
            ->assertInertia(fn (Assert $page) => $page->where('client_action', null));
    }

    public function test_preview_tokens_expire_and_legacy_preview_url_redirects_to_import_start(): void
    {
        [$admin] = $this->context();
        $csv = implode("\n", [
            'Plano de manutenção;Item manutenção;Campo de ordenação (TAG);Descrição item de manutenção;Local de instalação;Area(usina);Area.nome;Sub-area;sub-area.nome;Denominação do loc.instalação;GrpLisTar.;Numerador de grupos;Código ABC;Prefixo de avaria;Número do cliente;Número interno',
            '000012;000123;EQ-01;Descrição;LOC-01;U00;Geral;08;Pátio;Bomba;GRP;T5;D;AV-001;SAM-001;SEND-001',
        ]);
        $preview = $this->actingAs($admin)->post(route('equipments.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('ativos.csv', $csv),
        ]);
        $previewUrl = $this->redirectLocation($preview);
        $token = $this->tokenFromUrl($previewUrl, 'preview');

        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $this->actingAs($otherAdmin)->get($previewUrl)->assertRedirect(route('equipments.import.create'));

        Cache::forget("equipment-import-preview:{$token}");

        $this->actingAs($admin)->get($previewUrl)
            ->assertRedirect(route('equipments.import.create'))
            ->assertSessionHas('error');
        $this->get(route('equipments.import.preview.legacy'))
            ->assertRedirect(route('equipments.import.create'));
    }

    /** @return array{0:User, 1:Organization} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        Client::factory()->for($organization)->create();

        return [$admin, $organization];
    }

    /** @param array<string, ?string> $overrides
     * @return array<string, ?string>
     */
    private function mapping(array $overrides = []): array
    {
        return array_replace([
            'maintenance_plan_code' => 'column_0',
            'maintenance_item_code' => 'column_1',
            'tag' => 'column_2',
            'description' => 'column_3',
            'installation_location' => 'column_4',
            'area_code' => 'column_5',
            'area_name' => 'column_6',
            'subarea_code' => 'column_7',
            'subarea_name' => 'column_8',
            'name' => 'column_9',
            'task_list_group' => 'column_10',
            'task_list_group_counter' => 'column_11',
            'abc_code' => 'column_12',
            'defect_code_prefix' => 'column_13',
            'numero_cliente' => 'column_14',
            'numero_interno' => 'column_15',
        ], $overrides);
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
