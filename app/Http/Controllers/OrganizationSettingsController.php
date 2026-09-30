<?php

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateOrganization;
use App\Http\Requests\Settings\UpdateOrganizationRequest;
use App\Services\Branding\BrandingImageUrls;
use App\Services\Pwa\OrganizationAppBranding;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class OrganizationSettingsController extends Controller
{
    public function edit(Request $request, TenantContext $tenant, BrandingImageUrls $brandingUrls, OrganizationAppBranding $appBranding): InertiaResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        return Inertia::render('Settings/Company', [
            'organization' => [
                'name' => $organization->name,
                'legal_name' => $organization->legal_name,
                'document' => $organization->document,
                'logo_url' => $brandingUrls->companyLogo($organization),
                'primary_color' => $organization->primary_color,
                'icon_url' => $brandingUrls->companyIcon($organization),
                'pwa_icon_url' => $appBranding->iconUrl($organization),
                'has_pwa_icon' => $organization->pwa_icon_path !== null,
            ],
            'action' => route('settings.company.update'),
            'remove_logo_url' => route('settings.company.logo.destroy'),
            'remove_icon_url' => route('settings.company.icon.destroy'),
            'remove_pwa_icon_url' => route('settings.company.pwa-icon.destroy'),
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
            Storage::disk('branding_images')->delete($organization->logo_path);
            $organization->update(['logo_path' => null]);
        }

        return redirect()->route('settings.company.edit')->with('success', 'Logotipo removido.');
    }

    public function destroyIcon(Request $request, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        if ($organization->icon_path !== null) {
            Storage::disk('branding_images')->delete($organization->icon_path);
            $organization->update(['icon_path' => null]);
        }

        return redirect()->route('settings.company.edit')->with('success', 'Ícone removido.');
    }

    public function destroyPwaIcon(Request $request, TenantContext $tenant): RedirectResponse
    {
        $organization = $tenant->organization();
        $this->authorize('update', $organization);

        if ($organization->pwa_icon_path !== null) {
            Storage::disk('branding_images')->delete($organization->pwa_icon_path);
            $organization->update(['pwa_icon_path' => null]);
        }

        return redirect()->route('settings.company.edit')->with('success', 'Ícone do aplicativo removido.');
    }
}
