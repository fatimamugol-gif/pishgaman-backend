<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DelayCompensation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_clock_id',
        'date',
        'delay_minutes',
        'compensation_minutes_required',
        'compensation_minutes_completed',
        'auto_leave_recorded',
        'auto_leave_request_id',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'auto_leave_recorded' => 'boolean',
    ];

    /**
     * رابطه با کاربر
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * رابطه با رکورد تردد
     */
    public function attendanceClock()
    {
        return $this->belongsTo(AttendanceClock::class, 'attendance_clock_id');
    }

    /**
     * رابطه با مرخصی ثبت شده
     */
    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'auto_leave_request_id');
    }

    /**
     * محاسبه میزان جبران خدمت باقی‌مانده
     */
    public function getRemainingCompensationAttribute(): int
    {
        return max(0, $this->compensation_minutes_required - $this->compensation_minutes_completed);
    }

    /**
     * بررسی اینکه آیا جبران خدمت کامل شده است
     */
    public function isCompensationComplete(): bool
    {
        return $this->compensation_minutes_completed >= $this->compensation_minutes_required;
    }
}
