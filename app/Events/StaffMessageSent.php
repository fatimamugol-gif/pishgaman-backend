<?php

namespace App\Events;

use App\Models\StaffMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $senderId;
    public $receiverId;

    /**
     * Create a new event instance.
     */
    public function __construct(StaffMessage $message)
    {
        $this->message = $message;
        $this->senderId = $message->sender_id;
        $this->receiverId = $message->receiver_id;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        // Private channel for the receiver
        return new Channel('staff-chat.' . $this->receiverId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'StaffMessageSent';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'id'         => $this->message->id,
            'sender_id'  => $this->senderId,
            'receiver_id'=> $this->receiverId,
            'message'    => $this->message->message,
            'is_read'    => $this->message->is_read,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
