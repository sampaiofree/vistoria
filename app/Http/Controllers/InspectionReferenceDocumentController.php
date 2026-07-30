<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\AttachInspectionReferenceDocument;
use App\Actions\Inspections\RemoveInspectionReferenceDocument;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\UpdateInspectionReferenceDocumentsRequest;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class InspectionReferenceDocumentController extends Controller
{
    use ResolvesTenantStructure;

    public function update(
        UpdateInspectionReferenceDocumentsRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        AttachInspectionReferenceDocument $attach,
        RemoveInspectionReferenceDocument $remove,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('manageReferences', $inspection);

        $selectedDocumentIds = collect($request->validated('reference_document_ids', []))
            ->map(fn ($documentId): int => (int) $documentId)
            ->unique()
            ->values();

        DB::transaction(function () use ($inspection, $tenant, $request, $attach, $remove, $selectedDocumentIds): void {
            Inspection::query()
                ->whereKey($inspection->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $inspection->loadMissing('referenceDocuments.document');

            foreach ($selectedDocumentIds as $documentId) {
                $document = EquipmentDocument::query()
                    ->forOrganization($tenant->id())
                    ->whereKey($documentId)
                    ->firstOrFail();

                $attach->handle($inspection, $document, $request->user());
            }

            $inspection->referenceDocuments
                ->reject(fn (InspectionReferenceDocument $referenceDocument): bool => $selectedDocumentIds->contains($referenceDocument->equipment_document_id))
                ->each(function (InspectionReferenceDocument $referenceDocument) use ($remove, $request): void {
                    $remove->handle($referenceDocument, $request->user());
                });
        });

        return back()->with('success', 'Documentos de referência atualizados.');
    }

    public function destroy(
        Request $request,
        TenantContext $tenant,
        Inspection $inspection,
        InspectionReferenceDocument $referenceDocument,
        RemoveInspectionReferenceDocument $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $referenceDocument = $this->tenantInspectionReferenceDocument($tenant, $inspection, $referenceDocument);

        $this->authorize('manageReferences', $inspection);

        $action->handle($referenceDocument, $request->user());

        return back()->with('success', 'Documento de referência removido.');
    }
}
