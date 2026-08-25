<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\CreateOrganizationWithAdmin;
use App\Actions\Organizations\SetGlobalOrganizationStatus;
use App\Actions\Organizations\UpdateGlobalOrganization;
use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Http\Requests\Organizations\StoreGlobalOrganizationRequest;
use App\Http\Requests\Organizations\UpdateGlobalOrganizationRequest;
use App\Http\Requests\Organizations\UpdateGlobalOrganizationStatusRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class GlobalOrganizationController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->string('search'));
        $status = OrganizationStatus::tryFrom(trim((string) $request->string('status')));

        $organizations = Organization::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->with(['users' => fn ($query) => $query
                ->where('account_type', UserAccountType::CompanyAdmin->value)
                ->orderBy('id')])
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Organization $organization): array => $this->payload($organization));

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'filters' => [
                'search' => $search,
                'status' => $status?->value ?? '',
            ],
            'status_options' => [
                ['value' => OrganizationStatus::Active->value, 'label' => 'Ativa'],
                ['value' => OrganizationStatus::Suspended->value, 'label' => 'Suspensa'],
                ['value' => OrganizationStatus::Inactive->value, 'label' => 'Inativa'],
            ],
            'create_url' => route('admin.organizations.create'),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Organizations/Create', [
            'action' => route('admin.organizations.store'),
            'cancel_url' => route('admin.organizations.index'),
        ]);
    }

    public function store(
        StoreGlobalOrganizationRequest $request,
        CreateOrganizationWithAdmin $action,
    ): RedirectResponse {
        $result = $action->handle($request->validated());

        return redirect()
            ->route('admin.organizations.index')
            ->with('success', 'Empresa criada com sucesso.')
            ->with('temporary_credentials', [
                'name' => $result['admin']->name,
                'email' => $result['admin']->email,
                'password' => $result['temporary_password'],
            ]);
    }

    public function edit(Organization $organization): InertiaResponse
    {
        return Inertia::render('Organizations/Edit', [
            'organization' => [
                'name' => $organization->name,
                'legal_name' => $organization->legal_name,
                'document' => $organization->document,
            ],
            'action' => route('admin.organizations.update', $organization),
            'cancel_url' => route('admin.organizations.index'),
        ]);
    }

    public function update(
        UpdateGlobalOrganizationRequest $request,
        Organization $organization,
        UpdateGlobalOrganization $action,
    ): RedirectResponse {
        $action->handle($organization, $request->validated());

        return redirect()
            ->route('admin.organizations.index')
            ->with('success', 'Dados da empresa atualizados.');
    }

    public function updateStatus(
        UpdateGlobalOrganizationStatusRequest $request,
        Organization $organization,
        SetGlobalOrganizationStatus $action,
    ): RedirectResponse {
        $status = OrganizationStatus::from($request->validated('status'));
        $action->handle($organization, $status);

        return back()->with('success', $status === OrganizationStatus::Suspended
            ? 'Empresa suspensa.'
            : 'Empresa reativada.');
    }

    /** @return array<string, mixed> */
    private function payload(Organization $organization): array
    {
        /** @var User|null $administrator */
        $administrator = $organization->users->first();

        return [
            'public_id' => $organization->public_id,
            'name' => $organization->name,
            'legal_name' => $organization->legal_name,
            'document' => $organization->document,
            'status' => $organization->status->value,
            'users_count' => (int) $organization->users_count,
            'administrator' => $administrator === null ? null : [
                'name' => $administrator->name,
                'email' => $administrator->email,
            ],
            'edit_url' => route('admin.organizations.edit', $organization),
            'status_url' => route('admin.organizations.status', $organization),
            'can_suspend' => $organization->status === OrganizationStatus::Active,
            'can_reactivate' => $organization->status === OrganizationStatus::Suspended,
        ];
    }
}
