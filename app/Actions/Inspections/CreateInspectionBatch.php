<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspectionBatch
{
    public function __construct(
        private readonly AssignInspectionResponsible $assignResponsible,
        private readonly CreateInspection $createInspection,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<int, array{equipment_id:int,service_order:?string,planned_start_on:string,planned_end_on:string,inspector_id:int}>  $records
     * @return Collection<int, Inspection>
     */
    public function handle(User $actor, array $records): Collection
    {
        $this->validateActor($actor);

        return DB::transaction(function () use ($actor, $records): Collection {
            $this->validateRecords($records, true);

            return collect($records)->map(function (array $record) use ($actor): Inspection {
                $equipment = Equipment::query()
                    ->forOrganization($this->tenant->id())
                    ->whereKey($record['equipment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $inspector = User::query()
                    ->where('organization_id', $this->tenant->id())
                    ->whereKey($record['inspector_id'])
                    ->firstOrFail();

                $inspection = $this->createInspection->handle($actor, $equipment, $record);
                $this->assignResponsible->handle(
                    $inspection,
                    $actor,
                    InspectionResponsibility::Preparer,
                    $actor,
                    true,
                );
                $this->assignResponsible->handle(
                    $inspection,
                    $inspector,
                    InspectionResponsibility::Reviewer,
                    $actor,
                    true,
                );

                return $inspection;
            });
        });
    }

    /** @param array<int, array{equipment_id:int,service_order:?string,planned_start_on:string,planned_end_on:string,inspector_id:int}> $records */
    private function validateRecords(array $records, bool $lock = false): void
    {
        $errors = [];
        $seenEquipment = [];

        foreach ($records as $index => $record) {
            $equipmentId = (int) $record['equipment_id'];

            if (isset($seenEquipment[$equipmentId])) {
                $errors["inspections.$index.equipment_id"] = 'O equipamento está repetido neste planejamento.';

                continue;
            }

            $seenEquipment[$equipmentId] = true;

            $equipmentQuery = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->with('client')
                ->whereKey($equipmentId);

            if ($lock) {
                $equipmentQuery->lockForUpdate();
            }

            $equipment = $equipmentQuery->first();

            if ($equipment === null || ! $equipment->canReceiveInspection()) {
                $errors["inspections.$index.equipment_id"] = 'O equipamento não está apto para receber uma inspeção.';
            } elseif (Inspection::query()
                ->where('organization_id', $this->tenant->id())
                ->where('equipment_id', $equipment->getKey())
                ->whereNotIn('status', [InspectionStatus::Released->value, InspectionStatus::Canceled->value])
                ->exists()) {
                $errors["inspections.$index.equipment_id"] = 'O equipamento já possui uma inspeção aberta.';
            }

            $inspector = User::query()
                ->where('organization_id', $this->tenant->id())
                ->whereKey((int) $record['inspector_id'])
                ->first();

            if ($inspector === null || ! $inspector->isActive() || $inspector->operational_role !== OperationalRole::Inspector) {
                $errors["inspections.$index.inspector_id"] = 'Selecione um Inspetor ativo da organização.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateActor(User $actor): void
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id()) || $actor->operational_role !== OperationalRole::Planner) {
            throw ValidationException::withMessages([
                'actor' => 'Somente usuários ativos com papel Planejador podem criar inspeções.',
            ]);
        }
    }
}
