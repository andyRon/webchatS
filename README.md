webchats
----

参考：https://laravelacademy.org/post/19881



基于Swoole的实时在线聊天室



https://github.com/hhxsv5/laravel-s





## 后台WebSocket服务器实现





创建 WebSocketHandler
异步事件监听与处理
用户认证校验和消息接收事件触发
用户认证逻辑调整

### WebSocket服务器及异步事件监听配置



对于基于 Swoole HTTP 服务器运行的 Laravel 应用，由于 Laravel 容器会常驻内存，所以在涉及到用户认证的时候，需要在每次请求后清除本次请求的认证状态，以免被其他用户请求冒用，在配置文件 `laravels.php` 的 `cleaners` 配置项中取消如下这行配置前的注释即可：

```php
    'cleaners' => [
        // 在每次请求后清除本次请求的认证状态，以免被其他用户请求冒用
        \Hhxsv5\LaravelS\Illuminate\Cleaners\AuthCleaner::class,
    ],
```







Nginx 虚拟主机配置
重启 Swoole HTTP 服务器进行验证
