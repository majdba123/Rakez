<?php

namespace App\Services\Notifications;

class SmsSendResult
{
    public function __construct(
        public readonly string $sid,
    ) {}
}
