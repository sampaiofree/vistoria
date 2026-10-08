<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessInspectionGeneralAspectImage;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class StoreInspectionGeneralAspectImage
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Inspection $inspection, User $actor, UploadedFile $file): InspectionGeneralAspectImage
    {
        $id = DB::transaction(function () use ($inspection, $actor, $file): int {
            $inspection = Inspection::query()->forOrganization($this->tenant->id())
                ->lockForUpdate()->findOrFail($inspection->getKey());

            if (! $actor->can('manageGeneralAspects', $inspection)) {
                throw ValidationException::withMessages(['file' => 'Os Aspectos Gerais não estão disponíveis para edição.']);
            }

            $image = InspectionGeneralAspectImage::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->id,
                'processing_status' => PhotoProcessingStatus::Pending,
                'disk' => 'inspection_photos',
                'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 250),
                'original_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'original_extension' => $file->extension() ?: $file->getClientOriginalExtension(),
                'original_size' => $file->getSize() ?? 0,
                'uploaded_at' => now(),
                'unreferenced_at' => now(),
                'uploaded_by' => $actor->id,
            ]);

            $directory = sprintf('organizations/%d/inspections/%s/general-aspects/%s',
                $this->tenant->id(), $inspection->public_id, $image->public_id);
            $filename = 'original.'.strtolower((string) ($image->original_extension ?: 'bin'));
            $path = $directory.'/'.$filename;
            if (! Storage::disk('inspection_photos')->putFileAs($directory, $file, $filename)) {
                throw ValidationException::withMessages(['file' => 'Não foi possível armazenar a imagem.']);
            }

            $image->update(['original_path' => $path]);
            ProcessInspectionGeneralAspectImage::dispatch($image->id)->onQueue('images')->afterCommit();

            return $image->id;
        });

        return InspectionGeneralAspectImage::query()->findOrFail($id);
    }
}
