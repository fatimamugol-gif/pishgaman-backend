<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SmartLeadEngine
{
    /**
     * 1️⃣ موتور محاسبه‌گر امتیاز لید (Lead Scoring)
     */
    public function calculateScore($data)
    {
        $score = 0;

        if (!empty($data['age'])) {
            if ($data['age'] >= 18 && $data['age'] <= 28) $score += 30;
            elseif ($data['age'] > 28 && $data['age'] <= 35) $score += 20;
            elseif ($data['age'] > 35) $score += 10;
        }

        if (!empty($data['financial_capability_toman'])) {
            $cap = (int)$data['financial_capability_toman'];
            if ($cap >= 1000000000) $score += 30;
            elseif ($cap >= 500000000) $score += 15;
        }

        if (!empty($data['english_certified_level']) && $data['english_certified_level'] !== 'مدرک ندارد') {
            $score += 20;
        }
        if (!empty($data['german_certified_level']) && $data['german_certified_level'] !== 'مدرک ندارد') {
            $score += 20;
        }

        $edu = $data['education_level'] ?? '';
        if (in_array($edu, ['کارشناسی', 'کارشناسی ارشد', 'دکتری'])) {
            $score += 20;
        }

        return min($score, 100);
    }

  public function findBestAgentForInitialCall($leadData = [])
    {
        // ۱. تشخیص دپارتمان هدف لید
        $departmentId = $leadData['department_id'] ?? 1;

        // ۲. استعلام زنده: آیا فلوچارت و قانون داینامیکی برای این دپارتمان از فرانت چیده شده است؟
        $workflow = DB::table('next_workflows')->where('department_id', $departmentId)->where('is_active', 1)->first();
        
        $targetRole = 'call_center'; // نقش پیش‌فرض ارجاع

        if ($workflow && !empty($leadData)) {
            $rules = json_decode($workflow->flow_rules, true);
            
            // 🧠 پردازش شروط داینامیک فلوچارت (مثال: تفکیک بر اساس امتیاز یا تمکن چیده شده در فرانت)
            $leadScore = $this->calculateScore($leadData);
            if ($leadScore >= ($rules['high_score_threshold'] ?? 80)) {
                $targetRole = $rules['VIP_agent_role'] ?? 'senior_agent'; // ارجاع به کارشناس ارشد طبق فلو
            }
        }

        // ۳. واکشی کارشناسان متصل به این دپارتمان خاص بر اساس نقش فلوچارت
        $agents = DB::table('agents')
            ->where('is_active', 1)
            ->where('department_id', $departmentId) // مچ شدن با دپارتمان فیکس شده
            ->where('role', $targetRole)
            ->get();

        // Fallback: اگر کارشناسی در آن لول نبود، کل کارشناسان فعال دپارتمان را بیاور
        if ($agents->isEmpty()) {
            $agents = DB::table('agents')->where('is_active', 1)->where('department_id', $departmentId)->get();
        }
        if ($agents->isEmpty()) {
            $agents = DB::table('agents')->where('is_active', 1)->get(); // دژ نهایی
        }

        // ۴. موازنه بار کاری (Load Balancing) بین کارشناسان فیلتر شده فلوچارت
        $bestAgentId = null;
        $minLoad = PHP_INT_MAX;

        foreach ($agents as $agent) {
            $pendingTasks = DB::table('next_tasks')
                ->join('leads', 'next_tasks.lead_id', '=', 'leads.id')
                ->where('leads.agent_id', $agent->id)
                ->where('next_tasks.status', 'pending')
                ->count();

            if ($pendingTasks < $minLoad) {
                $minLoad = $pendingTasks;
                $bestAgentId = $agent->id;
            }
        }

        return $bestAgentId;
    }

   /**
     * 3️⃣ موتور ثبت اتو-تسک اولیه (Auto-Task Generator)
     */
    public function generateInitialTask($leadId, $agentId)
    {
        if (!$leadId) return;

        // 💡 استفاده از کلاس استاندارد Morilog\Jalali\Jalalian به جای تابع کمکی jdate
        $shamsiDate = class_exists('\Morilog\Jalali\Jalalian') 
            ? \Morilog\Jalali\Jalalian::now()->format('Y/m/d') 
            : now()->format('Y/m/d');

        DB::table('next_tasks')->insert([
            'lead_id' => $leadId,
            'task_title' => '📞 تماس مشاوره اولیه و وضعیت‌سنجی (Auto-Task)',
            'status' => 'pending',
            'due_date_shamsi' => $shamsiDate, 
            'priority' => 'high',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * 4️⃣ موتور پردازش نتیجه تماس و خلق اتوماتیک تسک بعدی
     */
    public function processCallOutcome($leadId, $agentId, $outcome, $persona = null, $requestedDate = null, $requestedTime = null, $nextStepNote = '')
    {
        $now = Carbon::now();
        $nextDate = null;
        $nextTime = null;
        $priority = 'medium';
        $taskTitle = '';

        // محاسبه تاریخ شمسی امروز برای محاسبات پویای فاصله زمانی
        $todayShamsi = class_exists('\Morilog\Jalali\Jalalian')
            ? \Morilog\Jalali\Jalalian::now()
            : null;

        if ($outcome === 'no_answer' || $outcome === 'not_convenient_no_time') {
            $nextDate = $todayShamsi ? $todayShamsi->format('Y/m/d') : now()->format('Y/m/d');
            $nextTime = $now->copy()->addHours(2)->format('H:i');
            $taskTitle = '📞 پیگیری مجدد (عدم پاسخ/مساعد نبودن)';
            $priority = 'high';
            
        } elseif ($outcome === 'not_convenient_has_time') {
            $nextDate = $requestedDate;
            $nextTime = $requestedTime;
            $taskTitle = '📞 تماس هماهنگ شده (متقاضی زمان داد)';
            
        } elseif ($outcome === 'consultation') {
            $taskTitle = '📞 فالوآپ فروش (' . $persona . ') - اقدام: ' . $nextStepNote;
            
            $followUpStep = DB::table('next_tasks')
                ->where('lead_id', $leadId)
                ->where('task_title', 'LIKE', '%فالوآپ فروش%')
                ->count() + 1;

            $daysToAdd = $this->getPersonaInterval($persona, $followUpStep);
            
            // اضافه کردن فواصل ۱۱ گانه پرسونایی به تاریخ شمسی
            $nextDate = $todayShamsi 
                ? $todayShamsi->addDays($daysToAdd)->format('Y/m/d') 
                : now()->addDays($daysToAdd)->format('Y/m/d');
            $nextTime = '10:00';
        }

        if ($nextDate) {
            DB::table('next_tasks')->insert([
                'lead_id' => $leadId,
                'task_title' => $taskTitle,
                'status' => 'pending',
                'due_date_shamsi' => $nextDate,
                'reminder_time' => $nextTime,
                'priority' => $priority,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::table('leads')->where('id', $leadId)->update([
                'next_call_date_shamsi' => $nextDate,
                'description' => DB::raw("CONCAT(COALESCE(description, ''), '\n', 'آخرین یادداشت: {$nextStepNote}')")
            ]);
        }
    }

    /**
     * 🧠 تکمیل فواصل زمانی ماتریکس بر اساس داکیومنت ۱۱ پرسونای روانشناختی پیشگامان
     */
   private function getPersonaInterval(string $persona, int $step): int
{
    // فواصل زمانی بازگردانی (Retargeting) دقیقاً بر اساس مستندات سیستم پیشگامان
    $intervals = [
        'Goal-Oriented'      => [1, 2, 3, 5, 8, 15, 21],     // retarget-21-15-8-5-3-2-1 [cite: 3]
        'Analytical'         => [1, 2, 5, 10, 20, 30],       // retarget-30-20-10-5-2-1 [cite: 6]
        'Safety-Oriented'    => [1, 2, 3, 5, 10, 15, 21],    // retarget-21-15-10-5-3-2-1 [cite: 9]
        'Explorer'           => [1, 2, 5, 9, 14, 21, 30],    // retarget-30-21-14-9-5-2-1 [cite: 14]
        'Skeptic'            => [1, 2, 4, 8, 16, 25],        // retarget-25-16-8-4-2-1 [cite: 17]
        'Budget-Conscious'   => [1, 2, 5, 9, 14, 21, 28],    // retarget-28-21-14-9-5-2-1 [cite: 20]
        'Family-First'       => [1, 3, 5, 10, 15, 25, 35],   // retarget-35-25-15-10-5-3-1 [cite: 25]
        'Fast-Track'         => [1, 2, 2, 4, 8, 10],         // retarget-10-8-4-2-2-1 
        'Undecided/Passive'  => [1, 2, 4, 8, 15, 25, 45],    // retarget-45-25-15-8-4-2-1 [cite: 31]
        'Opportunity-Driven' => [1, 2, 3, 4, 6, 10, 18],     // retarget-18-10-6-4-3-2-1 [cite: 35]
        'Case-Study-Seeker'  => [1, 2, 3, 6, 10, 15, 25],     // retarget-25-15-10-6-3-2-1 [cite: 40]
    ];

    // تعیین آرایه زمانی پرسونا (در صورت عدم وجود، تیپ تحلیلی به عنوان پیش‌فرض قرار می‌گیرد)
    $personaSequence = $intervals[$persona] ?? $intervals['Analytical'];

    // تبدیل استپ انسانی (شروع از 1) به ایندکس آرایه (شروع از 0)
    // اگر استپ وارد شده خارج از محدوده تعریف شده بود، طبق منطق کلی سیستم 30 روز در نظر گرفته می‌شود.
    return $personaSequence[$step - 1] ?? 30; 
}

    /**
     * 5️⃣ الگوریتم محاسبه‌گر بار کاری کارشناس و تشخیص سرریز (Overflow)
     */
    public function calculateAgentLoadAndOverflow($agentId, $dateShamsi, $shiftDurationMinutes = 240) 
    {
        $tasks = DB::table('next_tasks')
            ->join('leads', 'next_tasks.lead_id', '=', 'leads.id')
            ->where('leads.agent_id', $agentId)
            ->where('next_tasks.due_date_shamsi', $dateShamsi)
            ->where('next_tasks.status', 'pending')
            ->select('next_tasks.*')
            ->get();

        $totalRequiredMinutes = 0;
        $taskCounts = ['coef_1' => 0, 'coef_2' => 0, 'coef_3' => 0];

        foreach ($tasks as $task) {
            $coef = $this->getTaskCoefficient($task->task_title);
            if ($coef === 1) {
                $totalRequiredMinutes += 2;
                $taskCounts['coef_1']++;
            } elseif ($coef === 2) {
                $totalRequiredMinutes += 10;
                $taskCounts['coef_2']++;
            } elseif ($coef === 3) {
                $totalRequiredMinutes += 30;
                $taskCounts['coef_3']++;
            }
        }

        $wastedTime = $shiftDurationMinutes * 0.10;
        $availableTime = $shiftDurationMinutes - $wastedTime;
        $isOverflowing = $totalRequiredMinutes > $availableTime;

        return [
            'total_tasks' => $tasks->count(),
            'task_breakdown' => $taskCounts,
            'required_minutes' => $totalRequiredMinutes,
            'shift_minutes' => $shiftDurationMinutes,
            'wasted_time' => $wastedTime,
            'available_time' => $availableTime,
            'is_overflowing' => $isOverflowing,
            'overflow_minutes' => $isOverflowing ? ($totalRequiredMinutes - $availableTime) : 0
        ];
    }

    private function getTaskCoefficient($title)
    {
        if (str_contains($title, 'عدم پاسخ') || str_contains($title, 'بی پاسخ')) return 1;
        if (str_contains($title, 'مشاوره عالی') || str_contains($title, 'تخصصی')) return 3;
        return 2;
    }
    
}