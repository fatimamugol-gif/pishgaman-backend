<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $callData;

    /**
     * ایجاد نمونه رویداد جدید
     */
    public function __construct($userId, $callData)
    {
        $this->userId = $userId;
        $this->callData = $callData;
    }

    /**
     * کانال مخابره رویداد (کانال عمومی جهت تست و پایداری آنی)
     */
    public function broadcastOn()
    {
        return new Channel('agents.' . $this->userId);
    }

    /**
     * نام اختصاصی رویداد در لایه کلاینت لاراول اکو
     */
    public function broadcastAs()
    {
        return 'IncomingCallEvent';
    }
}