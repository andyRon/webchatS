<?php

namespace App\Services\WebSocket\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * 房间服务的门面代理类
 */
class Room extends Facade
{
    /**
     * 获取组件的注册名称
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'swoole.room';
    }
}
