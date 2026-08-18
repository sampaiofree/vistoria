<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessAssessmentPhoto;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class StoreAssessmentPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, DefectAssessment $assessment, UploadedFile $file, array $data): AssessmentPhoto
    {
        $assessment = DefectAssessment::query()
            ->forOrganization($this->tenant->id())
            ->with('inspection')
            ->whereKey($assessment->getKey())
            ->firstOrFail();

        $position = DB::transaction(function () use ($assessment, $actor, $file, $data): int {
            $locked = DefectAssessment::query()->forOrganization($this->tenant->id())->lockForUpdate()->findOrFail($assessment->getKey());
            $position = ((int) AssessmentPhoto::query()->where('defect_assessment_id', $locked->getKey())->max('position')) + 1;
            $photo = AssessmentPhoto::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $locked->inspection_id,
                'defect_assessment_id' => $locked->getKey(),
                'photo_type' => $data['photo_type'] ?? AssessmentPhotoType::Detail,
                'caption' => $data['caption'] ?? null,
                'position' => $position,
                'processing_status' => PhotoProcessingStatus::Pending,
                'disk' => 'inspection_photos',
                'original_name' => $file->getClientOriginalName(),
                'original_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'original_extension' => $file->extension() ?: $file->getClientOriginalExtension(),
                'original_size' => $file->getSize() ?? 0,
                'captured_at' => $data['captured_at'] ?? null,
                'uploaded_at' => now(),
                'uploaded_by' => $actor->getKey(),
            ]);
            $directory = sprintf('organizations/%d/inspections/%s/assessments/%s/%s', $this->tenant->id(), $locked->inspection->public_id, $locked->public_id, $photo->public_id);
            $filename = 'original.'.strtolower((string) ($photo->original_extension ?: 'bin'));
            $path = $directory.'/'.$filename;

            if (! Storage::disk('inspection_photos')->putFileAs($directory, $file, $filename)) {
                $photo->delete();
                throw ValidationException::withMessages(['file' => 'Não foi possível armazenar a fotografia.']);
            }

            $photo->update(['original_path' => $path]);
            ProcessAssessmentPhoto::dispatch($photo->getKey())->onQueue('images')->afterCommit();

            return $position;
        });

        return AssessmentPhoto::query()->where('defect_assessment_id', $assessment->getKey())->where('position', $position)->firstOrFail();
    }
}
