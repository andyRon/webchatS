<?php

namespace App\Services\WebSocket;

use Illuminate\Support\Facades\App;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * 数据编码解码器抽象基类
 */
abstract class Parser
{
    protected array $strategies = [];

    /**
     * 在解码有效负载之前执行策略。
     *
     * @param Server $server
     * @param Frame $frame
     * @return bool
     */
    public function execute(Server $server, Frame $frame): bool
    {
        $skip = false;
        foreach ($this->strategies as $strategy) {
            $result = App::call($strategy . '@handle',
            [
                'server' => $server,
                'frame' => $frame,
            ]);
            if ($result === true) {
                $skip = true;
                break;
            }
        }
        return $skip;
    }

    /**
     * 对 websocket 推送的输出有效负载进行编码。
     * @param string $event
     * @param $data
     * @return mixed
     */
    abstract public function encode(string $event, $data): mixed;

    /**
     * 在 websocket 上输入消息已连接。
     * 定义并返回事件名称和有效负载数据。
     * @param $frame
     * @return array
     */
    abstract public function decode($frame): array;
}
