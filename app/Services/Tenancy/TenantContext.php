<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use LogicException;

final class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function hasTenant(): bool
    {
        return $this->organization !== null;
    }

    public function organization(): Organization
    {
        return $this->organization
            ?? throw new LogicException('Nenhuma organização foi definida no contexto atual.');
    }

    public function id(): int
    {
        return $this->organization()->getKey();
    }
}
