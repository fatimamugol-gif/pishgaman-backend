<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'next_leaves_requests';

    protected $fillable = [
        'user_id',
        'department_id',
        'leave_type',
        'start_date_shamsi',
        'end_date_shamsi',
        'duration_text',
        'reason',
        'status',
        'supervisor_note',
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
        return $this->hasMany(DelayCompensation::class, 'auto_leave_request_id');
    }
}
