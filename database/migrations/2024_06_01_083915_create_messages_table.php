<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('msg')->comment('文本消息');
            $table->string('img')->comment('图片消息');
            $table->bigInteger('user_id');
            $table->smallInteger('room_id')->comment('房间id');
            $table->timestamp('created_at');
            // 消息发送后不可更改，不需要更新时间
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
