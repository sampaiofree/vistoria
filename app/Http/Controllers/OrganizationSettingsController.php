<?php

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateOrganization;
use App\Http\Requests\Settings\UpdateOrganizationRequest;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class OrganizationSettingsController extends Controller
{
    public function edit(Request $request, TenantContext $tenant): InertiaResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        return Inertia::render('Settings/Company', [
            'organization' => [
                'name' => $organization->name,
                'legal_name' => $organization->legal_name,
                'document' => $organization->document,
                'logo_url' => $organization->logo_path !== null
                    ? Storage::disk('public')->url($organization->logo_path)
                    : null,
                'primary_color' => $organization->primary_color,
                'icon_url' => $organization->icon_path !== null
                    ? Storage::disk('public')->url($organization->icon_path)
                    : null,
            ],
            'action' => route('settings.company.update'),
            'remove_logo_url' => route('settings.company.logo.destroy'),
            'remove_icon_url' => route('settings.company.icon.destroy'),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, TenantContext $tenant, UpdateOrganization $action): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);
        $action->handle($organization, $request->validated());

        return redirect()->route('settings.company.edit')->with('success', 'Configurações da empresa atualizadas.');
    }

    public function destroyLogo(Request $request, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        if ($organization->logo_path !== null) {
            Storage::disk('public')->delete($organization->logo_path);
            $organization->update(['logo_path' => null]);
        }

        return redirect()->route('settings.company.edit')->with('success', 'Logotipo removido.');
    }

    public function destroyIcon(Request $request, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        if ($organization->icon_path !== null) {
            Storage::disk('public')->delete($organization->icon_path);
            $organization->update(['icon_path' => null]);
        }

        return redirect()->route('settings.company.edit')->with('success', 'Ícone removido.');
    }
}
