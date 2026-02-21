<?php

namespace App\Providers;

use App\Events\Marketing\ImageUploadedEvent;
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
    ];
}
