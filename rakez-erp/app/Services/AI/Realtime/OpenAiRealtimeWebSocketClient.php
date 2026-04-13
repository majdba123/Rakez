<?php

namespace App\Services\AI\Realtime;

use App\Services\AI\Exceptions\AiAssistantException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Throwable;

class OpenAiRealtimeWebSocketClient implements RealtimeTransportClient
{
    private LoopInterface $loop;

    private ?ConnectionInterface $connection = null;

    private ?MessageBuffer $messageBuffer = null;

    private bool $connected = false;

    private bool $stopped = false;

    private ?Throwable $failure = null;

    /** @var array<int, string> */
    private array $pendingMessages = [];

    public function __construct()
    {
        $this->loop = LoopFactory::create();
    }

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
    ): void {
        $uri = new Uri('wss://api.openai.com/v1/realtime?model='.rawurlencode((string) config('ai_realtime.openai.model')));
        $connector = new Connector($this->loop);
        $negotiator = new ClientNegotiator(new HttpFactory());
        $request = $negotiator->generateRequest($uri)
            ->withHeader('Authorization', 'Bearer '.(string) config('openai.api_key'));

        $handshakeBuffer = '';
        $handshakeComplete = false;
        $timeoutTimer = $this->loop->addTimer($timeoutSeconds, function (): void {
            $this->failure = new AiAssistantException(
                'Timed out while connecting to OpenAI Realtime.',
                'ai_realtime_provider_timeout',
                504
            );

            $this->stop();
        });

        $connector->connect('tls://api.openai.com:443')->then(
            function (ConnectionInterface $connection) use ($negotiator, $request, $onEvent, $onOpen, &$handshakeBuffer, &$handshakeComplete, $timeoutTimer): void {
                $this->connection = $connection;

                $connection->on('data', function ($data) use ($negotiator, $request, $onEvent, $onOpen, &$handshakeBuffer, &$handshakeComplete, $timeoutTimer, $connection): void {
                    if (! $handshakeComplete) {
                        $handshakeBuffer .= $data;
                        $headerEnd = strpos($handshakeBuffer, "\r\n\r\n");

                        if ($headerEnd === false) {
                            return;
                        }

                        $rawResponse = substr($handshakeBuffer, 0, $headerEnd + 4);
                        $remaining = substr($handshakeBuffer, $headerEnd + 4);
                        $response = Message::parseResponse($rawResponse);

                        if (! $negotiator->validateResponse($request, $response)) {
                            $this->failure = new AiAssistantException(
                                'OpenAI Realtime rejected the WebSocket handshake.',
                                'ai_realtime_provider_unavailable',
                                502
                            );
                            $connection->close();
                            $this->stop();

                            return;
                        }

                        $this->connected = true;
                        $handshakeComplete = true;
                        $this->loop->cancelTimer($timeoutTimer);
                        $this->messageBuffer = new MessageBuffer(
                            new CloseFrameChecker(),
                            function (MessageInterface $message) use ($onEvent): void {
                                $decoded = json_decode($message->getPayload(), true);

                                if (is_array($decoded)) {
                                    $onEvent($decoded);
                                }
                            },
                            function (FrameInterface $frame) use ($connection): void {
                                switch ($frame->getOpcode()) {
                                    case Frame::OP_PING:
                                        $connection->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->maskPayload()->getContents());
                                        break;
                                    case Frame::OP_CLOSE:
                                        $connection->end((new Frame($frame->getPayload(), true, Frame::OP_CLOSE))->maskPayload()->getContents());
                                        $this->stop();
                                        break;
                                }
                            },
                            false,
                            null,
                            null,
                            null,
                            [$connection, 'write']
                        );

                        foreach ($this->pendingMessages as $payload) {
                            $this->messageBuffer->sendMessage($payload);
                        }

                        $this->pendingMessages = [];

                        if ($onOpen !== null) {
                            $onOpen();
                        }

                        if ($remaining !== '') {
                            $this->messageBuffer->onData($remaining);
                        }

                        return;
                    }

                    $this->messageBuffer?->onData($data);
                });

                $connection->on('close', function (): void {
                    $this->stop();
                });

                $connection->on('error', function (Throwable $throwable): void {
                    $this->failure = $throwable;
                    $this->stop();
                });

                $connection->write(Message::toString($request));
            },
            function (Throwable $throwable): void {
                $this->failure = $throwable;
                $this->stop();
            }
        );

        if ($shouldStop !== null) {
            $this->loop->addPeriodicTimer(0.25, function () use ($shouldStop): void {
                if ($shouldStop()) {
                    $this->stop();
                }
            });
        }

        if ($onTick !== null) {
            $this->loop->addPeriodicTimer(0.1, function () use ($onTick): void {
                $onTick();
            });
        }

        $this->loop->run();

        if ($this->failure instanceof AiAssistantException) {
            throw $this->failure;
        }

        if ($this->failure instanceof Throwable) {
            throw new AiAssistantException(
                'OpenAI Realtime transport failed: '.$this->failure->getMessage(),
                'ai_realtime_provider_unavailable',
                503
            );
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function send(array $event): void
    {
        $payload = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            throw new AiAssistantException('Failed to encode realtime transport event.', 'ai_realtime_transport_invalid_payload', 422);
        }

        if ($this->messageBuffer !== null) {
            $this->messageBuffer->sendMessage($payload);

            return;
        }

        $this->pendingMessages[] = $payload;
    }

    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }

        $this->stopped = true;

        if ($this->connection !== null) {
            $this->connection->close();
        }

        $this->loop->stop();
    }
}
