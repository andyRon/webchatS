<?php

namespace App\Services\WebSocket\Rooms;

/**
 * 房间的接口
 */
interface RoomContract
{
    public const ROOMS_KEY = 'rooms';

    /**
     * 描述符key
     */
    public const DESCRIPTORS_KEY = 'fds';

    /**
     * 在工作线程开始之前做一些初始化工作。
     * @return RoomContract
     */
    public function prepare(): RoomContract;

    /**
     * 将多个套接字fd添加到一个房间。
     * @param int $fd
     * @param $rooms
     */
    public function add(int $fd, $rooms);

    /**
     * 从房间中删除多个套接字fd。
     * @param int $fd
     * @param array|string $rooms
     */
    public function delete(int $fd, array|string $rooms);

    /**
     * 通过房间key获取所有套接字。
     * @param string $room
     * @return array
     */
    public function getClients(string $room): array;

    /**
     * 通过fd获取所有房间
     * @param int $fd
     * @return array
     */
    public function getRooms(int $fd): array;

}
