<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use Tests\TestCase;

final class CompanyAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('branding_images');
    }

    public function test_manifest_is_public_and_contains_only_installation_metadata(): void
    {
        $organization = Organization::factory()->create(['name' => 'Empresa Azul', 'primary_color' => '#123456']);
        $response = $this->get(route('pwa.manifest', $organization))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('id', '/pwa/'.$organization->public_id)
            ->assertJsonPath('name', 'Empresa Azul')
            ->assertJsonPath('short_name', 'Empresa Azul')
            ->assertJsonPath('start_url', '/dashboard')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('prefer_related_applications', false)
            ->assertJsonCount(2, 'icons');

        $this->assertEqualsCanonicalizing([
            'id', 'name', 'short_name', 'lang', 'start_url', 'scope', 'display',
            'background_color', 'theme_color', 'prefer_related_applications', 'icons',
        ], array_keys($response->json()));
        $this->assertStringNotContainsString($organization->document, $response->getContent());
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        foreach ($response->json('icons') as $icon) {
            $this->get($icon['src'])->assertOk()->assertHeader('Content-Type', 'image/png');
        }
        $this->assertGuest();
    }

    public function test_metadata_and_icons_remain_bound_to_the_url_company_regardless_of_session(): void
    {
        $first = $this->organizationWithPwaIcon($this->image(100, 100, '#ff0000'));
        $second = $this->organizationWithPwaIcon($this->image(100, 100, '#0000ff'));
        $firstManifest = $this->get(route('pwa.manifest', $first))->json();
        $this->actingAs(User::factory()->for($first)->create());
        $secondManifest = $this->get(route('pwa.manifest', $second))->assertJsonPath('name', $second->name)->json();

        $this->assertNotSame($firstManifest['id'], $secondManifest['id']);
        $this->assertNotSame($firstManifest['icons'][0]['src'], $secondManifest['icons'][0]['src']);
        $this->assertPixel($this->get($firstManifest['icons'][0]['src'])->getContent(), 96, 96, [255, 0, 0]);
        $this->assertPixel($this->get($secondManifest['icons'][0]['src'])->getContent(), 96, 96, [0, 0, 255]);
    }

    public function test_uploaded_square_icon_fills_both_sizes(): void
    {
        $organization = $this->organizationWithPwaIcon($this->image(100, 100, '#ff0000'));

        foreach ([192, 512] as $size) {
            $response = $this->get(route('pwa.icon', ['organization' => $organization, 'size' => $size]))
                ->assertOk()->assertHeader('Content-Type', 'image/png');
            $contents = $response->getContent();
            $dimensions = getimagesizefromstring($contents);
            $this->assertSame([$size, $size], [$dimensions[0], $dimensions[1]]);
            $this->assertPixel($contents, (int) ($size / 2), (int) ($size / 2), [255, 0, 0]);
            $this->assertPixel($contents, (int) ($size / 2), (int) ($size / 4), [255, 0, 0]);
            $this->assertPixel($contents, 0, (int) ($size / 2), [255, 0, 0]);
        }
    }

    public function test_transparency_is_composited_on_white(): void
    {
        $image = new Imagick;
        $image->newImage(100, 100, 'transparent', 'png');
        $draw = new ImagickDraw;
        $draw->setFillColor('#ff0000');
        $draw->rectangle(30, 30, 70, 70);
        $image->drawImage($draw);
        $organization = $this->organizationWithPwaIcon($image->getImageBlob());
        $image->clear();
        $contents = $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 192]))->assertOk()->getContent();

        $this->assertPixel($contents, 20, 96, [255, 255, 255]);
        $this->assertPixel($contents, 96, 96, [255, 0, 0]);
    }

    public function test_missing_invalid_and_unsafe_uploaded_icons_use_the_default_icon(): void
    {
        $organization = Organization::factory()->create();
        $url = route('pwa.icon', ['organization' => $organization, 'size' => 192]);
        $fallback = $this->get($url)->assertOk()->getContent();

        $organization->update(['pwa_icon_path' => 'missing.png']);
        $this->assertSame($fallback, $this->get($url)->assertOk()->getContent());
        Storage::disk('branding_images')->put('missing.png', 'not an image');
        $this->assertSame($fallback, $this->get($url)->assertOk()->getContent());
        Storage::disk('branding_images')->put('missing.png', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="192" height="192" fill="red"/></svg>');
        $this->assertSame($fallback, $this->get($url)->assertOk()->getContent());
        Storage::disk('branding_images')->put('missing.png', $this->image(400, 100, 'red'));
        $this->assertSame($fallback, $this->get($url)->assertOk()->getContent());
        Storage::disk('branding_images')->put('missing.png', $this->image(100, 100, 'red'));
        config(['photos.limits.max_dimension' => 50]);
        $this->assertSame($fallback, $this->get($url)->assertOk()->getContent());
    }

    public function test_logo_and_sidebar_icon_do_not_replace_the_default_pwa_icon(): void
    {
        $organization = Organization::factory()->create();
        $default = $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 192]))->assertOk()->getContent();
        Storage::disk('branding_images')->put('logo.png', $this->image(100, 100, 'red'));
        Storage::disk('branding_images')->put('sidebar.png', $this->image(100, 100, 'blue'));
        $organization->update(['logo_path' => 'logo.png', 'icon_path' => 'sidebar.png']);

        $this->assertSame($default, $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 192]))->assertOk()->getContent());
    }

    public function test_changing_and_removing_uploaded_icon_refreshes_icons_without_changing_app_identity(): void
    {
        $organization = $this->organizationWithPwaIcon($this->image(100, 100, 'red'));
        $manifestUrl = route('pwa.manifest', $organization);
        $original = $this->get($manifestUrl)->json();
        $oldIcon = $this->get($original['icons'][0]['src'])->assertOk();

        Storage::disk('branding_images')->put($organization->pwa_icon_path, $this->image(100, 100, 'blue'));
        $organization->update(['name' => 'Nome atualizado', 'primary_color' => '#654321']);
        $updated = $this->get($manifestUrl)->assertJsonPath('name', 'Nome atualizado')->assertJsonPath('theme_color', '#654321')->json();
        $this->assertSame($original['id'], $updated['id']);
        $this->assertNotSame($original['icons'][0]['src'], $updated['icons'][0]['src']);
        $newIcon = $this->get($updated['icons'][0]['src'])->assertOk();
        $this->assertNotSame($oldIcon->headers->get('ETag'), $newIcon->headers->get('ETag'));
        $this->assertPixel($newIcon->getContent(), 96, 96, [0, 0, 255]);

        $organization->update(['pwa_icon_path' => null]);
        $removed = $this->get($manifestUrl)->json();
        $this->assertSame($original['id'], $removed['id']);
        $this->assertNotSame($updated['icons'][0]['src'], $removed['icons'][0]['src']);
        $this->assertNotSame($newIcon->getContent(), $this->get($removed['icons'][0]['src'])->assertOk()->getContent());
    }

    public function test_icons_support_revalidation_and_restore_imagick_limits(): void
    {
        $organization = $this->organizationWithPwaIcon($this->image(100, 100, 'red'));
        $url = route('pwa.icon', ['organization' => $organization, 'size' => 192]);
        $previous = Imagick::getResourceLimit(Imagick::RESOURCETYPE_THREAD);
        config(['photos.processing.threads' => 1]);
        $response = $this->get($url)->assertOk();
        $this->assertSame($previous, Imagick::getResourceLimit(Imagick::RESOURCETYPE_THREAD));
        $this->assertSame($response->getContent(), $this->get($url)->getContent());
        $this->withHeader('If-None-Match', $response->headers->get('ETag'))->get($url)->assertStatus(304);
    }

    public function test_icon_cache_values_are_text_safe_and_cache_hits_still_return_pngs(): void
    {
        config(['cache.default' => 'database']);
        $organization = $this->organizationWithPwaIcon($this->image(100, 100, 'red'));

        foreach ([192, 512] as $size) {
            $url = route('pwa.icon', ['organization' => $organization, 'size' => $size]);
            $contents = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
            $this->assertSame($contents, $this->get($url)->assertOk()->getContent());
            $dimensions = getimagesizefromstring($contents);
            $this->assertSame([$size, $size], [$dimensions[0], $dimensions[1]]);
            $this->assertPixel($contents, (int) ($size / 2), (int) ($size / 2), [255, 0, 0]);
        }

        $prefix = Cache::getStore()->getPrefix();
        $keys = DB::table('cache')->pluck('key');
        $this->assertCount(2, $keys);
        foreach ($keys as $key) {
            // MySQL's TEXT cache column rejects binary PNG bytes. SQLite and
            // the array store used by other tests do not enforce this constraint.
            $cached = Cache::get(substr($key, strlen($prefix)));
            $this->assertIsString($cached);
            $this->assertTrue(mb_check_encoding($cached, 'UTF-8'), 'Icon cache values must be valid text for MySQL.');
        }
    }

    public function test_legacy_binary_cache_entries_do_not_break_icon_responses(): void
    {
        $icon = $this->image(100, 100, 'red');
        $organization = $this->organizationWithPwaIcon($icon);
        $legacyKey = 'pwa:icon:'.$organization->public_id.':'.hash('sha256', 'v1|'.$icon).':192';
        Cache::put($legacyKey, $this->image(192, 192, 'blue'), now()->addDays(30));

        $contents = $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 192]))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();

        $this->assertPixel($contents, 96, 96, [255, 0, 0]);
        $this->assertNotSame(Cache::get($legacyKey), $contents);
    }

    public function test_unknown_inactive_deleted_companies_and_unsupported_sizes_return_not_found(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $this->get(route('pwa.manifest', $organization))->assertNotFound();
        $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 192]))->assertNotFound();
        $this->get('/pwa/unknown/manifest.webmanifest')->assertNotFound();
        $this->get(route('pwa.icon', ['organization' => $organization, 'size' => 4096]))->assertNotFound();
        $organization->delete();
        $this->get(route('pwa.manifest', $organization))->assertNotFound();
    }

    public function test_authenticated_html_links_its_company_manifest_and_icon(): void
    {
        $organization = Organization::factory()->create(['primary_color' => '#123456']);
        $this->actingAs(User::factory()->for($organization)->create())
            ->get('/dashboard')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee(route('pwa.manifest', $organization, false), false)
            ->assertSee('/pwa/'.$organization->public_id.'/icons/192.png', false)
            ->assertSee('<meta name="theme-color" content="#123456">', false);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')->assertOk()->assertDontSee('rel="manifest"', false);
    }

    public function test_start_url_requires_login_when_session_is_absent(): void
    {
        $organization = Organization::factory()->create();
        $startUrl = $this->get(route('pwa.manifest', $organization))->json('start_url');
        $this->get($startUrl)->assertRedirectToRoute('login');
        $this->get('/login')->assertOk()->assertDontSee('rel="manifest"', false);
    }

    private function organizationWithPwaIcon(string $contents): Organization
    {
        $organization = Organization::factory()->create();
        $path = 'organizations/'.$organization->public_id.'/pwa-icon.png';
        Storage::disk('branding_images')->put($path, $contents);
        $organization->update(['pwa_icon_path' => $path]);

        return $organization;
    }

    private function image(int $width, int $height, string $color): string
    {
        $image = new Imagick;
        $image->newImage($width, $height, $color, 'png');
        $contents = $image->getImageBlob();
        $image->clear();

        return $contents;
    }

    private function assertPixel(string $contents, int $x, int $y, array $rgb): void
    {
        $image = new Imagick;
        $image->readImageBlob($contents);
        $color = $image->getImagePixelColor($x, $y)->getColor();
        $this->assertSame($rgb, [$color['r'], $color['g'], $color['b']]);
        $image->clear();
    }
}
