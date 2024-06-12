<?php

namespace App\Services\WebSocket\SocketIO\Strategies;

use App\Services\WebSocket\SocketIO\Packet;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * 心跳连接处理策略类
 */
class HeartbeatStrategy
{
    /**
     * 如果返回true就是跳过解码
     * @param Server $server
     * @param Frame $frame
     * @return bool
     */
    public function handle(Server $server, Frame $frame): bool
    {
        $packet = $frame->data;
        $packetLength = strlen($packet);
        $payload = '';

        if (Packet::getPayload($packet)) {
            return false;
        }

        if ($isPing = Packet::isSocketType($packet, 'ping')) {
            $payload .= Packet::PONG;
        }

        if ($isPing && $packetLength > 1) {
            $payload .= substr($packet, 1, $packetLength - 1);
        }

        if ($isPing) {
            $server->push($frame->fd, $payload);
        }

        return true;
    }
}
