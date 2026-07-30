<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionReferenceDocument;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachInspectionReferenceDocument
{
    use ValidatesInspectionReferenceDocument;

    public function handle(
        Inspection $inspection,
        EquipmentDocument $document,
        User $actor,
    ): InspectionReferenceDocument {
        $organizationId = $this->validateTenant($inspection, $actor);
        $this->validateDocument($document, $inspection, $organizationId);

        if ($inspection->status->isFinal()) {
            throw ValidationException::withMessages(['inspection' => 'Não é possível alterar documentos de referência de uma inspeção liberada ou cancelada.']);
        }

        return DB::transaction(function () use ($inspection, $document, $actor, $organizationId): InspectionReferenceDocument {
            Inspection::query()->whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();

            $existing = InspectionReferenceDocument::query()
                ->where('inspection_id', $inspection->getKey())
                ->where('equipment_document_id', $document->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return InspectionReferenceDocument::query()->create([
                'organization_id' => $organizationId,
                'inspection_id' => $inspection->getKey(),
                'equipment_document_id' => $document->getKey(),
                'added_by' => $actor->getKey(),
                'created_at' => now(),
            ]);
        });
    }
}
