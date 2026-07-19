<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DelayCompensationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'delay_start_minutes',
        'delay_end_minutes',
        'compensation_minutes',
        'auto_leave_hours',
        'auto_leave_duration_hours',
        'is_active',
    ];

    protected $casts = [
        'auto_leave_hours' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * بررسی اینکه آیا یک مقدار تاخیر خاص در این بازه قرار می‌گیرد
     */
    public function isInRange(int $delayMinutes): bool
    {
        return $delayMinutes >= $this->delay_start_minutes && $delayMinutes < $this->delay_end_minutes;
    }
}
