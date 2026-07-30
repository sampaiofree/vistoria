<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionReferenceDocument;
use App\Models\InspectionReferenceDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveInspectionReferenceDocument
{
    use ValidatesInspectionReferenceDocument;

    public function handle(InspectionReferenceDocument $referenceDocument, User $actor): void
    {
        $inspection = $referenceDocument->inspection;
        $organizationId = $this->validateTenant($inspection, $actor);

        if (! $referenceDocument->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['document' => 'A referência não pertence à organização atual.']);
        }

        if (! $referenceDocument->document->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['document' => 'O documento não pertence à organização atual.']);
        }

        if ($inspection->status->isFinal()) {
            throw ValidationException::withMessages(['inspection' => 'Não é possível alterar documentos de referência de uma inspeção liberada ou cancelada.']);
        }

        DB::transaction(function () use ($referenceDocument): void {
            InspectionReferenceDocument::query()
                ->whereKey($referenceDocument->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }
}
