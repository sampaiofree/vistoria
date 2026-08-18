<?php

namespace App\Http\Middleware;

use App\Enums\UserAccountType;
use App\Services\Navigation\InspectionContextNavigation;
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
            'inspection_navigation' => fn (): ?array => app(InspectionContextNavigation::class)->forRequest($request),
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
                        'primary_color' => $user->organization->primary_color ?? '#0F172A',
                        'icon_url' => $user->organization->icon_path !== null
                            ? asset('storage/'.$user->organization->icon_path)
                            : null,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'temporary_credentials' => fn (): ?array => $request->session()->get('temporary_credentials'),
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

        $navigation = [
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
        ];

        if ($user->isCompanyAdmin()) {
            $navigation[] = [
                'label' => 'Configurações',
                'href' => route('settings.company.edit'),
                'icon' => 'settings',
                'active' => $request->routeIs('settings.*'),
                'children' => [
                    [
                        'label' => 'Empresa',
                        'href' => route('settings.company.edit'),
                        'active' => $request->routeIs('settings.company.*'),
                    ],
                    [
                        'label' => 'Usuários',
                        'href' => route('settings.users.index'),
                        'active' => $request->routeIs('settings.users.*'),
                    ],
                ],
            ];
            $navigation[] = [
                'label' => 'Categorias de avarias',
                'href' => route('defect-categories.index'),
                'icon' => 'classification',
                'active' => $request->routeIs('defect-categories.*', 'defect-classifications.*'),
            ];
        }

        return array_merge($items, $navigation);
    }
}
