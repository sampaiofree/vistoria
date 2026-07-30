<?php

declare(strict_types=1);

namespace App\Actions\Inspections\Concerns;

use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

trait ValidatesInspectionReferenceDocument
{
    private function validateTenant(Inspection $inspection, User $actor): int
    {
        $organizationId = app(TenantContext::class)->id();

        if (! $inspection->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['inspection' => 'A inspeção não pertence à organização atual.']);
        }

        $this->validateUser($actor, $organizationId, 'actor');

        return $organizationId;
    }

    private function validateDocument(
        EquipmentDocument $document,
        Inspection $inspection,
        int $organizationId,
    ): void {
        if (! $document->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['document' => 'O documento não pertence à organização atual.']);
        }

        if ((int) $document->equipment_id !== (int) $inspection->equipment_id) {
            throw ValidationException::withMessages(['document' => 'O documento precisa pertencer ao mesmo equipamento da inspeção.']);
        }
    }

    private function validateUser(User $user, int $organizationId, string $field = 'user'): void
    {
        if ($user->isSuperAdmin() || ! $user->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([$field => 'O usuário não pertence à organização atual.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([$field => 'O usuário deve estar ativo.']);
        }
    }
}
