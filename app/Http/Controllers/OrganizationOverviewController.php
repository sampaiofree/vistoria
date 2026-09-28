<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Storage\OrganizationStorageUsage;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class OrganizationOverviewController extends Controller
{
    public function show(TenantContext $tenant): InertiaResponse
    {
        $this->authorize('update', $tenant->organization());

        return Inertia::render('Settings/Overview', [
            'counts' => [
                'users' => User::query()->where('organization_id', $tenant->id())->count(),
                'clients' => Client::query()->forOrganization($tenant->id())->count(),
                'inspections' => Inspection::query()->forOrganization($tenant->id())->count(),
            ],
            'storage_url' => route('settings.overview.storage'),
        ]);
    }

    public function storage(TenantContext $tenant, OrganizationStorageUsage $usage): JsonResponse
    {
        $this->authorize('update', $tenant->organization());

        $result = $usage->get($tenant->id());
        if ($result === null) {
            return response()->json(['status' => 'calculating'], 202)
                ->header('Retry-After', '2')
                ->header('Cache-Control', 'no-store');
        }

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
