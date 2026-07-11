<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    protected $fillable = [
        'lead_id',
        'channel',
        'sender_type',
        'message',
        'is_analyzed',
    ];

    // رابطه با لید
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}