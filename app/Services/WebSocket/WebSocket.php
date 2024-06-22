<?php

namespace App\Services\WebSocket;

use App\Services\WebSocket\Rooms\RoomContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use Swoole\WebSocket\Server;

/**
 * WebSocket整体服务类
 */
class WebSocket
{
    use Authenticatable;

    const PUSH_ACTION = 'push';
    const EVENT_CONNECT = 'connect';
    const USER_PREFIX = 'uid_';

    /**
     * Websocket服务器
     * @var Server
     */
    protected Server $server;

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

    protected  RoomContract $room;

    protected Container $container;

    public function __construct(RoomContract $room)
    {
        $this->room = $room;
    }

    public function broadcast(): self
    {
        $this->isBroadcast = true;
        return $this;
    }

    /**
     * 设置多个收件人 fd 或房间名称。
     * @param $values
     * @return $this
     */
    public function to($values): self
    {
        $values = is_string($values) || is_integer($values) ? func_get_args() : $values;

        foreach ($values as $value) {
            if (! in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * 将当前用户加入某个房间
     * @param array|string $rooms
     * @return $this
     */
    public function join(array|string $rooms): self
    {
        $rooms = is_string($rooms) || is_integer($rooms) ? func_get_args() : $rooms;

        $this->room->add($this->sender, $rooms);

        return $this;
    }

    /**
     * 离开某个房间
     * @param array $rooms
     * @return $this
     */
    public function leave(array $rooms = []): self
    {
        $rooms = is_string($rooms) || is_integer($rooms) ? func_get_args() : $rooms;

        $this->room->delete($this->sender, $rooms);

        return $this;
    }

    /**
     * 发出数据并重置某些状态。
     * @param string $event
     * @param $data
     * @return bool
     */
    public function emit(string $event, $data): bool
    {
        $fds = $this->getFds();
        $assigned = ! empty($this->to);

        // if no fds are found, but rooms are assigned
        // that means trying to emit to a non-existing room
        // skip it directly instead of pushing to a task queue
        if (empty($fds) && $assigned) {
            return false;
        }

        $payload = [
            'sender'    => $this->sender,
            'fds'       => $fds,
            'broadcast' => $this->isBroadcast,
            'assigned'  => $assigned,
            'event'     => $event,
            'message'   => $data,
        ];

        $server = app('swoole');
        $pusher = Pusher::make($payload, $server);
        $parser = app('swoole.parser');
        $pusher->push($parser->encode($pusher->getEvent(), $pusher->getMessage()));

        $this->reset();

        return true;
    }

    /**
     * 同join
     */
    public function in($room)
    {
        $this->join($room);

        return $this;
    }

    /**
     * 使用闭包绑定注册事件名称。
     * @param string $event
     * @param $callback
     * @return $this
     */
    public function on(string $event, $callback): self
    {
        if (! is_string($callback) && ! is_callable($callback)) {
            throw new \InvalidArgumentException(
                'Invalid websocket callback. Must be a string or callable.'
            );
        }

        $this->callbacks[$event] = $callback;

        return $this;
    }

    /**
     * 检查此事件名称是否存在。
     * @param string $event
     * @return bool
     */
    public function eventExists(string $event): bool
    {
        return array_key_exists($event, $this->callbacks);
    }

    /**
     * 按事件名称执行回调函数。
     * @param string $event
     * @param $data
     * @return mixed|null
     */
    public function call(string $event, $data = null)
    {
        if (! $this->eventExists($event)) {
            return null;
        }

        // inject request param on connect event
        $isConnect = $event === static::EVENT_CONNECT;
        $dataKey = $isConnect ? 'request' : 'data';

        return App::call($this->callbacks[$event], [
            'websocket' => $this,
            $dataKey => $data,
        ]);
    }

    /**
     * 设置发送方 fd
     * @param int $fd
     * @return $this
     */
    public function setSender(int $fd): self
    {
        $this->sender = $fd;

        return $this;
    }

    /**
     * 得到当前发送方fd
     * @return int
     */
    public function getSender(): int
    {
        return $this->sender;
    }

    /**
     * 获取广播状态值
     */
    public function getIsBroadcast(): bool
    {
        return $this->isBroadcast;
    }

    /**
     * 获取推送目标（fd或房间名称）。
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * 获取我们将要推送数据的所有 fds。
     */
    protected function getFds(): array
    {
        $fds = array_filter($this->to, function ($value) {
            return is_integer($value);
        });
        $rooms = array_diff($this->to, $fds);

        foreach ($rooms as $room) {
            $clients = $this->room->getClients($room);
            // fallback fd with wrong type back to fds array
            if (empty($clients) && is_numeric($room)) {
                $fds[] = $room;
            } else {
                $fds = array_merge($fds, $clients);
            }
        }

        return array_values(array_unique($fds));
    }

    /**
     * 重置某些数据的状态，避免复用。
     *
     * @param bool $force
     * @return $this
     */
    public function reset(bool $force = false): self
    {
        $this->isBroadcast = false;
        $this->to = [];

        if ($force) {
            $this->sender = null;
            $this->userId = null;
        }

        return $this;
    }
}
