<?php

namespace App\Services;

use App\Models\DelayCompensationRule;
use App\Models\DelayCompensation;
use App\Models\AttendanceClock;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DelayCompensationService
{
    /**
     * پردازش و محاسبه جبران تاخیر برای یک رکورد تردد
     */
    public function processAttendanceDelay(AttendanceClock $attendance): ?DelayCompensation
    {
        // 1. دریافت شیفت فعال کاربر
        $shift = $this->getUserShift($attendance->user_id);
        if (!$shift) {
            return null; // بدون شیفت، قانونی اعمال نمی‌شود
        }

        // 2. تبدیل timestamp به تاریخ شمسی برای بررسی تعطیلی
        $clockInTime = Carbon::createFromTimestamp($attendance->clock_in_timestamp);
        $dateShamsi = $this->convertToShamsi($clockInTime);

        // 3. بررسی اینکه روز تعطیل نیست
        if ($this->isHoliday($dateShamsi)) {
            return null; // روز تعطیل، قانون اعمال نمی‌شود
        }

        // 4. بررسی اینکه کاربر مرخصی روزانه یا ساعتی ندارد
        if ($this->hasLeaveOnDate($attendance->user_id, $dateShamsi)) {
            return null; // مرخصی ثبت شده، قانون اعمال نمی‌شود
        }

        // 5. محاسبه میزان تاخیر
        $delayMinutes = $this->calculateDelayMinutes($attendance->clock_in_timestamp, $shift->shift_start);
        
        if ($delayMinutes <= 0) {
            return null; // بدون تاخیر
        }

        // 6. یافتن قانون مناسب
        $rule = $this->findApplicableRule($delayMinutes);
        if (!$rule) {
            return null; // قانونی یافت نشد
        }

        // 7. ایجاد یا به‌روزرسانی رکورد جبران تاخیر
        return $this->createOrUpdateCompensation($attendance, $delayMinutes, $rule, $dateShamsi);
    }

    /**
     * دریافت شیفت فعال کاربر
     */
    private function getUserShift(int $userId): ?object
    {
        // تلاش برای دریافت شیفت اختصاصی کاربر
        $userShift = DB::table('user_shift_assignments')
            ->join('next_shifts', 'user_shift_assignments.shift_id', '=', 'next_shifts.id')
            ->where('user_shift_assignments.user_id', $userId)
            ->where('user_shift_assignments.is_active', true)
            ->select('next_shifts.*')
            ->first();

        if ($userShift) {
            return $userShift;
        }

        // در صورت عدم وجود شیفت اختصاصی، اولین شیفت فعال را برمی‌گردانیم
        return DB::table('next_shifts')->first();
    }

    /**
     * بررسی تعطیلی روز
     */
    private function isHoliday(string $dateShamsi): bool
    {
        return DB::table('next_holidays')->where('holiday_date_shamsi', $dateShamsi)->exists();
    }

    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    private function convertToShamsi(Carbon $date): string
    {
        // استفاده از کتابخانه تبدیل تاریخ یا فرمت ساده
        // در اینجا از فرمت ساده استفاده می‌کنیم
        return $date->format('Y/m/d');
    }

    /**
     * بررسی مرخصی کاربر در تاریخ مشخص
     */
    private function hasLeaveOnDate(int $userId, string $dateShamsi): bool
    {
        return DB::table('next_leaves_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('start_date_shamsi', '<=', $dateShamsi)
            ->where('end_date_shamsi', '>=', $dateShamsi)
            ->whereIn('leave_type', ['daily', 'hourly'])
            ->exists();
    }

    /**
     * محاسبه دقیقه تاخیر
     */
    private function calculateDelayMinutes($clockInTimestamp, string $shiftStart): int
    {
        if (!$clockInTimestamp) return 0;
        
        $clockInTime = Carbon::createFromTimestamp($clockInTimestamp);
        $shiftStartTime = Carbon::parse($clockInTime->format('Y-m-d') . ' ' . $shiftStart);
        
        // اگر ورود بعد از شروع شیفت باشد
        if ($clockInTime > $shiftStartTime) {
            return $clockInTime->diffInMinutes($shiftStartTime);
        }
        
        return 0;
    }

    /**
     * یافتن قانون مناسب بر اساس میزان تاخیر
     */
    private function findApplicableRule(int $delayMinutes): ?DelayCompensationRule
    {
        return DelayCompensationRule::where('is_active', true)
            ->where('delay_start_minutes', '<=', $delayMinutes)
            ->where('delay_end_minutes', '>', $delayMinutes)
            ->first();
    }

    /**
     * ایجاد یا به‌روزرسانی رکورد جبران تاخیر
     */
    private function createOrUpdateCompensation(
        AttendanceClock $attendance, 
        int $delayMinutes, 
        DelayCompensationRule $rule,
        string $dateShamsi
    ): DelayCompensation {
        // بررسی وجود رکورد قبلی
        $existingCompensation = DelayCompensation::where('attendance_clock_id', $attendance->id)->first();
        
        if ($existingCompensation) {
            // به‌روزرسانی رکورد موجود
            $existingCompensation->update([
                'delay_minutes' => $delayMinutes,
                'compensation_minutes_required' => $rule->compensation_minutes,
                'notes' => "قانون: {$rule->rule_name}",
            ]);
            
            // اگر قانون مرخصی خودکار دارد و قبلاً ثبت نشده
            if ($rule->auto_leave_hours && !$existingCompensation->auto_leave_recorded) {
                $this->recordAutoLeave($existingCompensation, $rule, $dateShamsi);
            }
            
            return $existingCompensation->fresh();
        }
        
        // ایجاد رکورد جدید
        $clockInTime = Carbon::createFromTimestamp($attendance->clock_in_timestamp);
        $compensation = DelayCompensation::create([
            'user_id' => $attendance->user_id,
            'attendance_clock_id' => $attendance->id,
            'date' => $clockInTime->toDateString(),
            'delay_minutes' => $delayMinutes,
            'compensation_minutes_required' => $rule->compensation_minutes,
            'compensation_minutes_completed' => 0,
            'auto_leave_recorded' => false,
            'notes' => "قانون: {$rule->rule_name}",
        ]);
        
        // اگر قانون مرخصی خودکار دارد
        if ($rule->auto_leave_hours) {
            $this->recordAutoLeave($compensation, $rule, $dateShamsi);
        }
        
        return $compensation;
    }

    /**
     * ثبت مرخصی خودکار
     */
    private function recordAutoLeave(DelayCompensation $compensation, DelayCompensationRule $rule, string $dateShamsi): void
    {
        try {
            $attendance = $compensation->attendanceClock;
            
            $leaveRequest = LeaveRequest::create([
                'user_id' => $compensation->user_id,
                'department_id' => DB::table('users')->where('id', $compensation->user_id)->value('department_id'),
                'leave_type' => 'hourly',
                'start_date_shamsi' => $dateShamsi,
                'end_date_shamsi' => $dateShamsi,
                'duration_text' => "{$rule->auto_leave_duration_hours} ساعت",
                'reason' => 'مرخصی خودکار به دلیل تاخیر بیش از حد',
                'status' => 'approved', // تایید خودکار
                'supervisor_note' => 'ثبت خودکار سیستم بر اساس قانون جبران تاخیر',
            ]);
            
            // به‌روزرسانی رکورد جبران تاخیر
            $compensation->update([
                'auto_leave_recorded' => true,
                'auto_leave_request_id' => $leaveRequest->id,
            ]);
            
        } catch (\Exception $e) {
            \Log::error("خطا در ثبت مرخصی خودکار: " . $e->getMessage());
        }
    }

    /**
     * ثبت جبران خدمت انجام شده
     */
    public function recordCompensationCompleted(int $compensationId, int $minutesCompleted): bool
    {
        try {
            $compensation = DelayCompensation::findOrFail($compensationId);
            
            $compensation->update([
                'compensation_minutes_completed' => $compensation->compensation_minutes_completed + $minutesCompleted,
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("خطا در ثبت جبران خدمت: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت گزارش جبران تاخیر کاربر
     */
    public function getUserCompensationReport(int $userId, string $startDate = null, string $endDate = null)
    {
        $query = DelayCompensation::with(['attendanceClock', 'leaveRequest'])
            ->where('user_id', $userId);
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
        
        return $query->orderBy('date', 'desc')->get();
    }
}
