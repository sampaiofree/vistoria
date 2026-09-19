<?php

namespace App\Http\Controllers;

use App\Actions\Settings\ChangeOrganizationUserStatus;
use App\Actions\Settings\CreateOrganizationUser;
use App\Actions\Settings\ResetOrganizationUserPassword;
use App\Actions\Settings\UpdateOrganizationUser;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Http\Requests\Settings\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class UserSettingsController extends Controller
{
    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorize('viewAny', User::class);
        $search = trim((string) $request->string('search'));
        $status = UserStatus::tryFrom(trim((string) $request->string('status')));
        $accountType = UserAccountType::tryFrom(trim((string) $request->string('account_type')));
        $operationalRole = OperationalRole::tryFrom(trim((string) $request->string('operational_role')));

        $users = User::query()
            ->where('organization_id', $tenant->id())
            ->withCount(['inspectionResponsibles as open_inspections_count' => fn ($query) => $query
                ->whereHas('inspection', fn ($inspection) => $inspection->whereNotIn('status', ['released', 'canceled']))])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($accountType !== null, fn ($query) => $query->where('account_type', $accountType->value))
            ->when($operationalRole !== null, fn ($query) => $query->where('operational_role', $operationalRole->value))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->payload($user, $request));

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'status' => $status?->value ?? '',
                'account_type' => $accountType?->value ?? '',
                'operational_role' => $operationalRole?->value ?? '',
            ],
            'status_options' => [
                ['value' => UserStatus::Active->value, 'label' => 'Ativo'],
                ['value' => UserStatus::Inactive->value, 'label' => 'Inativo'],
            ],
            'account_type_options' => [
                ['value' => UserAccountType::Member->value, 'label' => 'Usuário'],
                ['value' => UserAccountType::CompanyAdmin->value, 'label' => 'Administrador da Empresa'],
            ],
            'operational_role_options' => $this->operationalRoleOptions(),
            'create_url' => route('settings.users.create'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', User::class);

        return Inertia::render('Settings/Users/Create', [
            'action' => route('settings.users.store'),
            'cancel_url' => route('settings.users.index'),
            'account_type_options' => [
                ['value' => UserAccountType::Member->value, 'label' => 'Usuário'],
                ['value' => UserAccountType::CompanyAdmin->value, 'label' => 'Administrador da Empresa'],
            ],
            'operational_role_options' => $this->operationalRoleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request, TenantContext $tenant, CreateOrganizationUser $action): RedirectResponse
    {
        $result = $action->handle($tenant->organization(), $request->validated());

        return redirect()->route('settings.users.index')->with('temporary_credentials', [
            'name' => $result['user']->name,
            'email' => $result['user']->email,
            'password' => $result['temporary_password'],
        ]);
    }

    public function edit(Request $request, TenantContext $tenant, User $user): InertiaResponse
    {
        $user = $this->tenantUser($tenant, $user);
        $this->authorize('view', $user);

        return Inertia::render('Settings/Users/Edit', [
            'user' => $this->payload($user, $request),
            'action' => route('settings.users.update', $user),
            'status_url' => route('settings.users.status', $user),
            'reset_password_url' => $request->user()->can('resetPassword', $user)
                ? route('settings.users.reset-password', $user)
                : null,
            'cancel_url' => route('settings.users.index'),
            'account_type_options' => [
                ['value' => UserAccountType::Member->value, 'label' => 'Usuário'],
                ['value' => UserAccountType::CompanyAdmin->value, 'label' => 'Administrador da Empresa'],
            ],
            'operational_role_options' => $this->operationalRoleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, TenantContext $tenant, User $user, UpdateOrganizationUser $action): RedirectResponse
    {
        $user = $this->tenantUser($tenant, $user);
        $this->authorize('update', $user);
        $action->handle($request->user(), $user, $request->validated());

        return redirect()->route('settings.users.edit', $user)->with('success', 'Usuário atualizado.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, TenantContext $tenant, User $user, ChangeOrganizationUserStatus $action): RedirectResponse
    {
        $user = $this->tenantUser($tenant, $user);
        $this->authorize('changeStatus', $user);
        $action->handle($request->user(), $user, UserStatus::from($request->validated('status')));

        return back()->with('success', 'Status do usuário atualizado.');
    }

    public function resetPassword(Request $request, TenantContext $tenant, User $user, ResetOrganizationUserPassword $action): RedirectResponse
    {
        $user = $this->tenantUser($tenant, $user);
        $this->authorize('resetPassword', $user);
        abort_if($user->status !== UserStatus::Active, 422, 'Só é possível redefinir a senha de usuários ativos.');
        $result = $action->handle($user);

        return back()->with('temporary_credentials', [
            'name' => $result['user']->name,
            'email' => $result['user']->email,
            'password' => $result['temporary_password'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(User $user, Request $request): array
    {
        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'account_type' => $user->account_type->value,
            'account_type_label' => $user->isCompanyAdmin() ? 'Administrador da Empresa' : 'Usuário',
            'operational_role' => $user->operational_role?->value,
            'operational_role_label' => $user->operationalRoleLabel(),
            'status' => $user->status->value,
            'status_label' => match ($user->status) {
                UserStatus::Active => 'Ativo',
                UserStatus::Inactive => 'Inativo',
            },
            'open_inspections_count' => (int) ($user->open_inspections_count ?? 0),
            'is_current_user' => $request->user()?->getKey() === $user->getKey(),
            'edit_url' => route('settings.users.edit', $user),
            'status_url' => route('settings.users.status', $user),
        ];
    }

    private function tenantUser(TenantContext $tenant, User $user): User
    {
        return User::query()->where('organization_id', $tenant->id())->whereKey($user->getKey())->firstOrFail();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function operationalRoleOptions(): array
    {
        return array_map(
            fn (OperationalRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            OperationalRole::cases(),
        );
    }
}
