<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceClock extends Model
{
    use HasFactory;

    protected $table = 'next_attendance_clocks';

    protected $fillable = [
        'user_id',
        'date_shamsi',
        'clock_in',
        'clock_in_timestamp',
        'clock_out',
        'duration_seconds',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * رابطه با کاربر
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * رابطه با جبران تاخیرها
     */
    public function delayCompensations()
    {
        return $this->hasMany(DelayCompensation::class, 'attendance_clock_id');
    }
}
