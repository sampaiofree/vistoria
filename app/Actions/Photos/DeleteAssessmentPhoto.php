<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Models\AssessmentPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteAssessmentPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, AssessmentPhoto $photo): void
    {
        $photo = AssessmentPhoto::query()->forOrganization($this->tenant->id())->with('inspection')->findOrFail($photo->getKey());

        if ($photo->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['photo' => 'Fotografias não podem ser removidas após o encerramento da inspeção.']);
        }

        DB::transaction(function () use ($photo): void {
            $disk = Storage::disk($photo->disk);
            foreach ([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path] as $path) {
                if ($path !== null) {
                    $disk->delete($path);
                }
            }

            $photo->delete();
        });
    }
}
