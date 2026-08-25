<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\UserStatus;
use App\Models\Inspection;
use App\Models\User;
use App\Notifications\InspectionImageProcessingFailed;
use Illuminate\Support\Facades\Notification;

final class NotifyInspectionImageFailure
{
    public function handle(
        Inspection $inspection,
        ?int $uploaderId,
        string $title,
        string $message,
        string $url,
    ): void {
        $recipientIds = $inspection->responsibles()
            ->pluck('user_id')
            ->when($uploaderId !== null, fn ($ids) => $ids->push($uploaderId))
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->where('organization_id', $inspection->organization_id)
            ->where('status', UserStatus::Active->value)
            ->whereIn('id', $recipientIds)
            ->get();

        Notification::send($recipients, new InspectionImageProcessingFailed(
            $title,
            $message,
            $url,
            $inspection->public_id,
        ));
    }
}
