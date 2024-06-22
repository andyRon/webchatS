<?php

namespace App\Services\WebSocket;

use \Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use InvalidArgumentException;

/**
 * 用户认证相关业务逻辑
 */
trait Authenticatable
{
    protected $userId;

    /**
     * 使用当前用户登录。
     *
     * @param AuthenticatableContract $user
     * @return mixed
     */
    public function loginUsing(AuthenticatableContract $user): mixed
    {
        return $this->loginUsingId($user->getAuthIdentifier());
    }

    /**
     * 使用当前用户Id登录。
     *
     * @param $userId
     * @return mixed
     */
    public function loginUsingId($userId)
    {
        return $this->join(static::USER_PREFIX . $userId);
    }

    /**
     * 使用当前发送的 fd 注销。
     * @return mixed
     */
    public function logout()
    {
        if (is_null($userId = $this->getUserId())) {
            return null;
        }

        return $this->leave(static::USER_PREFIX . $userId);
    }

    /**
     * 按用户设置多个接收方的 fds。
     * @param $users
     * @return Authenticatable
     */
    public function toUser($users)
    {
        $users = is_object($users) ? func_get_args() : $users;

        $userIds = array_map(function (AuthenticatableContract $user) {
            $this->checkUser($user);

            return $user->getAuthIdentifier();
        }, $users);

        return $this->toUserId($userIds);
    }

    /**
     * 通过 userId 设置多个接收方的 fds。
     * @param $userIds
     * @return Authenticatable
     */
    public function toUserId($userIds)
    {
        $userIds = is_string($userIds) || is_integer($userIds) ? func_get_args() : $userIds;

        foreach ($userIds as $userId) {
            $fds = $this->room->getClients(static::USER_PREFIX . $userId);
            $this->to($fds);
        }

        return $this;
    }

    /**
     * 通过发件人的 fd 获取当前的身份验证用户 ID。
     */
    public function getUserId()
    {
        if (! is_null($this->userId)) {
            return $this->userId;
        }

        $rooms = $this->room->getRooms($this->getSender());

        foreach ($rooms as $room) {
            if (count($explode = explode(static::USER_PREFIX, $room)) === 2) {
                $this->userId = $explode[1];
            }
        }

        return $this->userId;
    }

    /**
     * 通过用户Id检测用户是否在线
     * @param $userId
     * @return bool
     */
    public function isUserIdOnline($userId)
    {
        return ! empty($this->room->getClients(static::USER_PREFIX . $userId));
    }

    /**
     * 核查用户是否实现AuthenticatableContract.
     * @param $user
     */
    protected function checkUser($user): void
    {
        if (! $user instanceOf AuthenticatableContract) {
            throw new InvalidArgumentException('user object must implement ' . AuthenticatableContract::class);
        }
    }
}
