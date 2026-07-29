<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        if ($user->isSuperAdmin()) {
            $this->tenantContext->clear();

            abort(403, 'Superadministradores não podem acessar módulos operacionais sem uma organização selecionada.');
        }

        $organization = $user->organization;

        abort_if($organization === null, 403, 'Usuario sem organizacao vinculada.');

        $this->tenantContext->set($organization);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
