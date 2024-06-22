<?php

use App\Services\WebSocket\WebSocket;
use App\Services\WebSocket\Facades\Websocket as WebsocketProxy;
use Swoole\Http\Request;

WebsocketProxy::on('connect', function (WebSocket $webSocket, Request $request) {
    // 发送欢迎信息
    $webSocket->setSender($request->fd);
    $webSocket->emit('connect', '欢迎访问聊天室');
});

WebsocketProxy::on('disconnect', function (WebSocket $websocket) {
});

WebsocketProxy::on('login', function (WebSocket $websocket, $data) {
    if (!empty($data['token']) && ($user = \App\User::where('api_token', $data['token'])->first())) {
        $websocket->loginUsing($user);
        // todo 读取未读消息
        $websocket->toUser($user)->emit('login', '登录成功');
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});
