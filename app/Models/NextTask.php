<?php

use Illuminate\Database\Eloquent\Model;


class NextTask extends Model
{
protected $casts = [
    'has_reminder' => 'boolean',
    'due_date_at' => 'datetime',
    'start_date_at' => 'datetime',
    'reminder_at' => 'datetime',
    'completed_at' => 'datetime',
];
}