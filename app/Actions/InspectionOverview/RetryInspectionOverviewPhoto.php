<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessInspectionOverviewPhoto;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetryInspectionOverviewPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, InspectionOverviewPhoto $photo): InspectionOverviewPhoto
    {
        $photo = InspectionOverviewPhoto::query()
            ->forOrganization($this->tenant->id())
            ->with('inspection')
            ->findOrFail($photo->getKey());

        if ($photo->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['photo' => 'A fotografia não pode ser reprocessada após o encerramento da inspeção.']);
        }

        if ($photo->processing_status !== PhotoProcessingStatus::Failed) {
            throw ValidationException::withMessages(['photo' => 'Somente fotografias com falha podem ser reprocessadas.']);
        }

        DB::transaction(function () use ($photo): void {
            $photo->update(['processing_status' => PhotoProcessingStatus::Pending, 'processing_error' => null]);
            ProcessInspectionOverviewPhoto::dispatch($photo->getKey())->onQueue('images')->afterCommit();
        });

        return $photo->refresh();
    }
}
