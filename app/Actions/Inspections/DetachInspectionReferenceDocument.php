<?php

namespace App\Actions\Inspections;

use App\Models\Inspection;
use App\Models\User;
use App\Policies\InspectionPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DetachInspectionReferenceDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InspectionPolicy $policy,
    ) {}

    public function handle(User $actor, int $inspectionId, int $equipmentDocumentId): void
    {
        $organizationId = $this->tenantContext->id();

        DB::transaction(function () use ($actor, $inspectionId, $equipmentDocumentId, $organizationId): void {
            $inspection = Inspection::query()
                ->forOrganization($organizationId)
                ->lockForUpdate()
                ->find($inspectionId);

            if ($inspection === null) {
                throw ValidationException::withMessages(['inspection_id' => 'A inspeção não pertence à organização atual.']);
            }

            if (! $this->policy->manageReferenceDocuments($actor, $inspection)) {
                throw new AuthorizationException('O usuário não pode alterar os documentos de referência desta inspeção.');
            }

            if (! $inspection->status->allowsReferenceDocumentChanges()) {
                throw ValidationException::withMessages([
                    'inspection_id' => 'Documentos de referência não podem ser alterados após o fechamento técnico.',
                ]);
            }

            $reference = $inspection->referenceDocuments()
                ->where('organization_id', $organizationId)
                ->where('equipment_document_id', $equipmentDocumentId)
                ->first();

            if ($reference === null) {
                throw ValidationException::withMessages([
                    'equipment_document_id' => 'O documento não está vinculado à inspeção.',
                ]);
            }

            $reference->delete();
        });
    }
}
