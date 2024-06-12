<?php

namespace App\Services\WebSocket;

/**
 * WebSocket整体服务类
 */
class WebSocket
{
    const PUSH_ACTION = 'push';
    const EVENT_CONNECT = 'connect';
    const USER_PREFIX = 'uid_';

    protected bool $isBroadcast = false;

    /**
     * 发送者的fd
     * @var int
     */
    protected int $sender;

    /**
     * 接受者的fd或房间号
     * @var array
     */
    protected array $to = [];

    /**
     * Websocket事件回调方法
     * @var array
     */
    protected array $callbacks = [];
}
