<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BrandingImageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_branding_is_only_served_to_active_users_of_its_company(): void
    {
        Storage::fake('branding_images');
        $organization = Organization::factory()->create();
        $client = Client::factory()->for($organization)->create();
        $logoPath = 'organizations/'.$organization->public_id.'/branding/logo.png';
        $iconPath = 'organizations/'.$organization->public_id.'/branding/icon.webp';
        $clientPath = 'organizations/'.$organization->id.'/clients/'.$client->public_id.'/logo.jpg';
        $organization->update(['logo_path' => $logoPath, 'icon_path' => $iconPath]);
        $client->update(['logo_path' => $clientPath]);
        Storage::disk('branding_images')->put($logoPath, 'company-logo');
        Storage::disk('branding_images')->put($iconPath, 'company-icon');
        Storage::disk('branding_images')->put($clientPath, 'client-logo');

        $companyLogoUrl = route('branding.company', ['organization' => $organization, 'kind' => 'logo']);
        $companyIconUrl = route('branding.company', ['organization' => $organization, 'kind' => 'icon']);
        $clientLogoUrl = route('branding.client', $client);
        foreach ([$companyLogoUrl, $companyIconUrl, $clientLogoUrl] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $member = User::factory()->for($organization)->create();
        $this->actingAs($member)->get($companyLogoUrl)
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent('company-logo');
        $this->actingAs($member)->get($companyIconUrl)
            ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertStreamedContent('company-icon');
        $this->actingAs($member)->get($clientLogoUrl)
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertStreamedContent('client-logo');

        $otherMember = User::factory()->for(Organization::factory()->create())->create();
        $this->actingAs($otherMember)->get($clientLogoUrl)->assertNotFound();
        $this->actingAs($otherMember)->get($companyLogoUrl)->assertNotFound();
        $this->actingAs($otherMember)->get($companyIconUrl)->assertNotFound();
    }

    public function test_branding_route_rejects_paths_outside_the_expected_company_directory(): void
    {
        Storage::fake('branding_images');
        $organization = Organization::factory()->create([
            'logo_path' => 'organizations/another-company/branding/logo.png',
        ]);
        Storage::disk('branding_images')->put($organization->logo_path, 'other-logo');
        $member = User::factory()->for($organization)->create();

        $this->actingAs($member)
            ->get(route('branding.company', ['organization' => $organization, 'kind' => 'logo']))
            ->assertNotFound();
    }
}
