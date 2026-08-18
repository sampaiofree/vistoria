<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessAssessmentPhoto;
use App\Models\AssessmentPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetryAssessmentPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, AssessmentPhoto $photo): AssessmentPhoto
    {
        $photo = AssessmentPhoto::query()->forOrganization($this->tenant->id())->with('inspection')->findOrFail($photo->getKey());

        if ($photo->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['photo' => 'Fotografias não podem ser reprocessadas após o encerramento da inspeção.']);
        }

        if ($photo->processing_status !== PhotoProcessingStatus::Failed) {
            throw ValidationException::withMessages(['photo' => 'Somente fotografias com falha podem ser reprocessadas.']);
        }

        DB::transaction(function () use ($photo): void {
            $photo->update(['processing_status' => PhotoProcessingStatus::Pending, 'processing_error' => null]);
            ProcessAssessmentPhoto::dispatch($photo->getKey())->onQueue('images')->afterCommit();
        });

        return $photo->refresh();
    }
}
