<?php

namespace App\Actions\EquipmentDocuments;

use App\Models\EquipmentDocument;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetEquipmentDocumentCurrent
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(EquipmentDocument $document): EquipmentDocument
    {
        return DB::transaction(function () use ($document): EquipmentDocument {
            $document = EquipmentDocument::query()
                ->forOrganization($this->tenant->id())
                ->with('equipment')
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            $siblings = EquipmentDocument::query()
                ->forOrganization($this->tenant->id())
                ->where('equipment_id', $document->equipment_id)
                ->where('document_group', $document->document_group)
                ->lockForUpdate()
                ->get();

            if ($siblings->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'Não foi possível localizar a revisão do documento.',
                ]);
            }

            $siblings->each(function (EquipmentDocument $sibling): void {
                $sibling->update([
                    'is_current' => false,
                ]);
            });

            $document->update([
                'is_current' => true,
            ]);

            return $document->refresh();
        });
    }
}
