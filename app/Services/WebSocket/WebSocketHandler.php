<?php
namespace App\Services\WebSocket;

use App\Events\MessageReceived;
use App\Models\User;
use App\Services\WebSocket\SocketIO\Packet;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Support\Facades\Log;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * WebSocket服务通信处理器类
 */
class WebSocketHandler implements WebSocketHandlerInterface
{
    protected WebSocket $webSocket;

    protected Parser $parser;

    public function __construct()
    {
        $this->webSocket = app(WebSocket::class);
        $this->parser = app(Parser::class);
    }

    /**
     * 连接建立时触发
     * @param Server $server
     * @param Request $request
     * @return void
     */
    public function onOpen(Server $server, Request $request): void
    {
        if (!request()->input('sid')) {
            // 初始化连接信息适配 socket.io-client
            $payload = json_encode([
                'sid' => base64_encode(uniqid()),
                'upgrades' => [],
                'pingInterval' => config('laravels.swoole.heartbeat_idle_time') * 1000,
                'pingTimeout' => config('laravels.swoole.heartbeat_check_interval') * 1000,
            ]);
            $initPayload = Packet::OPEN . $payload;
            $connectPayload = Packet::MESSAGE . Packet::CONNECT;
            $server->push($request->fd, $initPayload);
            $server->push($request->fd, $connectPayload);
        }

        Log::info('WebSocket链接建立:' . $request->fd);

        $payload = [
            'sender'    => $request->fd,
            'fds'       => [$request->fd],
            'broadcast' => false,
            'assigned'  => false,
            'event'     => 'message',
            'message'   => '欢迎访问聊天室',
        ];
        $pusher = Pusher::make($payload, $server);
        $pusher->push($this->parser->encode($pusher->getEvent(), $pusher->getMessage()));
    }

    /**
     * 收到消息时触发
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        // $frame->fd 是客户端 id，$frame->data 是客户端发送的数据
        Log::info("从 {$frame->fd} 接收到的数据： {$frame->data}");

//        $message = json_decode($frame->data);
//        // 基于token的用户校验
//        if (empty($message->token) || !($user = User::query()->where('api_token', $message->token)->first())) {
//            Log::warning('用户' . $message->name . '已经离线，不能发送信息');
//            $server->push($frame->fd, '离线用户不嫩发送消息');
//        } else {
//            // 触发消息接收事件
//            $event = new MessageReceived($message, $user->id);
//            Event::fire($event);
//            unset($message->token);
//            //  WebSocket服务器遍历所有建立连接的有效客户端，并将去掉了 Token 字段的消息广播给它们
//            foreach ($server->connections as $fd) {
//                if (!$server->isEstablished($fd)) {
//                    //
//                    continue;
//                }
//                // 服务端通过 push 方法向所有客户端广播消息
//                $server->push($fd, $frame->data);
//            }
//        }

        if ($this->parser->execute($server, $frame)) {
            // 跳过心跳连接处理
            return;
        }
        $payload = $this->parser->decode($frame);
        ['event' => $event, 'data' => $data] = $payload;
        $payload = [
            'sender' => $frame->fd,
            'fds'    => [$frame->fd],
            'broadcast' => false,
            'assigned'  => false,
            'event'     => $event,
            'message'   => $data,
        ];
        $pusher = Pusher::make($payload, $server);
        $pusher->push($this->parser->encode($pusher->getEvent(), $pusher->getMessage()));
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
        Log::info('WebSocket链接关闭：' . $fd);
    }
}
