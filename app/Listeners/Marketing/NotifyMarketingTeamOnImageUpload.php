<?php

namespace App\Listeners\Marketing;

use App\Events\Marketing\ImageUploadedEvent;
use App\Models\ProjectMedia;
use App\Services\Marketing\MarketingNotificationService;

class NotifyMarketingTeamOnImageUpload
{
    public function __construct(
        private MarketingNotificationService $notificationService
    ) {}

    public function handle(ImageUploadedEvent $event): void
    {
        // Store in project_media table. Uploads from editing department (photography/montage)
        // are treated as "after editing" so they appear in marketing project media_links.
        ProjectMedia::create([
            'contract_id' => $event->contractId,
            'url' => $event->mediaUrl,
            'type' => 'image', // Simplification, could be video
            'department' => $event->department,
            'approved_at' => now(),
        ]);

        // Notify
        $this->notificationService->notifyImageUpload($event->contractId, $event->mediaUrl);
    }
}
