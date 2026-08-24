<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectClassification;
use App\Models\User;
use App\Services\Classification\DefectClassificationRangeValidator;
use Illuminate\Support\Facades\DB;

final class ChangeDefectClassificationStatus
{
    public function __construct(private readonly DefectClassificationRangeValidator $rangeValidator) {}

    public function handle(User $actor, DefectClassification $classification, RegistrationStatus $status): DefectClassification
    {
        DB::transaction(function () use ($actor, $classification, $status): void {
            $classification = DefectClassification::query()
                ->with('category')
                ->lockForUpdate()
                ->findOrFail($classification->getKey());
            $category = $classification->category;

            if ($category === null) {
                throw new \RuntimeException('A categoria da classificação não foi encontrada.');
            }

            $category->newQuery()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            if ($status === RegistrationStatus::Active) {
                $this->rangeValidator->ensureActivatable($category, $classification);
            }

            $classification->update([
                'status' => $status,
                'updated_by' => $actor->getKey(),
            ]);
        });

        return $classification->refresh();
    }
}
