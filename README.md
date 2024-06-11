webchatS
----

参考：https://laravelacademy.org/post/19881



基于Swoole的实时在线聊天室



https://github.com/hhxsv5/laravel-s



## 1 环境准备





## 2 后台数据库和API认证

### 数据库准备

```sh
php artisan make:migration alter_users_add_avatar_column --table=users
```



```sh
php artisan make:model Message -m
```



### API认证



```sh
php artisan make:migration alter_users_add_api_token --table=users
```





## 3 后台WebSocket服务器实现

### 3.1 创建 WebSocketHandler

处理WebSocket通信。

### 3.2 异步事件监听与处理

构建聊天室项目时，除了将消息广播给所有客户端之外，还要保存消息到数据库，而且还会校验用户是否登录，未登录用户不能发送消息。

由于操作数据库是一个涉及到网络 IO 的耗时操作，所以通过 Swoole 提供的[异步事件监听](https://laravelacademy.org/post/19730.html)机制将其转交给 Task Worker 去处理，从而提高 WebSocket 服务器的通信性能。

创建消息接收事件 `MessageReceived`：

```sh
php artisan make:event MessageReceived
```



由于 `Message` 模型只包含创建时间，不包含更新时间，所以显式指定`created_at`字段，另外还要将`Message`模型类的 `$timestamps` 属性设置为 `false`，以避免系统自动为其设置时间字段。

创建消息监听器 `MessageListener` 对上述 `MessageReceived` 事件进行处理：

```sh
php artisan make:listener MessageListener
```



### 3.3 用户认证校验和消息接收事件触发

有了消息接收事件和消息事件监听器后，需要在 WebSocket 服务器收到消息时触发消息接收事件，这个业务逻辑可以在 `WebSocketHandler` 的 `onMessage` 方法中完成。

```php
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
```



首先对接收到的数据进行解码（假设客户端传递过来的是 JSON 字符串），然后判断其中是否包含`token`字段，以及`token`值是否有效，并以此为依据判断用户是否通过认证，对于没有认证的用户，不会广播消息给其他客户端，只是告知该用户需要登录才能发送消息。反之，如果用户已经登录，则触发 `MessageReceived` 事件，并传入消息对象和用户 ID，然后由消息监听器进行后续保存处理，而 WebSocket 服务器则遍历所有建立连接的有效客户端，并将去掉了 Token 字段的消息广播给它们，从而完成聊天消息的一次发送。

> 注：WebSocket连接与之前认证使用的HTTP连接是不同的连接，所以认证逻辑也是独立的，不能简单通过 `Auth` 那种方式判断，那一套逻辑仅适用于 HTTP 通信。



### 3.4 用户认证逻辑调整

调整默认的基于 Token 的用户认证逻辑，当用户注册成功或者登录成功，会更新 `users` 表的 `api_token` 字段值，当用户退出时，则清空该字段值。



### 3.5 WebSocket服务器及异步事件监听配置

改 `config/laravels.php` 配置文件，完成 WebSocket 服务器和异步事件监听配置。

```php
    // 启动 WebSocket 并定义通信处理器
    'websocket' => [
        'enable' => true,
        'handler' => WebSocketHandler::class,
    ],


    // 异步事件即对应监听器的映射关系在 events 配置项中配置
    'events' => [
        // 一个事件可以被多个监听器监听并处理
        \App\Events\MessageReceived::class => [
            \App\Listeners\MessageListener::class
        ]
    ],


```

异步事件的监听和处理是通过 Swoole 的 Task Worker 进程处理的，需要开启 `task_worker_num` 配置:

```php
	'swoole' => [

        'task_worker_num' => function_exists('swoole_cpu_num') ? swoole_cpu_num() * 2 : 8,  // 异步事件的监听和处理需要开启
    
    ]
```



对于基于 Swoole HTTP 服务器运行的 Laravel 应用，由于 Laravel 容器会常驻内存，所以在涉及到用户认证的时候，需要在每次请求后清除本次请求的认证状态，以免被其他用户请求冒用，在配置文件 `laravels.php` 的 `cleaners` 配置项中取消如下这行配置前的注释即可：

```php
    'cleaners' => [
        // 在每次请求后清除本次请求的认证状态，以免被其他用户请求冒用
        \Hhxsv5\LaravelS\Illuminate\Cleaners\AuthCleaner::class,
    ],
```



最后我们在 `.env` 新增如下这两行配置，分别用于指定 Swoole HTTP/WebSocket 服务器运行的 IP 地址和是否后台运行：

```
# Swoole HTTP/WebSocket 服务器运行的 IP 地址
LARAVELS_LISTEN_IP=localhost
# 是否后台运行
LARAVELS_DAEMONIZE=true
```







### 3.6 Nginx 虚拟主机配置



重启 Swoole HTTP 服务器进行验证



🔖

```sh
php bin/laravels restart
[2024-06-01 12:23:59] [WARNING] It seems that Swoole is not running.
 _                               _  _____ 
| |                             | |/ ____|
| |     __ _ _ __ __ ___   _____| | (___  
| |    / _` | '__/ _` \ \ / / _ \ |\___ \ 
| |___| (_| | | | (_| |\ V /  __/ |____) |
|______\__,_|_|  \__,_| \_/ \___|_|_____/ 
                                           
Speed up your Laravel/Lumen
>>> Components
+---------------------------+----------+
| Component                 | Version  |
+---------------------------+----------+
| PHP                       | 8.3.6    |
| Swoole                    | 5.1.2    |
| LaravelS                  | 3.7.38   |
| Laravel Framework [local] | 10.48.12 |
+---------------------------+----------+
>>> Protocols
+-----------+--------+----------------+-----------------------+
| Protocol  | Status | Handler        | Listen At             |
+-----------+--------+----------------+-----------------------+
| Main HTTP | On     | Laravel Router | http://localhost:5200 |
+-----------+--------+----------------+-----------------------+
>>> Feedback: https://github.com/hhxsv5/laravel-s
[2024-06-01 12:24:00] [TRACE] Swoole is running in daemon mode, see "ps -ef|grep laravels".
[2024-06-01 12:24:00] [ERROR] Uncaught exception "Swoole\Exception"([48]failed to listen server port[localhost:5200], Error: Address already in use[48]) at /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/Swoole/Server.php:57, 
#0 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/Swoole/Server.php(57): Swoole\Server->__construct('localhost', 5200, 2, 1)
#1 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/LaravelS.php(50): Hhxsv5\LaravelS\Swoole\Server->__construct(Array)
#2 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/Console/Portal.php(158): Hhxsv5\LaravelS\LaravelS->__construct(Array, Array)
#3 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/Console/Portal.php(215): Hhxsv5\LaravelS\Console\Portal->start()
#4 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/hhxsv5/laravel-s/src/Console/Portal.php(63): Hhxsv5\LaravelS\Console\Portal->restart()
#5 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/vendor/symfony/console/Command/Command.php(326): Hhxsv5\LaravelS\Console\Portal->execute(Object(Symfony\Component\Console\Input\ArgvInput), Object(Symfony\Component\Console\Output\ConsoleOutput))
#6 /Volumes/FX-SSD-PS2000/myfield/gits/webchatS/bin/laravels(167): Symfony\Component\Console\Command\Command->run(Object(Symfony\Component\Console\Input\ArgvInput), Object(Symfony\Component\Console\Output\ConsoleOutput))
#7 {main}

```



## 4 前端资源初始化

前端界面基于 https://github.com/hua1995116/webchat
