<?php

namespace App\Services\WebSocket\SocketIO;

use App\Services\WebSocket\Parser;
use App\Services\WebSocket\SocketIO\Strategies\HeartbeatStrategy;

/**
 * Socket.io 对应数据编解码器
 */
class SocketIOParser extends Parser
{

    protected array $strategies = [
        HeartbeatStrategy::class,
    ];

    public function encode(string $event, $data): mixed
    {
        $packet = Packet::MESSAGE . Packet::EVENT;
        $shouldEncode = is_array($data) || is_object($data);
        $data = $shouldEncode ? json_encode($data) : $data;
        $format = $shouldEncode ? '["%s",%s]' : '["%s","%s"]';

        return $packet . sprintf($format, $event, $data);
    }

    public function decode($frame): array
    {
        $payload = Packet::getPayload($frame->data);
        return [
            'event' => $payload['event'] ?? null,
            'data' => $payload['data'] ?? null,
        ];
    }
}
