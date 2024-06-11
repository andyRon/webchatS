<?php

namespace App\Events;

use App\Models\Message;
use Carbon\Carbon;
use Hhxsv5\LaravelS\Swoole\Task\Event;

/**
 * 消息接收事件
 */
class MessageReceived extends Event
{
    private $message;
    private $userId;

    /**
     * Create a new event instance.
     */
    public function __construct($message, $userId = 0)
    {
        $this->message = $message;
        $this->userId = $userId;
    }

    public function getData(): Message
    {
        $model = new Message();
        $model->room_id = $this->message->room_id;
        $model->msg = $this->message->type == 'text' ? $this->message->content : '';
        $model->img = $this->message->type == 'image' ? $this->message->image : '';
        $model->user_id = $this->userId;
        $model->created_at = Carbon::now();
        return $model;
    }

}
