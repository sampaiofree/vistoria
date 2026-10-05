<?php

namespace App\Services\Accounts;

use App\Models\User;

final class ProfilePhotoUrls
{
    public function forUser(User $user): ?string
    {
        return $user->profile_photo_path === null
            ? null
            : route('account.profile.photo.show', [
                'user' => $user,
                'v' => basename($user->profile_photo_path),
            ]);
    }
}
