<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class HrManagementController extends Controller
{
   /**
     * 🚀 ثبت لایو کلک ورود و خروج با لایه‌های کنترلی و گارد ضد اسپم و فراموشی
     */
    public function toggleClock(Request $request)
    {
        try {
            $user = auth()->user();
            $currentTimestamp = time();
            
            $maxShiftSeconds = 12 * 3600; // سقف مجاز باز ماندن تایمر (۱۲ ساعت)
            $antiSpamSeconds = 30;        // حداقل فاصله مجاز بین دو کلیک (۳۰ ثانیه)

            // استفاده از Transaction برای جلوگیری از ثبت همزمان رکوردهای تکراری در صدم ثانیه
            return DB::transaction(function () use ($user, $currentTimestamp, $maxShiftSeconds, $antiSpamSeconds) {
                
                // ۱. گارد ضد اسپم: بررسی آخرین تغییر وضعیت کاربر
                $lastRecord = DB::table('next_attendance_clocks')
                    ->where('user_id', $user->id)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastRecord) {
                    $lastActiveTs = $lastRecord->clock_out_timestamp ?: $lastRecord->clock_in_timestamp;
                    if (($currentTimestamp - $lastActiveTs) < $antiSpamSeconds) {
                        return response()->json([
                            'status' => 'error', 
                            'message' => '🛑 لطفاً کمی صبر کنید! درخواست‌های متوالی مجاز نیست.'
                        ], 429);
                    }
                }

                // ۲. واکشی تایمر باز با قفل دیتابیس برای جلوگیری از Race Condition
                $activeClock = DB::table('next_attendance_clocks')
                    ->where('user_id', $user->id)
                    ->whereNull('clock_out_timestamp')
                    ->lockForUpdate()
                    ->first();

                // ۳. گارد انقضای هوشمند (سیستم خاموش شده یا فراموشی خروج)
                if ($activeClock && ($currentTimestamp - $activeClock->clock_in_timestamp) > $maxShiftSeconds) {
                    $assumedDuration = 8.5 * 3600; // ۸.۵ ساعت کارکرد فرضی
                    $assumedClockOut = $activeClock->clock_in_timestamp + $assumedDuration;

                    DB::table('next_attendance_clocks')
                        ->where('id', $activeClock->id)
                        ->update([
                            'clock_out_timestamp' => $assumedClockOut,
                            'duration_seconds' => $assumedDuration,
                            'is_auto_closed' => 1, // مارک کردن رکورد به عنوان بسته شده توسط سیستم
                            'updated_at' => now()
                        ]);

                    $activeClock = null; // تایمر خراب بسته شد، پس وضعیت فعلی آزاد است
                }

                // ۴. منطق اصلی ثبت ورود یا خروج
                if (!$activeClock) {
                    // ثبت ورود جدید
                    DB::table('next_attendance_clocks')->insert([
                        'user_id' => $user->id,
                        'clock_in_timestamp' => $currentTimestamp,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    return response()->json([
                        'status' => 'success', 
                        'is_clocked_in' => true, 
                        'message' => '🚀 ورود زنده شما با موفقیت ثبت شد.'
                    ]);
                } else {
                    // ثبت خروج
                    $duration = $currentTimestamp - $activeClock->clock_in_timestamp;

                    DB::table('next_attendance_clocks')
                        ->where('id', $activeClock->id)
                        ->update([
                            'clock_out_timestamp' => $currentTimestamp,
                            'duration_seconds' => $duration > 0 ? $duration : 0,
                            'updated_at' => now()
                        ]);
                    return response()->json([
                        'status' => 'success', 
                        'is_clocked_in' => false, 
                        'message' => '🛑 خروج زنده شما ثبت شد. خسته نباشی مهندس!'
                    ]);
                }
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getClockStatus(Request $request)
    {
        try {
            $user = auth()->user();
            $currentTimestamp = time();
            $maxShiftSeconds = 12 * 3600;
            $startOfDay = \Illuminate\Support\Carbon::today()->timestamp;

            $activeClock = DB::table('next_attendance_clocks')
                ->where('user_id', $user->id)
                ->whereNull('clock_out_timestamp')
                ->first();

            // اگر تایمر قدیمی و منقضی شده پیدا شد، همینجا اصلاحش کن
            if ($activeClock && ($currentTimestamp - $activeClock->clock_in_timestamp) > $maxShiftSeconds) {
                $assumedDuration = 8.5 * 3600;
                DB::table('next_attendance_clocks')
                    ->where('id', $activeClock->id)
                    ->update([
                        'clock_out_timestamp' => $activeClock->clock_in_timestamp + $assumedDuration,
                        'duration_seconds' => $assumedDuration,
                        'is_auto_closed' => 1,
                        'updated_at' => now()
                    ]);
                $activeClock = null;
            }

            $totalSecondsToday = DB::table('next_attendance_clocks')
                ->where('user_id', $user->id)
                ->where('clock_in_timestamp', '>=', $startOfDay)
                ->sum('duration_seconds');

            $liveActiveSeconds = 0;
            if ($activeClock) {
                $liveActiveSeconds = $currentTimestamp - $activeClock->clock_in_timestamp;
            }

            return response()->json([
                'status' => 'success',
                'is_clocked_in' => !empty($activeClock),
                'active_clock_in_timestamp' => $activeClock ? $activeClock->clock_in_timestamp : null,
                'total_seconds_today' => (int)$totalSecondsToday,
                'live_active_seconds' => $liveActiveSeconds,
                'is_friday' => (\Illuminate\Support\Carbon::now()->dayOfWeek === \Illuminate\Support\Carbon::FRIDAY)
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📅 ثبت درخواست مرخصی، ماموریت یا ثبت تردد دستی توسط ناظر
     */
    public function submitLeaveRequest(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|in:daily_vacation,hourly_pass,medical,mission,without_pay',
            'start_timestamp' => 'required|integer',
            'end_timestamp' => 'required|integer',
            'target_user_id' => 'nullable|integer',
            'reason' => 'nullable|string'
        ]);

        try {
            $currentUser = auth()->user();
            
            $finalUserId = $currentUser->id;
            $finalDeptId = $currentUser->department_id;

            // اگر سوپروایزر دستی وارد کرده باشد
            if ($currentUser->role === 'supervisor' && !empty($request->target_user_id)) {
                $finalUserId = $request->target_user_id;
                $finalDeptId = DB::table('users')->where('id', $finalUserId)->value('department_id');
            }

            DB::table('next_leaves_requests')->insert([
                'user_id' => $finalUserId,
                'department_id' => $finalDeptId,
                'leave_type' => $request->leave_type,
                'start_timestamp' => $request->start_timestamp,
                'end_timestamp' => $request->end_timestamp,
                'reason' => $request->reason,
                'status' => ($currentUser->role === 'supervisor') ? 'approved' : 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => '✓ رکورد اداری/مرخصی با موفقیت در دیتابیس هسته پلمب شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📋 واکشی کل لاگ‌های تردد + محاسبات هوشمند سقف و مانده مرخصی استحقاقی پرسنل
     */
    public function getLeavesHistory(Request $request)
    {
        try {
            $user = auth()->user();
            
            // ۱. واکشی کلک‌های ورود و خروج
            $clocksQuery = DB::table('next_attendance_clocks')
                ->join('users', 'next_attendance_clocks.user_id', '=', 'users.id')
                ->select('next_attendance_clocks.*', 'users.name as user_name');
            if ($user->role !== 'supervisor') {
                $clocksQuery->where('next_attendance_clocks.user_id', $user->id);
            }
            $clocks = $clocksQuery->orderBy('next_attendance_clocks.id', 'desc')->get();

            // ۲. واکشی درخواست‌های مرخصی و ماموریت
            $leavesQuery = DB::table('next_leaves_requests')
                ->join('users', 'next_leaves_requests.user_id', '=', 'users.id')
                ->select('next_leaves_requests.*', 'users.name as user_name');
            if ($user->role !== 'supervisor') {
                $leavesQuery->where('next_leaves_requests.user_id', $user->id);
            }
            $leaves = $leavesQuery->orderBy('next_leaves_requests.id', 'desc')->get();

            // ۳. 🎯 موتور محاسباتی مانده مرخصی استحقاقی (Leave Balance)
            // فرض می‌کنیم سقف مرخصی استحقاقی مجاز سالانه ۲۶ روز است
            $allowedLeaveDays = 26; 
            
            // محاسبه تعداد روزهای مرخصی تایید شده کاربر فعلی
            $usedLeaveDays = DB::table('next_leaves_requests')
                ->where('user_id', $user->id)
                ->where('leave_type', 'daily_vacation')
                ->where('status', 'approved')
                ->count(); // هر رکورد تایید شده را ۱ روز فرض میکنیم (میتوان بر پایه تفاضل تایم‌استمپ هم دقیق‌تر کرد)

            $remainingLeaveDays = $allowedLeaveDays - $usedLeaveDays;

            return response()->json([
                'status' => 'success',
                'clocks' => $clocks,
                'leaves' => $leaves,
                'leave_balance' => [
                    'total_allowed' => $allowedLeaveDays,
                    'total_used' => $usedLeaveDays,
                    'remaining' => $remainingLeaveDays > 0 ? $remainingLeaveDays : 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ⚙️ استقرار مناسبت‌های تقویم تعطیلات رسمی توسط ادمین
     */
    public function storeHoliday(Request $request)
    {
        $request->validate(['holiday_date_shamsi' => 'required|string', 'title' => 'required|string']);
        
        DB::table('next_holidays')->insert([
            'holiday_date_shamsi' => $request->holiday_date_shamsi,
            'title' => $request->title,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['status' => 'success', 'message' => '✓ روز تعطیل رسمی با موفقیت در کورتکس سیستم پلمب شد.']);
    }

    public function getShiftsAndHolidays()
    {
        return response()->json([
            'status' => 'success',
            'shifts' => DB::table('next_shifts')->get(),
            'holidays' => DB::table('next_holidays')->get()
        ]);
    }

    // /**
    //  * 👑 گارد ادمین: تخصیص سقف مرخصی اختصاصی برای یک کارشناس خاص
    //  */
    // public function updateCustomLeaveLimit(Request $request)
    // {
    //     $request->validate([
    //         'user_id' => 'required|integer',
    //         'custom_limit' => 'required|integer|min:0'
    //     ]);

    //     DB::table('users')->where('id', $request->user_id)->update([
    //         'custom_leave_limit' => $request->custom_limit,
    //         'updated_at' => now()
    //     ]);

    //     return response()->json(['status' => 'success', 'message' => '✓ سقف مرخصی اختصاصی کارشناس در سرور مرکز پلمب شد.']);
    // }

    public function reviewLeaveRequest(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'supervisor_note' => 'nullable|string'
        ]);

        try {
            $user = auth()->user();
            Log::info('Current logged in user role:', ['role' => auth()->user()->role]);
            if ($user->role !== 'supervisor') {
                return response()->json(['status' => 'error', 'message' => 'شما سطح دسترسی ناظر برای تایید مرخصی را ندارید.'], 403);
            }

            DB::table('next_leaves_requests')->where('id', $id)->update([
                'status' => $request->status,
                'supervisor_note' => $request->supervisor_note,
                'updated_at' => now()
            ]);

            $statusText = $request->status === 'approved' ? 'تایید' : 'رد';
            return response()->json(['status' => 'success', 'message' => "✓ وضعیت درخواست مرخصی با موفقیت به حالت ({$statusText} شده) تغییر یافت."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCustomLeaveLimit(Request $request)
    {
        // 🛡️ گارد امنیتی: فقط سوپروایزر حق تغییر سقف مرخصی را دارد
        if (auth()->user()->role !== 'supervisor') {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز مدیریت سطوح دسترسی اداری را ندارید.'], 403);
        }

        $request->validate([
            'user_id' => 'required|integer',
            'custom_limit' => 'required|integer|min:0'
        ]);

        DB::table('users')->where('id', $request->user_id)->update([
            'custom_leave_limit' => $request->custom_limit,
            'updated_at' => now()
        ]);

        return response()->json(['status' => 'success', 'message' => '✓ سقف مرخصی اختصاصی کارشناس در سرور مرکز پلمب شد.']);
    }

    /**
     * 👑 اتاق پایش ادمین: واکشی و فیلتر اتمیک تمام کارکردهای پرسنل (فیلتر روزانه و کارشناس)
     */
    public function getAllStaffAttendanceLogs(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            $startTimestamp = $request->query('start_timestamp'); // فیلتر بر پایه بازه تایم‌استمپ عدد صحیح
            $endTimestamp = $request->query('end_timestamp');

            $query = DB::table('next_attendance_clocks')
                ->join('users', 'next_attendance_clocks.user_id', '=', 'users.id')
                ->select('next_attendance_clocks.*', 'users.name as user_name', 'users.email as user_email');

            if (!empty($userId)) {
                $query->where('next_attendance_clocks.user_id', $userId);
            }

            if (!empty($startTimestamp) && !empty($endTimestamp)) {
                $query->whereBetween('next_attendance_clocks.clock_in_timestamp', [(int)$startTimestamp, (int)$endTimestamp]);
            }

            $logs = $query->orderBy('next_attendance_clocks.id', 'desc')->get();

            return response()->json(['status' => 'success', 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}