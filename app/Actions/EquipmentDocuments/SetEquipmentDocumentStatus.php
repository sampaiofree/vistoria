<?php

namespace App\Actions\EquipmentDocuments;

use App\Enums\DocumentStatus;
use App\Models\EquipmentDocument;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class SetEquipmentDocumentStatus
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(EquipmentDocument $document, DocumentStatus $status): EquipmentDocument
    {
        return DB::transaction(function () use ($document, $status): EquipmentDocument {
            $document = EquipmentDocument::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            $document->update([
                'status' => $status,
            ]);

            return $document->refresh();
        });
    }
}
