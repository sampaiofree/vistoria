<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrganizationOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('inspection_photos');
        Storage::fake('inspection_maps');
        Storage::fake('branding_images');
    }

    public function test_only_company_administrators_can_open_the_overview_and_storage_endpoint(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($member)->get(route('settings.overview.show'))->assertForbidden();
        $this->actingAs($member)->getJson(route('settings.overview.storage'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('settings.overview.show'))->assertForbidden();
        $this->actingAs($superAdmin)->getJson(route('settings.overview.storage'))->assertForbidden();

        $this->actingAs($admin)->get(route('settings.overview.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Overview')
                ->where('storage_url', route('settings.overview.storage'))
                ->where('navigation.3.children.0.label', 'Resumo'));
        $this->actingAs($admin)->getJson(route('settings.overview.storage'))->assertOk();
    }

    public function test_overview_counts_all_company_records_except_deleted_clients(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        User::factory()->for($organization)->create(['status' => UserStatus::Inactive]);
        $client = Client::factory()->for($organization)->inactive()->create();
        Inspection::factory()->for($organization)->create(['status' => InspectionStatus::Canceled]);
        User::factory()->for(Organization::factory()->create())->create();

        $this->actingAs($admin)->get(route('settings.overview.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.users', 2)
                ->where('counts.clients', 1)
                ->where('counts.inspections', 1));

        $client->delete();
        $this->actingAs($admin)->get(route('settings.overview.show'))
            ->assertInertia(fn (Assert $page) => $page->where('counts.clients', 0));
    }

    public function test_storage_counts_only_current_company_images_and_reuses_cache_for_eight_hours(): void
    {
        $startedAt = now()->startOfSecond();
        $this->travelTo($startedAt);
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $otherAdmin = User::factory()->for($otherOrganization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $prefix = 'organizations/'.$organization->id;

        Storage::disk('inspection_photos')->put($prefix.'/photo/original.jpg', 'abc');
        Storage::disk('inspection_photos')->put($prefix.'/photo/optimized.webp', '12345');
        Storage::disk('inspection_photos')->put($prefix.'/photo/thumbnail.webp', 'xy');
        Storage::disk('inspection_maps')->put($prefix.'/map/source.png', '1234567');
        Storage::disk('inspection_maps')->put($prefix.'/map/background.webp', '12345678901');
        Storage::disk('inspection_maps')->put($prefix.'/map/thumbnail.webp', '1234');
        Storage::disk('inspection_maps')->put($prefix.'/map/ignored.pdf', str_repeat('p', 20));
        Storage::disk('branding_images')->put($prefix.'/clients/logo.png', str_repeat('l', 30));
        Storage::disk('branding_images')->put('organizations/'.$organization->public_id.'/branding/icon.png', str_repeat('i', 4));
        Storage::disk('inspection_photos')->put('organizations/'.$otherOrganization->id.'/photo.webp', str_repeat('o', 40));

        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertOk()
            ->assertJsonPath('photos.bytes', 10)
            ->assertJsonPath('photos.file_count', 3)
            ->assertJsonPath('maps.bytes', 22)
            ->assertJsonPath('maps.file_count', 3)
            ->assertJsonPath('branding.bytes', 34)
            ->assertJsonPath('branding.file_count', 2)
            ->assertJsonPath('total_bytes', 66)
            ->assertJsonPath('total_file_count', 8)
            ->assertJsonPath('measured_at', $startedAt->toIso8601String());

        Storage::disk('inspection_photos')->put($prefix.'/photo/new.jpg', 'new-data');
        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertJsonPath('total_bytes', 66);

        $this->actingAs($otherAdmin)->getJson(route('settings.overview.storage'))
            ->assertJsonPath('total_bytes', 40)
            ->assertJsonPath('total_file_count', 1);

        $this->travelTo($startedAt->copy()->addHours(8)->addSecond());
        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertJsonPath('photos.bytes', 18)
            ->assertJsonPath('total_bytes', 74)
            ->assertJsonPath('total_file_count', 9);
    }

    public function test_an_in_progress_scan_returns_calculating_without_starting_another_scan(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $cacheKey = 'organization-storage-usage:v2:'.$organization->id;
        $lock = Cache::lock($cacheKey.':lock', 300);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($admin)->getJson(route('settings.overview.storage'))
                ->assertStatus(202)
                ->assertHeader('Retry-After', '2')
                ->assertJsonPath('status', 'calculating');
            $this->assertNull(Cache::get($cacheKey));
        } finally {
            $lock->release();
        }

        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertOk()
            ->assertJsonPath('total_bytes', 0);
    }

    public function test_a_failed_scan_does_not_cache_a_partial_total(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $prefix = 'organizations/'.$organization->id;
        $cacheKey = 'organization-storage-usage:v2:'.$organization->id;
        Storage::disk('inspection_photos')->put($prefix.'/photo.webp', 'photo');
        Storage::disk('inspection_maps')->put($prefix.'/map.webp', 'map');
        $target = Storage::disk('inspection_maps')->path($prefix.'/map.webp');
        $link = Storage::disk('inspection_maps')->path($prefix.'/linked.webp');
        symlink($target, $link);

        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertInternalServerError();
        $this->assertNull(Cache::get($cacheKey));

        unlink($link);
        $this->actingAs($admin)->getJson(route('settings.overview.storage'))
            ->assertOk()
            ->assertJsonPath('total_bytes', 8);
    }
}
