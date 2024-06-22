<?php

namespace App\Services\WebSocket\Rooms;


use Illuminate\Support\Arr;
use Predis\Client as RedisClient;
use Predis\Pipeline\Pipeline;

/**
 * 基于Redis作为存储媒介
 */
class RedisRoom implements RoomContract
{
    protected RedisClient $redis;

    protected array $confg;

    protected string $prefix = 'swoole:';

    public function __construct(array $confg)
    {
        $this->confg = $confg;
    }

    /**
     * @inheritDoc
     */
    public function prepare(RedisClient $redis = null): RoomContract
    {
        $this->setRedis($redis);
        $this->setPrefix();
        $this->clearRooms();

        return $this;
    }

    public function setRedis(?RedisClient $redis = null): void
    {
        if (!$redis) {
            $server = Arr::get($this->confg, 'server', []);
            $options = Arr::get($this->confg, 'options', []);
            if (Arr::has($options, 'prefix')) {
                $options = Arr::except($options, 'prefix');
            }
            $redis = new RedisClient($server, $options);
        }
        $this->redis = $redis;
    }

    public function setPrefix(): void
    {
        if ($prefix = Arr::get($this->confg, 'prefix')) {
            $this->prefix = $prefix;
        }
    }

    public function getRedis(): RedisClient
    {
        return $this->redis;
    }

    protected function clearRooms(): void
    {
        if (count($keys = $this->redis->keys("{$this->prefix}*"))) {
            $this->redis->del($keys);
        }
    }

    /**
     * @inheritDoc
     */
    public function add(int $fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];

        $this->addValue($fd, $rooms, RoomContract::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->addValue($room, [$fd], RoomContract::ROOMS_KEY);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(int $fd, array|string $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        $rooms = count($rooms) ? $rooms : $this->getRooms($fd);

        $this->removeValue($fd, $rooms, RoomContract::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->removeValue($room, [$fd], RoomContract::ROOMS_KEY);
        }
    }

    /**
     * @inheritDoc
     */
    public function getClients(string $room): array
    {
        return $this->getValue($room, RoomContract::ROOMS_KEY) ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getRooms(int $fd): array
    {
        return $this->getValue($fd, RoomContract::DESCRIPTORS_KEY) ?? [];
    }

    /**
     * 向redis添加值
     * @param $key
     * @param array $values
     * @param string $table
     * @return $this
     */
    public function addValue($key, array $values, string $table): RedisRoom
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $this->redis->pipeline(function (Pipeline $pipe) use ($redisKey, $values) {
            foreach ($values as $value) {
                $pipe->srem($redisKey, $value);
            }
        });

        return $this;
    }

    public function removeValue($key, array $values, string $table): RedisRoom
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $this->redis->pipeline(function (Pipeline $pipe) use ($redisKey, $values) {
            foreach ($values as $value) {
                $pipe->srem($redisKey, $value);
            }
        });

        return $this;
    }

    public function getValue(string $key, string $table): array
    {
        $this->checkTable($table);
        $result = $this->redis->smembers($this->getKey($key, $table));

        return is_array($result) ? $result : [];
    }

    /**
     * 检查表中的房间和描述符
     * @param string $table
     * @return void
     */
    protected function checkTable(string $table): void
    {
        if (! in_array($table, [RoomContract::ROOMS_KEY, RoomContract::DESCRIPTORS_KEY])) {
            throw new \InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }

    public function getKey(string $key, string $table)
    {
        return "{$this->prefix}{$table}:{$key}";
    }
}
