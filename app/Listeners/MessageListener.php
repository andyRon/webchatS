<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\Task\Listener;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * MessageReceived事件的消息监听器
 */
class MessageListener extends Listener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * @param MessageReceived|Event $event
     */
    public function handle(MessageReceived|Event $event): void
    {
        $message = $event->getData();
        Log::info(__CLASS__ . ':开始处理', $message->toArray());
        if ($message && $message->user_id && $message->room_id && ($message->msg || $message->img)) {
            $message->save();
            Log::info(__CLASS__ . ':处理完毕');
        } else {
            Log::error(__CLASS__ . ':消息字段缺失，无法保存');
        }
    }
}
