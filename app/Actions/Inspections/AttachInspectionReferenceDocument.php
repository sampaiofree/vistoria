<?php

namespace App\Actions\Inspections;

use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\User;
use App\Policies\InspectionPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachInspectionReferenceDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InspectionPolicy $policy,
    ) {}

    public function handle(User $actor, int $inspectionId, int $equipmentDocumentId): InspectionReferenceDocument
    {
        $organizationId = $this->tenantContext->id();

        return DB::transaction(function () use ($actor, $inspectionId, $equipmentDocumentId, $organizationId): InspectionReferenceDocument {
            $inspection = Inspection::query()
                ->forOrganization($organizationId)
                ->lockForUpdate()
                ->find($inspectionId);

            $document = EquipmentDocument::query()
                ->forOrganization($organizationId)
                ->find($equipmentDocumentId);

            if ($inspection === null) {
                throw ValidationException::withMessages(['inspection_id' => 'A inspeção não pertence à organização atual.']);
            }

            if ($document === null) {
                throw ValidationException::withMessages(['equipment_document_id' => 'O documento não pertence à organização atual.']);
            }

            if (! $this->policy->manageReferenceDocuments($actor, $inspection)) {
                throw new AuthorizationException('O usuário não pode alterar os documentos de referência desta inspeção.');
            }

            $this->ensureReferencesAreMutable($inspection);

            if ((int) $document->equipment_id !== (int) $inspection->equipment_id) {
                throw ValidationException::withMessages(['equipment_document_id' => 'O documento não pertence ao equipamento inspecionado.']);
            }

            if ($inspection->referenceDocuments()->where('equipment_document_id', $document->id)->exists()) {
                throw $this->duplicateValidationException();
            }

            try {
                return $inspection->referenceDocuments()->create([
                    'organization_id' => $organizationId,
                    // Store the concrete revision id; document_group is intentionally not copied.
                    'equipment_document_id' => $document->id,
                    'added_by' => $actor->id,
                    'created_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ($inspection->referenceDocuments()->where('equipment_document_id', $document->id)->exists()) {
                    throw $this->duplicateValidationException();
                }

                throw $exception;
            }
        });
    }

    private function ensureReferencesAreMutable(Inspection $inspection): void
    {
        if (! $inspection->status->allowsReferenceDocumentChanges()) {
            throw ValidationException::withMessages([
                'inspection_id' => 'Documentos de referência não podem ser alterados após o fechamento técnico.',
            ]);
        }
    }

    private function duplicateValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            'equipment_document_id' => 'Este documento já está vinculado à inspeção.',
        ]);
    }
}
