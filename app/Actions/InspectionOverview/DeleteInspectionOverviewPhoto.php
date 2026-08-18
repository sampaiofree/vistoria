<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteInspectionOverviewPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, InspectionOverviewPhoto $photo): void
    {
        $photo = InspectionOverviewPhoto::query()
            ->forOrganization($this->tenant->id())
            ->with('inspection')
            ->findOrFail($photo->getKey());

        if ($photo->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['photo' => 'A fotografia não pode ser removida após o encerramento da inspeção.']);
        }

        $paths = array_values(array_filter([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path]));

        DB::transaction(function () use ($photo): void {
            $photo->update(['slot' => null]);
            $photo->delete();
        });

        Storage::disk($photo->disk)->delete($paths);
    }
}
