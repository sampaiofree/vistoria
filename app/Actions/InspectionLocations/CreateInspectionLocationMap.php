<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapSourceKind;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\DefectCategory;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationCapacity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspectionLocationMap
{
    public function __construct(private readonly InspectionLocationCapacity $capacity) {}

    public function handle(User $actor, Inspection $inspection, array $data): InspectionLocationMap
    {
        if ($actor->organization_id !== $inspection->organization_id) {
            throw ValidationException::withMessages(['inspection' => 'A inspeção não pertence à organização atual.']);
        }

        $category = DefectCategory::query()
            ->forOrganization($inspection->organization_id)
            ->whereKey($data['defect_category_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $documentId = $data['equipment_document_id'] ?? null;
        $document = null;
        if ($documentId !== null) {
            $document = EquipmentDocument::query()
                ->forOrganization($inspection->organization_id)
                ->whereKey($documentId)
                ->where('equipment_id', $inspection->equipment_id)
                ->first();

            $referenced = $document !== null && $inspection->referenceDocuments()->where('equipment_document_id', $document->id)->exists();
            if (! $referenced) {
                throw ValidationException::withMessages(['equipment_document_id' => 'O documento precisa estar referenciado na inspeção.']);
            }
        }

        $map = DB::transaction(function () use ($actor, $inspection, $category, $data, $documentId, $document): InspectionLocationMap {
            Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            $this->capacity->assertCanCreateMap($inspection, $category->id);
            $mapData = [
                ...$data,
                'organization_id' => $inspection->organization_id,
                'equipment_id' => $inspection->equipment_id,
                'inspection_id' => $inspection->id,
                'position' => $data['position'] ?? ((int) $inspection->locationMaps()->max('position') + 1),
                'source_kind' => $documentId === null ? InspectionLocationMapSourceKind::Upload : InspectionLocationMapSourceKind::ReferenceDocument,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ];

            if ($documentId !== null) {
                $mapData += [
                    'source_disk' => $document->disk,
                    'source_path' => $document->path,
                    'source_mime_type' => $document->mime_type,
                    'source_size' => $document->size,
                    'source_checksum' => $document->checksum,
                ];
            }

            return InspectionLocationMap::query()->create($mapData);
        });

        if ($documentId !== null) {
            ProcessInspectionLocationMap::dispatch($map->id, $map->source_checksum)->afterCommit();
        }

        return $map;
    }
}
