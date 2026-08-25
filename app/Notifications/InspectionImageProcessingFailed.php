<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class InspectionImageProcessingFailed extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $url,
        private readonly string $inspectionPublicId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'inspection_image_processing_failed',
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'inspection_public_id' => $this->inspectionPublicId,
        ];
    }
}
