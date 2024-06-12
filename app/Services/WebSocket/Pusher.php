<?php

namespace App\Services\WebSocket;

use Swoole\WebSocket\Server;

/**
 * 通信数据发送类
 */
class Pusher
{
    protected Server $server;

    protected int $opcode;

    protected int $sender;

    protected array $descriptors;

    protected bool $broadcast;

    protected bool $assigned;

    protected string $event;

    protected mixed $message;

    /**
     * @param Server $server
     * @param int $opcode
     * @param int $sender
     * @param array $descriptors
     * @param bool $broadcast
     * @param bool $assigned
     * @param string $event
     * @param mixed $message
     */
    public function __construct(Server $server, int $opcode, int $sender, array $descriptors, bool $broadcast, bool $assigned, string $event, mixed $message = null)
    {
        $this->server = $server;
        $this->opcode = $opcode;
        $this->sender = $sender;
        $this->descriptors = $descriptors;
        $this->broadcast = $broadcast;
        $this->assigned = $assigned;
        $this->event = $event;
        $this->message = $message;
    }

    public static function make(array $data, Server $server): Pusher
    {
        return new static(
            $server,
            $data['opcode'] ?? 1,
            $data['sender'] ?? 0,
            $data['fds'] ?? [],
            $data['broadcast'] ?? false,
            $data['assigned'] ?? false,
            $data['event'] ?? null,
            $data['message'] ?? null
        );
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getOpcode(): int
    {
        return $this->opcode;
    }

    public function getSender(): int
    {
        return $this->sender;
    }

    public function getDescriptors(): array
    {
        return $this->descriptors;
    }

    public function isBroadcast(): bool
    {
        return $this->broadcast;
    }

    public function isAssigned(): bool
    {
        return $this->assigned;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }

    public function addDescriptor($descriptor): self
    {
        return $this->addDescriptors([$descriptor]);
    }

    public function addDescriptors(array $descriptors): self
    {
        $this->descriptors = array_values(
            array_unique(array_merge($this->descriptors, $descriptors))
        );
        return $this;
    }

    public function hasDescriptor(int $descriptor): bool
    {
        return in_array($descriptor, $this->descriptors);
    }

    public function shouldBroadcast(): bool
    {
        return $this->broadcast && empty($this->descriptors) && !$this->assigned;
    }

    /**
     * 返回所有属于 websocket 的描述符
     * @return array
     */
    protected function getWebsocketConnections(): array
    {
        return array_filter(iterator_to_array($this->server->connections), function ($fd) { // TODO
           return $this->server->isEstablished($fd);
        });
    }

    public function shouldPushToDescriptor(int $fd): bool
    {
        if (!$this->server->isEstablished($fd)) {
            return false;
        }
        return $this->broadcast ? $this->sender !== (int) $fd : true;
    }


    public function push($payload): void
    {
        // 如果未broadcast，则附加发送方
        if (!$this->broadcast && $this->sender && !$this->hasDescriptor($this->sender)) {
            $this->addDescriptor($this->sender);
        }

        // 检查是否广播到其他客户端
        if ($this->shouldBroadcast()) {
            $this->addDescriptors($this->getWebsocketConnections());
        }

        // 将消息推送到指定的 FD
        foreach ($this->descriptors as $descriptor) {
            if ($this->shouldPushToDescriptor($descriptor)) {
                $this->server->push($descriptor, $payload, $this->opcode);
            }
        }
    }
}
