<?php
namespace App\Services;

use App\Events\MessageReceived;
use App\Models\User;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Support\Facades\Log;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class WebSocketHandler implements WebSocketHandlerInterface
{

    public function __construct()
    {
    }

    public function onOpen(Server $server, Request $request)
    {
        Log::info('WebSocket链接建立:' . $request->fd);
    }

    /**
     * 收到消息时触发
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame)
    {
        // $frame->fd 是客户端 id，$frame->data 是客户端发送的数据
        Log::info("从 {$frame->fd} 接收到的数据： {$frame->data}");
        $message = json_decode($frame->data);
        // 基于token的用户校验
        if (empty($message->token) || !($user = User::query()->where('api_token', $message->token)->first())) {
            Log::warning('用户' . $message->name . '已经离线，不能发送信息');
            $server->push($frame->fd, '离线用户不嫩发送消息');
        } else {
            // 触发消息接收事件
            $event = new MessageReceived($message, $user->id);
            Event::fire($event);
            unset($message->token);
            //  WebSocket服务器遍历所有建立连接的有效客户端，并将去掉了 Token 字段的消息广播给它们
            foreach ($server->connections as $fd) {
                if (!$server->isEstablished($fd)) {
                    //
                    continue;
                }
                // 服务端通过 push 方法向所有客户端广播消息
                $server->push($fd, $frame->data);
            }
        }
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
        Log::info('WebSocket链接关闭：' . $fd);
    }
}
