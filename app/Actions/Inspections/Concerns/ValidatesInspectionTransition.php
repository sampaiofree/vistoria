<?php

declare(strict_types=1);

namespace App\Actions\Inspections\Concerns;

use App\Enums\InspectionResponsibility;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

trait ValidatesInspectionTransition
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

    private function validateUser(User $user, int $organizationId, string $field = 'user'): void
    {
        if ($user->isSuperAdmin() || ! $user->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([$field => 'O usuário não pertence à organização atual.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([$field => 'O usuário deve estar ativo.']);
        }
    }

    private function ensureActorHasResponsibility(
        Inspection $inspection,
        User $actor,
        InspectionResponsibility ...$responsibilities,
    ): void {
        if ($responsibilities === []) {
            throw ValidationException::withMessages(['actor' => 'A responsabilidade exigida é inválida.']);
        }

        if (! $inspection->hasAnyResponsibilityForUser($actor, ...$responsibilities)) {
            throw ValidationException::withMessages(['actor' => 'O usuário não possui a responsabilidade necessária para esta transição.']);
        }
    }

    private function ensureResponsibilityPresent(
        Inspection $inspection,
        InspectionResponsibility ...$responsibilities,
    ): void {
        foreach ($responsibilities as $responsibility) {
            if (! $inspection->hasResponsibility($responsibility)) {
                throw ValidationException::withMessages([
                    'inspection' => sprintf(
                        'A inspeção precisa ter pelo menos um responsável com a função %s.',
                        $responsibility->label(),
                    ),
                ]);
            }
        }
    }
}
