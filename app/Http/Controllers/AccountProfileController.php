<?php

namespace App\Http\Controllers;

use App\Actions\Account\UpdateOwnProfile;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\User;
use App\Services\Accounts\ProfilePhotoUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AccountProfileController extends Controller
{
    public function edit(Request $request, ProfilePhotoUrls $photos): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Account/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'photo_url' => $photos->forUser($user),
            ],
            'update_url' => route('account.profile.update'),
            'remove_photo_url' => route('account.profile.photo.destroy'),
            'password_url' => route('account.password.edit'),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateOwnProfile $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect()->route('account.profile.edit')->with('success', 'Perfil atualizado.');
    }

    public function destroyPhoto(Request $request, UpdateOwnProfile $action): RedirectResponse
    {
        $action->removePhoto($request->user());

        return redirect()->route('account.profile.edit')->with('success', 'Foto de perfil removida.');
    }

    public function showPhoto(Request $request, User $user): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor->getKey() === $user->getKey()
            || ($actor->organization_id !== null && $actor->organization_id === $user->organization_id), 404);

        $path = $user->profile_photo_path;
        abort_unless($path !== null
            && str_starts_with($path, 'users/'.$user->public_id.'/')
            && preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\.webp\z/', basename($path)) === 1
            && substr_count($path, '/') === 2, 404);

        $disk = Storage::disk('profile_photos');
        abort_unless($disk->exists($path), 404);
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
