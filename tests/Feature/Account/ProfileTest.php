<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\InspectionResponsibility;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_account_type_can_change_only_its_own_name(): void
    {
        $organization = Organization::factory()->create();
        $users = [
            User::factory()->for($organization)->create(['account_type' => UserAccountType::Member]),
            User::factory()->for($organization)->create(['account_type' => UserAccountType::Client]),
            User::factory()->superAdmin()->create(),
        ];

        foreach ($users as $user) {
            $originalEmail = $user->email;
            $originalType = $user->account_type;

            $this->actingAs($user)->get(route('account.profile.edit'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Account/Profile')
                    ->where('profile.name', $user->name)
                    ->where('profile.photo_url', null)
                    ->etc());

            $this->put(route('account.profile.update'), ['name' => '  Novo   Nome  '])
                ->assertRedirect(route('account.profile.edit'));

            $this->assertSame('Novo Nome', $user->refresh()->name);
            $this->assertSame($originalEmail, $user->email);
            $this->assertSame($originalType, $user->account_type);
        }
    }

    public function test_name_validation_and_extra_fields_do_not_change_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Nome original']);
        $other = User::factory()->for($user->organization)->create(['name' => 'Outro usuário']);

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => '   '])
            ->assertSessionHasErrors('name');
        $this->put(route('account.profile.update'), ['name' => str_repeat('a', 151)])
            ->assertSessionHasErrors('name');
        $this->put(route('account.profile.update'), [
            'name' => 'Nome indevido',
            'email' => 'novo@example.com',
            'account_type' => UserAccountType::CompanyAdmin->value,
            'user_id' => $other->getKey(),
        ])->assertSessionHasErrors(['email', 'account_type', 'user_id']);

        $this->assertSame('Nome original', $user->refresh()->name);
        $this->assertSame('Outro usuário', $other->refresh()->name);
    }

    public function test_temporary_password_must_be_changed_before_editing_profile(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('account.profile.edit'))
            ->assertRedirect(route('account.password.edit'));
        $this->put(route('account.profile.update'), ['name' => 'Novo nome'])
            ->assertRedirect(route('account.password.edit'));
        $this->assertNotSame('Novo nome', $user->refresh()->name);
    }

    public function test_photo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('profile_photos');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.profile.update'), [
            '_method' => 'put',
            'name' => $user->name,
            'photo' => UploadedFile::fake()->image('portrait.jpg', 1200, 800),
        ])->assertRedirect(route('account.profile.edit'));

        $firstPath = $user->refresh()->profile_photo_path;
        $this->assertNotNull($firstPath);
        Storage::disk('profile_photos')->assertExists($firstPath);
        $firstImage = new \Imagick;
        $firstImage->readImageBlob(Storage::disk('profile_photos')->get($firstPath));
        $this->assertSame('WEBP', $firstImage->getImageFormat());
        $this->assertLessThanOrEqual(512, $firstImage->getImageWidth());
        $this->assertLessThanOrEqual(512, $firstImage->getImageHeight());
        $firstImage->clear();
        $firstImage->destroy();

        $photoUrl = route('account.profile.photo.show', ['user' => $user, 'v' => basename($firstPath)]);
        $this->get(route('account.profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('Account/Profile')->where('profile.photo_url', $photoUrl)->etc());
        $this->get($photoUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->post(route('account.profile.update'), [
            '_method' => 'put',
            'name' => 'Nome novo',
            'photo' => UploadedFile::fake()->image('replacement.png', 500, 500),
        ])->assertRedirect(route('account.profile.edit'));

        $secondPath = $user->refresh()->profile_photo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('profile_photos')->assertMissing($firstPath);
        Storage::disk('profile_photos')->assertExists($secondPath);

        $this->delete(route('account.profile.photo.destroy'))
            ->assertRedirect(route('account.profile.edit'));
        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('profile_photos')->assertMissing($secondPath);
    }

    public function test_invalid_photo_is_rejected_without_changing_name_or_photo(): void
    {
        Storage::fake('profile_photos');
        $user = User::factory()->create(['name' => 'Nome original']);

        foreach ([
            UploadedFile::fake()->create('document.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->image('large.jpg')->size(2049),
            UploadedFile::fake()->image('wide.png', 4097, 10),
        ] as $file) {
            $this->actingAs($user)->post(route('account.profile.update'), [
                '_method' => 'put',
                'name' => 'Nome novo',
                'photo' => $file,
            ])->assertSessionHasErrors('photo');
        }

        $this->assertSame('Nome original', $user->refresh()->name);
        $this->assertNull($user->profile_photo_path);
    }

    public function test_new_photo_is_cleaned_up_if_profile_update_cannot_be_saved(): void
    {
        Storage::fake('profile_photos');
        $user = User::factory()->create();
        $user->delete();

        try {
            app(\App\Actions\Account\UpdateOwnProfile::class)->handle($user, [
                'name' => 'Novo nome',
                'photo' => UploadedFile::fake()->image('portrait.png', 100, 100),
            ]);
            $this->fail('Era esperado que o usuário não fosse encontrado.');
        } catch (ModelNotFoundException) {
            $this->assertSame([], Storage::disk('profile_photos')->allFiles());
        }
    }

    public function test_photo_is_private_to_the_owner_and_active_users_of_the_same_company(): void
    {
        Storage::fake('profile_photos');
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create();
        $sameCompany = User::factory()->for($organization)->create();
        $otherCompany = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $path = 'users/'.$owner->public_id.'/01K7RFJB8XM96MTKXM5P72X0RV.webp';
        $owner->update(['profile_photo_path' => $path]);
        Storage::disk('profile_photos')->put($path, 'private-photo');
        $url = route('account.profile.photo.show', $owner);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($owner)->get($url)->assertOk()->assertStreamedContent('private-photo');
        $this->actingAs($sameCompany)->get($url)->assertOk();
        $this->actingAs($otherCompany)->get($url)->assertNotFound();
        $this->actingAs($superAdmin)->get($url)->assertNotFound();

        $superAdminPath = 'users/'.$superAdmin->public_id.'/01K7RFJB8XM96MTKXM5P72X0RV.webp';
        $superAdmin->update(['profile_photo_path' => $superAdminPath]);
        Storage::disk('profile_photos')->put($superAdminPath, 'admin-photo');
        $this->get(route('account.profile.photo.show', $superAdmin))
            ->assertOk()->assertStreamedContent('admin-photo');

        $owner->update(['profile_photo_path' => 'users/'.$otherCompany->public_id.'/outside.webp']);
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_team_payload_includes_photo_url_and_keeps_null_for_users_without_photo(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $withPhoto = User::factory()->for($organization)->create();
        $withPhoto->update(['profile_photo_path' => 'users/'.$withPhoto->public_id.'/01K7RFJB8XM96MTKXM5P72X0RV.webp']);
        $withoutPhoto = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()->create(['organization_id' => $organization->getKey()]);
        InspectionResponsible::factory()->forInspection($inspection, $withPhoto)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $withoutPhoto)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $this->actingAs($actor)->get(route('inspections.team', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Team')
                ->where('responsibles.0.user.profile_photo_url', route('account.profile.photo.show', [
                    'user' => $withPhoto,
                    'v' => '01K7RFJB8XM96MTKXM5P72X0RV.webp',
                ]))
                ->where('responsibles.1.user.profile_photo_url', null)
                ->etc());
    }
}
