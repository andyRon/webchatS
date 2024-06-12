<?php

namespace App\Services\WebSocket\SocketIO;

/**
 * Socket.io通信数据解析底层类
 */
class Packet
{
    /*
     * Socket.io包类型
     */
    const OPEN = 0;
    const CLOSE = 1;
    const PING = 2;
    const PONG = 3;
    const MESSAGE = 4;
    const UPGRADE = 5;
    const NOOP = 6;

    /*
     * Engine.io包类型
     */
    const CONNECT = 0;
    const DISCONNECT = 1;
    const EVENT = 2;
    const ACK = 3;
    const ERROR = 4;
    const BINARY_ACK = 6;

    /**
     * Socket.io包类型
     */
    public static array $socketTypes = [
        0 => 'OPEN',
        1 => 'CLOSE',
        2 => 'PING',
        3 => 'PONG',
        4 => 'MESSAGE',
        5 => 'UPGRADE',
        6 => 'NOOP',
    ];

    /**
     * Engine.io包类型
     */
    public static array $engineTypes = [
        0 => 'CONNECT',
        1 => 'DISCONNECT',
        2 => 'EVENT',
        3 => 'ACK',
        4 => 'ERROR',
        5 => 'BINARY_EVENT',
        6 => 'BINARY_ACK',
    ];

    /**
     * 从原始有效负载中，获取套接字数据包类型。
     * @param string $packet
     * @return int|null
     */
    public static function getSocketType(string $packet): null|int
    {
        $type = $packet[0] ?? null;
        if (!array_key_exists($type, static::$socketTypes)) {
            return null;
        }

        return (int) $type;
    }

    /**
     * 从原始有效负载中获取数据包
     * @param string $packet
     * @return array|null
     */
    public static function getPayload(string $packet): null|array
    {
        $packet = trim($packet);
        $start = strpos($packet, '[');

        if ($start === false || substr($packet, -1) !== ']') {
            return null;
        }
        $data = substr($packet, $start, strlen($packet) - $start);
        $data = json_decode($data, true);

        if (is_null($data)) {
            return null;
        }

        return [
            'event' => $data[0],
            'data' => $data[1] ?? null,
        ];
    }

    /**
     * 判断Socket数据包类型是否属于定义好的
     * @param $packet
     * @param string $typeName
     * @return bool
     */
    public static function isSocketType($packet, string $typeName): bool
    {
        $type = array_search(strtoupper($typeName), static::$socketTypes);

        if ($type === false) {
            return false;
        }

        return static::getSocketType($packet) === $type;
    }
}
