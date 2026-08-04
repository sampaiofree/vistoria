<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Enums\EquipmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OrganizationStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCodeSequence;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use App\Services\Demo\ViewFirstCivilScenario;
use App\Services\Defects\DefectSnapshotBuilder;
use App\Services\Inspections\InspectionSnapshotBuilder;
use App\Support\TextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ViewFirstDemoSeeder extends Seeder
{
    public const ORGANIZATION_DOCUMENT = '21798932000100';

    public const EQUIPMENT_TAG = 'U03-06VT002';

    public const PREVIOUS_INSPECTION_SERVICE_ORDER = '3500762191';

    public const CURRENT_INSPECTION_SERVICE_ORDER = '3500874310';

    public const DOCUMENT_NUMBER = 'T000000-S-2PO006';

    private const DOCUMENT_REVISION = 'R-04';

    private const DOCUMENT_FIXTURE = 'seeders/fixtures/view-first-demo-procedure.pdf';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('O cenário View First só pode ser carregado nos ambientes local e testing.');
        }

        $fixture = file_get_contents(database_path(self::DOCUMENT_FIXTURE));

        if ($fixture === false || $fixture === '') {
            throw new RuntimeException('O documento técnico da demonstração não pôde ser lido.');
        }

        DB::transaction(function () use ($fixture): void {
            $organization = $this->seedOrganization();
            $users = $this->seedUsers($organization);
            [$client, $unit, $area, $subarea] = $this->seedOperationalStructure($organization);
            $equipment = $this->seedEquipment(
                $organization,
                $client,
                $unit,
                $area,
                $subarea,
                $users['demo'],
            );

            $document = $this->seedDocument($organization, $equipment, $users['demo'], $fixture);
            [$previousInspection, $currentInspection] = $this->seedInspections(
                $organization,
                $equipment,
                $users['demo'],
            );

            $this->seedInspectionHistories($organization, $previousInspection, $currentInspection, $users);
            $this->seedResponsibles($organization, $previousInspection, $currentInspection, $users);
            $this->seedReferenceDocuments(
                $organization,
                $previousInspection,
                $currentInspection,
                $document,
                $users['demo'],
            );
            $this->seedDefects(
                $organization,
                $equipment,
                $previousInspection,
                $currentInspection,
                $users['inspector'],
            );
        });
    }

    private function seedOrganization(): Organization
    {
        $organization = Organization::withTrashed()->firstOrNew([
            'document' => self::ORGANIZATION_DOCUMENT,
        ]);

        $this->restoreIfTrashed($organization);
        $this->ensurePublicId($organization);

        $organization->fill([
            'name' => 'Vistoria Serviços de Inspeção Ltda.',
            'legal_name' => 'Vistoria Serviços de Inspeção Ltda.',
            'timezone' => 'America/Sao_Paulo',
            'status' => OrganizationStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        return $organization;
    }

    /**
     * @return array{demo: User, inspector: User, reviewer: User, approver: User, releaser: User, admin: User, member: User, superadmin: User}
     */
    private function seedUsers(Organization $organization): array
    {
        return [
            'demo' => $this->upsertUser('demo@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Ricardo Almeida — Diretor Técnico',
                'account_type' => UserAccountType::CompanyAdmin,
            ]),
            'inspector' => $this->upsertUser('mariana.costa@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Mariana Costa — Inspetora Civil',
                'account_type' => UserAccountType::Member,
            ]),
            'reviewer' => $this->upsertUser('ana.mendes@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Ana Paula Mendes — Revisora Técnica',
                'account_type' => UserAccountType::Member,
            ]),
            'approver' => $this->upsertUser('carlos.rocha@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Carlos Eduardo Rocha — Aprovador',
                'account_type' => UserAccountType::Member,
            ]),
            'releaser' => $this->upsertUser('juliana.martins@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Juliana Martins — Liberadora',
                'account_type' => UserAccountType::Member,
            ]),
            // Mantém as contas históricas de desenvolvimento para não quebrar o fluxo local existente.
            'admin' => $this->upsertUser('admin@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Administrador da Empresa',
                'account_type' => UserAccountType::CompanyAdmin,
            ]),
            'member' => $this->upsertUser('member@vistoria.test', [
                'organization_id' => $organization->id,
                'name' => 'Membro da Empresa',
                'account_type' => UserAccountType::Member,
            ]),
            'superadmin' => $this->upsertUser('superadmin@vistoria.test', [
                'organization_id' => null,
                'name' => 'Superadministrador',
                'account_type' => UserAccountType::SuperAdmin,
            ]),
        ];
    }

    private function upsertUser(string $email, array $attributes): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $this->ensurePublicId($user);

        $user->fill([
            ...$attributes,
            'status' => UserStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        if (! $user->exists || ! Hash::check('password', (string) $user->password)) {
            $user->password = 'password';
        }

        $user->email_verified_at ??= '2026-01-01 09:00:00';
        $user->save();

        return $user;
    }

    /**
     * @return array{Client, ClientUnit, Area, Subarea}
     */
    private function seedOperationalStructure(Organization $organization): array
    {
        $client = Client::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            // Identidade já usada pelo seeder de desenvolvimento anterior; reaproveitá-la evita duplicação local.
            'document' => '11222333000144',
        ]);
        $this->restoreIfTrashed($client);
        $this->ensurePublicId($client);
        $client->fill([
            'name' => 'Samarco Mineração S.A.',
            'legal_name' => 'Samarco Mineração S.A.',
            'email' => 'contato@samarco.test',
            'phone' => '(27) 3361-9000',
            'status' => RegistrationStatus::Active,
            'notes' => 'Cliente do cenário oficial de demonstração View First.',
        ])->save();

        $unit = ClientUnit::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'normalized_code' => TextNormalizer::technicalCode('UBU'),
        ]);
        $this->restoreIfTrashed($unit);
        $this->ensurePublicId($unit);
        $unit->fill([
            'name' => 'Unidade de Ubu',
            'code' => 'UBU',
            'timezone' => 'America/Sao_Paulo',
            'address_line' => 'Rodovia ES-060, km 14',
            'address_number' => 's/n',
            'district' => 'Ubu',
            'postal_code' => '29230-000',
            'city' => 'Anchieta',
            'state' => 'ES',
            'country_code' => 'BR',
            'status' => RegistrationStatus::Active,
            'notes' => 'Unidade do cenário demonstrativo.',
        ])->save();

        $area = Area::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'client_unit_id' => $unit->id,
            'normalized_code' => TextNormalizer::technicalCode('USINA III'),
        ]);
        $this->restoreIfTrashed($area);
        $this->ensurePublicId($area);
        $area->fill([
            'name' => 'Usina III',
            'code' => 'USINA III',
            'status' => RegistrationStatus::Active,
            'description' => 'Área industrial do cenário demonstrativo.',
        ])->save();

        $subarea = Subarea::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'area_id' => $area->id,
            'normalized_code' => TextNormalizer::technicalCode('FORNO DE ENDURECIMENTO'),
        ]);
        $this->restoreIfTrashed($subarea);
        $this->ensurePublicId($subarea);
        $subarea->fill([
            'name' => 'Forno de Endurecimento',
            'code' => 'FORNO DE ENDURECIMENTO',
            'status' => RegistrationStatus::Active,
            'description' => 'Subárea do ventilador de processo.',
        ])->save();

        return [$client, $unit, $area, $subarea];
    }

    private function seedEquipment(
        Organization $organization,
        Client $client,
        ClientUnit $unit,
        Area $area,
        Subarea $subarea,
        User $actor,
    ): Equipment {
        $equipment = Equipment::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'client_unit_id' => $unit->id,
            'normalized_tag' => TextNormalizer::equipmentTag(self::EQUIPMENT_TAG),
        ]);
        $this->restoreIfTrashed($equipment);
        $this->ensurePublicId($equipment);
        $equipment->fill([
            'client_id' => $client->id,
            'area_id' => $area->id,
            'subarea_id' => $subarea->id,
            'tag' => self::EQUIPMENT_TAG,
            'defect_code_prefix' => 'VT009',
            'name' => 'Ventilador de processo',
            'description' => 'Conjunto motoventilador do forno de endurecimento.',
            'manufacturer' => 'WEG',
            'model' => 'VX-200',
            'serial_number' => 'SN-00000001',
            'asset_code' => 'ATV-U03-06VT002',
            'abc_code' => 'A',
            'installation_location' => 'Usina III — Forno de Endurecimento',
            'commissioned_at' => '2014-03-18',
            'status' => EquipmentStatus::Active,
            'notes' => 'Equipamento principal do cenário View First 06B.',
            'decommissioned_at' => null,
            'decommissioned_by' => null,
            'decommission_reason' => null,
            'created_by' => $equipment->created_by ?? $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        return $equipment;
    }

    private function seedDocument(
        Organization $organization,
        Equipment $equipment,
        User $actor,
        string $fixture,
    ): EquipmentDocument {
        $path = sprintf(
            'organizations/%d/equipments/%s/documents/view-first-demo/%s_%s.pdf',
            $organization->id,
            $equipment->public_id,
            self::DOCUMENT_NUMBER,
            self::DOCUMENT_REVISION,
        );

        if (! Storage::disk('equipment_documents')->put($path, $fixture)) {
            throw new RuntimeException('O documento técnico da demonstração não pôde ser restaurado.');
        }

        $document = EquipmentDocument::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'path' => $path,
        ]);
        $this->restoreIfTrashed($document);
        $this->ensurePublicId($document);
        $document->document_group ??= (string) Str::ulid();
        $document->fill([
            'document_type' => EquipmentDocumentType::Procedure,
            'title' => 'Procedimento técnico de inspeção CIVIL',
            'document_number' => self::DOCUMENT_NUMBER,
            'revision' => self::DOCUMENT_REVISION,
            'description' => 'Procedimento de referência utilizado nas duas inspeções do cenário demonstrativo.',
            'disk' => 'equipment_documents',
            'original_name' => 'T000000-S-2PO006_R-04.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => strlen($fixture),
            'checksum' => hash('sha256', $fixture),
            'is_current' => true,
            'status' => DocumentStatus::Active,
            'uploaded_by' => $actor->id,
            'issued_at' => '2024-04-15',
        ])->save();

        return $document;
    }

    /**
     * @return array{Inspection, Inspection}
     */
    private function seedInspections(
        Organization $organization,
        Equipment $equipment,
        User $actor,
    ): array {
        $snapshot = app(InspectionSnapshotBuilder::class)->build($equipment);

        $previous = $this->upsertInspection([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'service_order' => self::PREVIOUS_INSPECTION_SERVICE_ORDER,
        ], [
            'previous_inspection_id' => null,
            'number' => 'INS-2025-000001',
            'inspection_type' => InspectionType::Initial,
            'status' => InspectionStatus::Released,
            'external_report_number' => 'RL-CIV-U03-06VT002-00',
            'procedure_number' => self::DOCUMENT_NUMBER.'_'.self::DOCUMENT_REVISION,
            'atmospheric_classification' => 'C4',
            'scheduled_for' => '2025-08-12',
            'inspected_on' => '2025-08-12',
            'context_snapshot' => $snapshot,
            'snapshot_version' => InspectionSnapshotBuilder::VERSION,
            'general_notes' => 'Inspeção inicial liberada e utilizada como referência comparativa da reinspeção.',
            'started_at' => '2025-08-12 08:00:00',
            'field_completed_at' => '2025-08-12 17:10:00',
            'reviewed_at' => '2025-08-14 11:20:00',
            'approved_at' => '2025-08-15 15:40:00',
            'report_generated_at' => '2025-08-18 10:00:00',
            'released_at' => '2025-08-20 09:15:00',
            'canceled_at' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $current = $this->upsertInspection([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'service_order' => self::CURRENT_INSPECTION_SERVICE_ORDER,
        ], [
            'previous_inspection_id' => $previous->id,
            'number' => 'INS-2026-000002',
            'inspection_type' => InspectionType::Reinspection,
            'status' => InspectionStatus::InProgress,
            'external_report_number' => null,
            'procedure_number' => self::DOCUMENT_NUMBER.'_'.self::DOCUMENT_REVISION,
            'atmospheric_classification' => 'C4',
            'scheduled_for' => '2026-08-03',
            'inspected_on' => '2026-08-03',
            'context_snapshot' => $snapshot,
            'snapshot_version' => InspectionSnapshotBuilder::VERSION,
            'general_notes' => 'Reinspeção oficial de demonstração, com treze de quatorze avaliações concluídas e um rascunho em aberto.',
            'started_at' => '2026-08-03 08:10:00',
            'field_completed_at' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'report_generated_at' => null,
            'released_at' => null,
            'canceled_at' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return [$previous, $current];
    }

    private function upsertInspection(array $identity, array $attributes): Inspection
    {
        $inspection = Inspection::query()->firstOrNew($identity);
        $this->ensurePublicId($inspection);
        $inspection->fill($attributes)->save();

        return $inspection;
    }

    /**
     * @param  array{demo: User, inspector: User, reviewer: User, approver: User, releaser: User}  $users
     */
    private function seedInspectionHistories(
        Organization $organization,
        Inspection $previous,
        Inspection $current,
        array $users,
    ): void {
        InspectionStatusHistory::query()
            ->where('organization_id', $organization->id)
            ->whereIn('inspection_id', [$previous->id, $current->id])
            ->delete();

        $previousHistory = [
            [null, InspectionStatus::Planned, $users['demo'], 'Inspeção inicial planejada.', '2025-06-02 09:00:00'],
            [InspectionStatus::Planned, InspectionStatus::InProgress, $users['inspector'], 'Atividade de campo iniciada.', '2025-08-12 08:00:00'],
            [InspectionStatus::InProgress, InspectionStatus::AwaitingReview, $users['demo'], 'Levantamento de campo concluído.', '2025-08-13 09:30:00'],
            [InspectionStatus::AwaitingReview, InspectionStatus::AwaitingApproval, $users['reviewer'], 'Revisão técnica concluída.', '2025-08-14 11:20:00'],
            [InspectionStatus::AwaitingApproval, InspectionStatus::Approved, $users['approver'], 'Inspeção aprovada.', '2025-08-15 15:40:00'],
            [InspectionStatus::Approved, InspectionStatus::ReportGenerated, $users['demo'], 'Relatório técnico consolidado.', '2025-08-18 10:00:00'],
            [InspectionStatus::ReportGenerated, InspectionStatus::Released, $users['releaser'], 'Relatório liberado ao cliente.', '2025-08-20 09:15:00'],
        ];

        foreach ($previousHistory as [$from, $to, $actor, $reason, $createdAt]) {
            $this->createHistory($organization, $previous, $from, $to, $actor, $reason, $createdAt);
        }

        $this->createHistory(
            $organization,
            $current,
            null,
            InspectionStatus::Planned,
            $users['demo'],
            'Reinspeção anual planejada.',
            '2026-07-20 10:30:00',
        );
        $this->createHistory(
            $organization,
            $current,
            InspectionStatus::Planned,
            InspectionStatus::InProgress,
            $users['inspector'],
            'Reinspeção iniciada em campo.',
            '2026-08-03 08:10:00',
        );
    }

    private function createHistory(
        Organization $organization,
        Inspection $inspection,
        ?InspectionStatus $from,
        InspectionStatus $to,
        User $actor,
        string $reason,
        string $createdAt,
    ): void {
        InspectionStatusHistory::query()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor->id,
            'reason' => $reason,
            'metadata' => ['source' => 'view-first-demo'],
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @param  array{demo: User, inspector: User, reviewer: User, approver: User, releaser: User}  $users
     */
    private function seedResponsibles(
        Organization $organization,
        Inspection $previous,
        Inspection $current,
        array $users,
    ): void {
        $assignments = [
            InspectionResponsibility::Inspector->value => $users['inspector'],
            InspectionResponsibility::Preparer->value => $users['demo'],
            InspectionResponsibility::Reviewer->value => $users['reviewer'],
            InspectionResponsibility::Approver->value => $users['approver'],
            InspectionResponsibility::Releaser->value => $users['releaser'],
        ];

        foreach ([$previous, $current] as $inspection) {
            $keptIds = [];

            foreach ($assignments as $responsibility => $user) {
                $responsible = InspectionResponsible::query()->updateOrCreate([
                    'organization_id' => $organization->id,
                    'inspection_id' => $inspection->id,
                    'user_id' => $user->id,
                    'responsibility' => $responsibility,
                ], [
                    'is_primary' => true,
                    'assigned_by' => $users['demo']->id,
                    'assigned_at' => $inspection->is($previous)
                        ? '2025-06-02 09:15:00'
                        : '2026-07-20 10:45:00',
                    'completed_at' => $inspection->is($previous)
                        ? '2025-08-20 09:15:00'
                        : null,
                ]);
                $keptIds[] = $responsible->id;
            }

            InspectionResponsible::query()
                ->where('organization_id', $organization->id)
                ->where('inspection_id', $inspection->id)
                ->whereNotIn('id', $keptIds)
                ->delete();
        }
    }

    private function seedReferenceDocuments(
        Organization $organization,
        Inspection $previous,
        Inspection $current,
        EquipmentDocument $document,
        User $actor,
    ): void {
        foreach ([$previous, $current] as $inspection) {
            InspectionReferenceDocument::query()->updateOrCreate([
                'organization_id' => $organization->id,
                'inspection_id' => $inspection->id,
                'equipment_document_id' => $document->id,
            ], [
                'added_by' => $actor->id,
                'created_at' => $inspection->is($previous)
                    ? '2025-06-02 09:20:00'
                    : '2026-07-20 10:50:00',
            ]);
        }
    }

    private function seedDefects(
        Organization $organization,
        Equipment $equipment,
        Inspection $previous,
        Inspection $current,
        User $inspector,
    ): void {
        $definitions = $this->defectDefinitions();

        foreach ($definitions as $definition) {
            $sequence = (int) $definition['sequence'];
            $isNew = $definition['current_condition'] === DefectAssessmentCondition::New->value;
            $defect = Defect::query()->firstOrNew([
                'organization_id' => $organization->id,
                'code' => $definition['code'],
            ]);
            $this->ensurePublicId($defect);
            $defect->fill([
                'equipment_id' => $equipment->id,
                'first_inspection_id' => $isNew ? $current->id : $previous->id,
                'category' => DefectCategory::Civil,
                'sequence_number' => $sequence,
                'title' => $definition['title'],
                'origin_description' => $definition['origin_description'],
                'status' => $definition['current_condition'] === DefectAssessmentCondition::Repaired->value
                    ? DefectStatus::Repaired
                    : DefectStatus::Active,
                'repaired_at' => $definition['current_condition'] === DefectAssessmentCondition::Repaired->value
                    ? '2026-08-03 15:10:00'
                    : null,
                'archived_at' => null,
                'created_by' => $inspector->id,
                'updated_by' => $inspector->id,
            ])->save();

            $previousAssessment = null;

            if (! $isNew) {
                $snapshot = app(DefectSnapshotBuilder::class)->build($defect);
                $snapshot['defect']['status'] = DefectStatus::Active->value;

                $previousAssessment = $this->upsertAssessment($defect, $previous, [
                    'previous_assessment_id' => null,
                    'condition' => DefectAssessmentCondition::New,
                    'status' => DefectAssessmentStatus::Complete,
                    'location_description' => $definition['previous_location'] ?? null,
                    'comment' => $definition['previous_comment'] ?? null,
                    'recommendation' => $definition['previous_recommendation'] ?? null,
                    'reason' => null,
                    'internal_notes' => null,
                    'defect_snapshot' => $snapshot,
                    'snapshot_version' => DefectSnapshotBuilder::VERSION,
                    'assessed_at' => CarbonImmutable::parse('2025-08-12 11:00:00')
                        ->addMinutes(($sequence - 1) * 7)
                        ->format('Y-m-d H:i:s'),
                    'created_by' => $inspector->id,
                    'updated_by' => $inspector->id,
                ]);
            }

            $isDraft = (bool) ($definition['draft'] ?? false);
            $this->upsertAssessment($defect, $current, [
                'previous_assessment_id' => $previousAssessment?->id,
                'condition' => $definition['current_condition'],
                'status' => $isDraft
                    ? DefectAssessmentStatus::Draft
                    : DefectAssessmentStatus::Complete,
                'location_description' => $definition['current_location'] ?? null,
                'comment' => $definition['current_comment'] ?? null,
                'recommendation' => $definition['current_recommendation'] ?? null,
                'reason' => $definition['reason'] ?? null,
                'internal_notes' => null,
                'defect_snapshot' => $isDraft
                    ? null
                    : app(DefectSnapshotBuilder::class)->build($defect),
                'snapshot_version' => DefectSnapshotBuilder::VERSION,
                'assessed_at' => $isDraft
                    ? null
                    : CarbonImmutable::parse('2026-08-03 11:30:00')
                        ->addMinutes(($sequence - 1) * 7)
                        ->format('Y-m-d H:i:s'),
                'created_by' => $inspector->id,
                'updated_by' => $inspector->id,
            ]);
        }

        $sequence = DefectCodeSequence::query()->firstOrNew([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'category' => DefectCategory::Civil->value,
        ]);
        $sequence->last_number = max((int) $sequence->last_number, count($definitions));
        $sequence->save();
    }

    private function upsertAssessment(
        Defect $defect,
        Inspection $inspection,
        array $attributes,
    ): DefectAssessment {
        $assessment = DefectAssessment::query()->firstOrNew([
            'organization_id' => $defect->organization_id,
            'defect_id' => $defect->id,
            'inspection_id' => $inspection->id,
        ]);
        $this->ensurePublicId($assessment);
        $assessment->fill([
            'equipment_id' => $defect->equipment_id,
            ...$attributes,
        ])->save();

        return $assessment;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defectDefinitions(): array
    {
        return ViewFirstCivilScenario::findings();
    }

    private function restoreIfTrashed(Model $model): void
    {
        if (method_exists($model, 'trashed') && $model->exists && $model->trashed()) {
            $model->restore();
        }
    }

    private function ensurePublicId(Model $model): void
    {
        if (! $model->exists && blank($model->getAttribute('public_id'))) {
            $model->setAttribute('public_id', (string) Str::ulid());
        }
    }
}
