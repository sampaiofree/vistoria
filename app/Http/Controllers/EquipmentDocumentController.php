<?php

namespace App\Http\Controllers;

use App\Actions\EquipmentDocuments\SetEquipmentDocumentCurrent;
use App\Actions\EquipmentDocuments\SetEquipmentDocumentStatus;
use App\Actions\EquipmentDocuments\StoreEquipmentDocument;
use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\EquipmentDocuments\StoreEquipmentDocumentRequest;
use App\Http\Requests\EquipmentDocuments\UpdateEquipmentDocumentStatusRequest;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EquipmentDocumentController extends Controller
{
    use ResolvesTenantStructure;

    public function store(
        StoreEquipmentDocumentRequest $request,
        TenantContext $tenant,
        Equipment $equipment,
        StoreEquipmentDocument $action,
    ): RedirectResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('create', [EquipmentDocument::class, $equipment]);

        $action->handle(
            $request->user(),
            $equipment,
            $request->file('file'),
            $request->validated(),
        );

        return redirect()
            ->route('equipments.show', $equipment)
            ->with('success', 'Documento enviado.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        EquipmentDocument $equipmentDocument,
    ): InertiaResponse {
        $equipmentDocument = $this->tenantEquipmentDocument($tenant, $equipmentDocument);

        $this->authorize('view', $equipmentDocument);

        $equipmentDocument->loadMissing([
            'equipment.client',
            'equipment.unit',
            'equipment.area',
            'equipment.subarea',
            'uploader',
        ]);

        $equipment = $equipmentDocument->equipment;

        return Inertia::render('EquipmentDocuments/Show', [
            'document' => $this->documentPayload($equipmentDocument),
            'equipment' => [
                'public_id' => $equipment->public_id,
                'tag' => $equipment->tag,
                'name' => $equipment->name,
                'show_url' => route('equipments.show', $equipment),
            ],
            'client' => [
                'public_id' => $equipment->client->public_id,
                'name' => $equipment->client->name,
                'show_url' => route('clients.show', $equipment->client),
            ],
            'unit' => [
                'public_id' => $equipment->unit->public_id,
                'name' => $equipment->unit->name,
                'show_url' => route('units.show', $equipment->unit),
            ],
            'area' => [
                'public_id' => $equipment->area->public_id,
                'name' => $equipment->area->name,
                'show_url' => route('areas.show', $equipment->area),
            ],
            'subarea' => $equipment->subarea === null
                ? null
                : [
                    'public_id' => $equipment->subarea->public_id,
                    'name' => $equipment->subarea->name,
                    'show_url' => route('subareas.show', $equipment->subarea),
                ],
            'can' => [
                'download' => $request->user()->can('download', $equipmentDocument),
                'update_status' => $request->user()->can('updateStatus', $equipmentDocument),
                'set_current' => $request->user()->can('setCurrent', $equipmentDocument),
            ],
            'back_url' => route('equipments.show', $equipment),
            'download_url' => route('equipment-documents.download', $equipmentDocument),
            'status_url' => route('equipment-documents.status', $equipmentDocument),
            'document_types' => EquipmentDocumentType::options(),
        ]);
    }

    public function download(
        TenantContext $tenant,
        EquipmentDocument $equipmentDocument,
    ) {
        $equipmentDocument = $this->tenantEquipmentDocument($tenant, $equipmentDocument);

        $this->authorize('download', $equipmentDocument);

        if (! Storage::disk($equipmentDocument->disk)->exists($equipmentDocument->path)) {
            abort(404);
        }

        return Storage::disk($equipmentDocument->disk)->download(
            $equipmentDocument->path,
            $equipmentDocument->original_name,
        );
    }

    public function updateStatus(
        UpdateEquipmentDocumentStatusRequest $request,
        TenantContext $tenant,
        EquipmentDocument $equipmentDocument,
        SetEquipmentDocumentStatus $action,
    ): RedirectResponse {
        $equipmentDocument = $this->tenantEquipmentDocument($tenant, $equipmentDocument);

        $this->authorize('updateStatus', $equipmentDocument);

        $action->handle(
            $equipmentDocument,
            DocumentStatus::from($request->validated('status')),
        );

        return back()->with('success', 'Status do documento atualizado.');
    }

    public function updateCurrent(
        TenantContext $tenant,
        EquipmentDocument $equipmentDocument,
        SetEquipmentDocumentCurrent $action,
    ): RedirectResponse {
        $equipmentDocument = $this->tenantEquipmentDocument($tenant, $equipmentDocument);

        $this->authorize('setCurrent', $equipmentDocument);

        $action->handle($equipmentDocument);

        return back()->with('success', 'Documento definido como atual.');
    }

    /**
     * @return array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, status_url:string, set_current_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}
     */
    private function documentPayload(EquipmentDocument $document): array
    {
        return [
            'id' => $document->id,
            'public_id' => $document->public_id,
            'document_group' => $document->document_group,
            'document_type' => $document->document_type->value,
            'document_type_label' => $document->document_type->label(),
            'title' => $document->title,
            'document_number' => $document->document_number,
            'revision' => $document->revision,
            'description' => $document->description,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'size' => (int) $document->size,
            'checksum' => $document->checksum,
            'is_current' => (bool) $document->is_current,
            'status' => $document->status->value,
            'issued_at' => $document->issued_at?->toDateString(),
            'created_at' => $document->created_at?->toDateTimeString(),
            'updated_at' => $document->updated_at?->toDateTimeString(),
            'download_url' => route('equipment-documents.download', $document),
            'show_url' => route('equipment-documents.show', $document),
            'status_url' => route('equipment-documents.status', $document),
            'set_current_url' => route('equipment-documents.current', $document),
            'uploaded_by' => $document->uploader === null
                ? null
                : [
                    'id' => $document->uploader->id,
                    'public_id' => $document->uploader->public_id,
                    'name' => $document->uploader->name,
                ],
        ];
    }
}
