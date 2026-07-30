<?php

namespace App\Http\Middleware;

use App\Enums\UserAccountType;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'navigation' => $this->navigation($request),
            'auth' => [
                'logout_url' => $user ? route('logout') : null,
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_type' => $user->account_type->value,
                    'organization' => $user->organization ? [
                        'name' => $user->organization->name,
                        'legal_name' => $user->organization->legal_name,
                        'document' => $user->organization->document,
                        'status' => $user->organization->status->value,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * @return array<int, array{label:string, href:string, icon:string, active:bool}>
     */
    private function navigation(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $items = [
            [
                'label' => 'Dashboard',
                'href' => route('dashboard'),
                'icon' => 'dashboard',
                'active' => $request->routeIs('dashboard'),
            ],
        ];

        if ($user->account_type === UserAccountType::SuperAdmin) {
            return $items;
        }

        return array_merge($items, [
            [
                'label' => 'Inspeções',
                'href' => route('inspections.index'),
                'icon' => 'inspections',
                'active' => $request->routeIs('inspections.*'),
            ],
            [
                'label' => 'Equipamentos',
                'href' => route('equipments.index'),
                'icon' => 'equipments',
                'active' => $request->routeIs('equipments.*', 'equipment-documents.*'),
            ],
            [
                'label' => 'Clientes',
                'href' => route('clients.index'),
                'icon' => 'clients',
                'active' => $request->routeIs('clients.*', 'units.*', 'areas.*', 'subareas.*'),
            ],
        ]);
    }
}
