<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_it_stores_the_current_organization(): void
    {
        $organization = new Organization();
        $organization->setAttribute('id', 10);

        $context = new TenantContext();
        $context->set($organization);

        $this->assertTrue($context->hasTenant());
        $this->assertSame(10, $context->id());
        $this->assertSame($organization, $context->organization());
    }

    public function test_it_throws_when_no_tenant_is_defined(): void
    {
        $context = new TenantContext();

        $this->expectException(LogicException::class);

        $context->id();
    }

    public function test_it_can_clear_the_context(): void
    {
        $organization = new Organization();
        $organization->setAttribute('id', 10);

        $context = new TenantContext();
        $context->set($organization);
        $context->clear();

        $this->assertFalse($context->hasTenant());
    }
}
