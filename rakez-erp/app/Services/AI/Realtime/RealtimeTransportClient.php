<?php

namespace App\Services\AI\Realtime;

interface RealtimeTransportClient
{
    /**
     * @param  callable(array<string, mixed>): void  $onEvent
     * @param  callable(): void|null  $onOpen
     * @param  callable(): void|null  $onTick
     * @param  callable(): bool|null  $shouldStop
     */
    public function run(
        callable $onEvent,
        ?callable $onOpen = null,
        ?callable $onTick = null,
        ?callable $shouldStop = null,
        int $timeoutSeconds = 30,
    ): void;

    /**
     * @param  array<string, mixed>  $event
     */
    public function send(array $event): void;

    public function stop(): void;
}
