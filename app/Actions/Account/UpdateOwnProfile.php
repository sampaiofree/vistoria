<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class UpdateOwnProfile
{
    /** @param array{name:string, photo?:UploadedFile|null} $data */
    public function handle(User $actor, array $data): void
    {
        $newPath = null;
        $oldPath = null;

        try {
            if (($data['photo'] ?? null) instanceof UploadedFile) {
                $newPath = 'users/'.$actor->public_id.'/'.Str::ulid().'.webp';
                $bytes = $this->optimizedPhoto($data['photo']);
                if (! Storage::disk('profile_photos')->put($newPath, $bytes)) {
                    throw new RuntimeException('Não foi possível salvar a foto de perfil.');
                }
            }

            DB::transaction(function () use ($actor, $data, $newPath, &$oldPath): void {
                $user = User::query()->lockForUpdate()->findOrFail($actor->getKey());
                $changes = ['name' => $data['name']];

                if ($newPath !== null) {
                    $oldPath = $user->profile_photo_path;
                    $changes['profile_photo_path'] = $newPath;
                }

                $user->update($changes);
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->deletePhoto($newPath, $actor);
            }

            throw $exception;
        }

        if ($oldPath !== null) {
            $this->deletePhoto($oldPath, $actor);
        }
    }

    public function removePhoto(User $actor): void
    {
        $oldPath = DB::transaction(function () use ($actor): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $path = $user->profile_photo_path;

            if ($path !== null) {
                $user->update(['profile_photo_path' => null]);
            }

            return $path;
        });

        if ($oldPath !== null) {
            $this->deletePhoto($oldPath, $actor);
        }
    }

    private function optimizedPhoto(UploadedFile $photo): string
    {
        $image = new Imagick;

        try {
            $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
            $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
            $image->setResourceLimit(Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);
            $image->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
            $image->readImage($photo->getRealPath());
            $image->setIteratorIndex(0);
            foreach (['autoOrient', 'autoOrientImage', 'autoOrientate'] as $method) {
                if (method_exists($image, $method)) {
                    $image->{$method}();
                    break;
                }
            }
            $image->thumbnailImage(512, 512, true);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(82);

            return $image->getImageBlob();
        } catch (ImagickException $exception) {
            throw ValidationException::withMessages(['photo' => 'Não foi possível processar esta foto. Escolha outra imagem.']);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function deletePhoto(string $path, User $actor): void
    {
        if (preg_match('/\Ausers\/'.preg_quote($actor->public_id, '/').'\/[0-9A-HJKMNP-TV-Z]{26}\.webp\z/', $path) !== 1) {
            Log::warning('Caminho inválido ao limpar foto de perfil.', ['user_id' => $actor->getKey()]);
            return;
        }

        try {
            if (! Storage::disk('profile_photos')->delete($path)) {
                throw new RuntimeException('Exclusão não confirmada.');
            }
        } catch (Throwable $exception) {
            Log::warning('Não foi possível limpar uma foto de perfil antiga.', [
                'user_id' => $actor->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
