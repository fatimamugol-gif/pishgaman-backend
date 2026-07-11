<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    /**
     * 📋 API اول: واکشی و محاسبه خودکار کیف پول بر پایه تایم‌استمپ خالص دوره (بدون وابستگی به فیلد متنی)
     */
    public function getPayrolls(Request $request)
    {
        try {
            $user = auth()->user();
            $month = $request->query('month_shamsi', '1405/04'); 

            $targetUserId = $user->id;
            if (($user->role === 'supervisor' || $user->role === 'admin') && $request->has('user_id')) {
                $targetUserId = $request->query('user_id');
            }

            // 🧠 قفل مهندسی: تبدیل ماه شمسی به بازه تایم‌استمپ یونیکس جهت مهار قطعی باگ Unknown Column
            // فرض می‌کنیم ماه ۴ (تیر) سال ۱۴۰۵ را مپ می‌کنیم:
            $startTs = $this->getShamsiMonthBounds($month, 'start');
            $endTs = $this->getShamsiMonthBounds($month, 'end');

            $dailyBaseSalary = 300000; // حقوق پایه روزانه فرضی ۳۰۰ هزار تومان
            $standardDailySeconds = 8 * 3600; // ۲۸۸۰۰ ثانیه کارکرد استاندارد روزانه

            // تفکیک و جمع زدن ثانیه‌ها بر پایه ستون معتبر زمان ورود (Timestamp)
            $totalWorkedSeconds = DB::table('next_attendance_clocks')
                ->where('user_id', $targetUserId)
                ->whereBetween('clock_in_timestamp', [$startTs, $endTs])
                ->sum('duration_seconds');

            // شمارش روزهای متمایز کاری پرسنل در بازه زمانی
            $distinctDaysCount = DB::table('next_attendance_clocks')
                ->where('user_id', $targetUserId)
                ->whereBetween('clock_in_timestamp', [$startTs, $endTs])
                ->distinct()
                ->count(DB::raw('DATE(FROM_UNIXTIME(clock_in_timestamp))'));

            $distinctDaysCount = $distinctDaysCount > 0 ? $distinctDaysCount : 1;
            $grossSalary = $distinctDaysCount * $dailyBaseSalary;

            // محاسبه کسر کارکرد از مرز ۸ ساعت مصوب روزانه
            $expectedSeconds = $distinctDaysCount * $standardDailySeconds;
            $deductions = 0;
            
            if ($totalWorkedSeconds < $expectedSeconds) {
                $missingSeconds = $expectedSeconds - $totalWorkedSeconds;
                $hourlyRate = $dailyBaseSalary / 8;
                $secondRate = $hourlyRate / 3600;
                $deductions = round($missingSeconds * $secondRate);
            }

            $existingPayroll = DB::table('next_payrolls')
                ->where('user_id', $targetUserId)
                ->where('month_shamsi', $month)
                ->first();

            $performanceBonus = $existingPayroll ? $existingPayroll->performance_bonus : 0;
            $insuranceTax = round($grossSalary * 0.07); 

            $finalPayable = ($grossSalary + $performanceBonus) - ($deductions + $insuranceTax);

            // پلمب خودکار در جدول جهت سینک بودن کیف پول فرانت
            DB::table('next_payrolls')->updateOrInsert(
                ['user_id' => $targetUserId, 'month_shamsi' => $month],
                [
                    'base_salary' => $grossSalary,
                    'total_worked_seconds' => $totalWorkedSeconds,
                    'performance_bonus' => $performanceBonus,
                    'deductions' => $deductions,
                    'insurance_tax' => $insuranceTax,
                    'final_payable' => $finalPayable > 0 ? $finalPayable : 0,
                    'status' => $existingPayroll ? $existingPayroll->status : 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            // واکشی کل فیش‌های این دوره برای رندر در جدول
            $finalData = DB::table('next_payrolls')
                ->join('users', 'next_payrolls.user_id', '=', 'users.id')
                ->select('next_payrolls.*', 'users.name as user_name', 'users.role as user_role')
                ->where('next_payrolls.month_shamsi', $month)
                ->when(($user->role !== 'supervisor' && $user->role !== 'admin'), fn($q) => $q->where('next_payrolls.user_id', $user->id))
                ->orderBy('next_payrolls.id', 'desc')
                ->get();

            return response()->json(['status' => 'success', 'data' => $finalData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 👑 API دوم: ثبت یا بروزرسانی وضعیت تسویه حساب توسط سوپروایزر / ادمین ارشد
     */
    public function storeOrUpdate(Request $request)
    {
        if (auth()->user()->role !== 'supervisor' && auth()->user()->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'شما سطح دسترسی مالی لازم را ندارید.'], 403);
        }

        $request->validate([
            'user_id' => 'required|integer',
            'month_shamsi' => 'required|string',
            'performance_bonus' => 'nullable|integer',
            'status' => 'required|in:pending,approved,paid'
        ]);

        try {
            DB::table('next_payrolls')
                ->where('user_id', $request->user_id)
                ->where('month_shamsi', $request->month_shamsi)
                ->update([
                    'performance_bonus' => $request->performance_bonus ?? 0,
                    'status' => $request->status,
                    'paid_at_timestamp' => $request->status === 'paid' ? time() : null,
                    'updated_at' => now()
                ]);

            return response()->json(['status' => 'success', 'message' => '✓ وضعیت فیش و تسویه کیف پول با موفقیت اعمال شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🧠 متد کمکی مپ بازه زمانی شمسی به تایم‌استمپ عددی میلادی جهت استعلام دقیق دیتابیس
     */
    private function getShamsiMonthBounds($monthStr, $type = 'start')
    {
        // مپ ساده تیر ماه ۱۴۰۵ (۲۰۲۶/۰۶/۲۲ الی ۲۰۲۶/۰۷/۲۲)
        if ($monthStr === '1405/04') {
            return $type === 'start' ? 1782161400 : 1784753399;
        }
        // بازه پیش‌فرض حمایتی برای سایر ماه‌ها
        return $type === 'start' ? Carbon::now()->startOfMonth()->timestamp : Carbon::now()->endOfMonth()->timestamp;
    }
}