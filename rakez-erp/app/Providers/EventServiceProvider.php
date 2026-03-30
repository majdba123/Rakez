<?php

namespace App\Providers;

use App\Events\AI\AiDocumentIngested;
use App\Events\AI\AiRequestCompleted;
use App\Events\AI\AiRequestFailed;
use App\Events\AI\AiToolExecuted;
use App\Events\Marketing\ImageUploadedEvent;
use App\Listeners\AI\LogAiDocumentIngestedAudit;
use App\Listeners\AI\LogAiInteraction;
use App\Listeners\AI\LogAiRequestFailed;
use App\Listeners\AI\LogAiToolExecutedAudit;
use App\Listeners\Marketing\NotifyMarketingTeamOnImageUpload;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ImageUploadedEvent::class => [
            NotifyMarketingTeamOnImageUpload::class,
        ],
        AiRequestCompleted::class => [
            LogAiInteraction::class,
        ],
        AiRequestFailed::class => [
            LogAiRequestFailed::class,
        ],
        AiToolExecuted::class => [
            LogAiToolExecutedAudit::class,
        ],
        AiDocumentIngested::class => [
            LogAiDocumentIngestedAudit::class,
        ],
    ];
}
