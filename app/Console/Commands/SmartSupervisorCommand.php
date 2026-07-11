<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\SmartLeadEngine;
use App\Services\LeadRoutingService;
use App\Models\Lead;

class SmartSupervisorCommand extends Command
{
    /**
     * نام و امضای مقتدرانه فرمان ناظر در خط فرمان
     */
    protected $signature = 'crm:smart-supervisor';

    /**
     * توضیحات تفصیلی وظایف ناظر هوشمند سیستم
     */
    protected $description = 'کامل‌ترین ناظر هوشمند پیشگامان: مدیریت زنده کلندر، وضعیت اورژانسی، زمان پرت ساعتی، تماس‌های وضعیت‌سنجی نشده و سرریز بار کاری';

    /**
     * اجرای متمرکز فرآیند پایش ۳۶۰ درجه
     */
    public function handle()
    {
        $this->info("🕵️‍♂️ [SMART SUPERVISOR CORE] Initializing comprehensive system audit...");
        Log::info("🕵️‍♂️ [SMART SUPERVISOR CORE] Comprehensive system audit cycle triggered.");

        $leadEngine = app(SmartLeadEngine::class);
        $routingService = app(LeadRoutingService::class);

        // ۱. استخراج تاریخ شمسی امروز برای بررسی تطابق تسک‌ها و کلندر
        $todayShamsi = class_exists('\Morilog\Jalali\Jalalian')
            ? \Morilog\Jalali\Jalalian::now()->format('Y/m/d')
            : now()->format('Y/m/d');

        // اجرای گام‌به‌گام و مقتدرانه فازهای نظارتی مطابق سند متقاضی
        $this->auditEmergencyFlags();
        $this->auditUnsubmittedOutcomes();
        $this->auditDeadLeads($routingService);
        $this->auditExpiredAndOverdueTasks($todayShamsi);
        $this->auditAgentLoadAndAdvancedRebalance($leadEngine, $todayShamsi);

        $this->info("✅ [SMART SUPERVISOR CORE] Comprehensive audit cycle successfully finalized.");
        return Command::SUCCESS;
    }

    /**
     * 🔥 فاز ۱: پایش زنده وضعیت اورژانسی کارشناسان (Emergency Flag Watchdog)
     * در صورت فعال بودن وضعیت اورژانسی، اعلان آنی به سوپروایزر شلیک شده و تخصیص لید متوقف می‌شود.
     */
    private function auditEmergencyFlags()
    {
        $emergencyAgents = DB::table('agents')
            ->where('is_active', 1)
            ->where('is_emergency', 1) // فیلد وضعیت اورژانسی کارمند [cite: 66]
            ->get();

        foreach ($emergencyAgents as $agent) {
            Log::critical("🚨 [EMERGENCY ALERT] Agent ID {$agent->id} ({$agent->name}) activated Emergency Pause! Reporting immediately to CRM Manager."); // [cite: 67]
            
            // شلیک رویداد ریل‌تایم به وب‌سوکت ادمین اصلی (User ID 1) جهت اطلاع‌رسانی آنی
            event(new \App\Events\IncomingCallEvent(1, [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'status' => 'emergency_pause_activated',
                'message' => "کارشناس {$agent->name} وضعیت اورژانسی اعلام کرد. وقفه نیم‌ساعته ایجاد شد." // 
            ]));
        }
    }

    /**
     * 📞 فاز ۲: شکار تماس‌های وضعیت‌سنجی نشده (The Unsubmitted Outcome Grabber)
     * ردیابی تماس‌هایی که قطع شده‌اند (Hangup) اما کارشناس تب وضعیت‌سنجی را پر نکرده یا بسته است.
     */
    private function auditUnsubmittedOutcomes()
    {
        // بررسی تماس‌هایی که بیش از ۵ دقیقه از قطع شدن آن‌ها گذشته اما هنوز وضعیت لید آپدیت نشده است
        $threshold = Carbon::now()->subMinutes(5);

        $unsubmittedCalls = DB::table('voip_call_stats')
            ->where('disposition', 'ANSWERED')
            ->where('created_at', '<', $threshold)
            ->where('is_outcome_submitted', 0) // فیلد ردیابی پر شدن فرم وضعیت‌سنجی 
            ->get();

        foreach ($unsubmittedCalls as $call) {
            Log::error("❌ [UNSUBMITTED OUTCOME DISCOVERED] Lead ID or Phone {$call->customer_phone} has an unsubmitted consultation form by Extension {$call->agent_extension}."); // 

            // علامت‌گذاری به عنوان وضعیت‌سنجی نشده جهت جلوگیری از پردازش تکراری ناظر
            DB::table('voip_call_stats')->where('unique_id', $call->unique_id)->update(['is_outcome_submitted' => -1]);

            // شلیک مستقیم گزارش به ادمین/سوپروایزر مربوطه مطابق مستندات صفحه ۴ 
            event(new \App\Events\IncomingCallEvent(1, [
                'customer_phone' => $call->customer_phone,
                'agent_extension' => $call->agent_extension,
                'status' => 'unsubmitted_call_report',
                'message' => "هشدار ناظر: تماس وضعیت‌سنجی نشده برای شماره {$call->customer_phone} ثبت شد." // 
            ]));
        }
    }

    /**
     * 🔍 فاز ۳: پایش و نجات لیدهای بلاتکلیف و رها شده (Dead Leads Monitor)
     * انتقال لیدهای بدون تسک یا رها شده به چرخه هوش مصنوعی یا کارشناس خلوت بعدی
     */
    private function auditDeadLeads($routingService)
    {
        $thresholdTime = Carbon::now()->subHours(2); // بازه ۲ ساعته معطلی پیش‌فرض [cite: 68, 75, 79]

        $deadLeads = DB::table('leads')
            ->where('status', 'assigned')
            ->whereNotNull('agent_id')
            ->where('updated_at', '<', $thresholdTime)
            ->get();

        foreach ($deadLeads as $lead) {
            $hasPendingTask = DB::table('next_tasks')
                ->where('lead_id', $lead->id)
                ->where('status', 'pending')
                ->exists();

            if (!$hasPendingTask) {
                Log::warning("🚨 [SUPERVISOR] Lead ID {$lead->id} is stranded with no auto-tasks. Re-routing immediately.");
                
                // آزادسازی اصولی ظرفیت کارشناس قبلی [cite: 160]
                DB::table('agents')->where('id', $lead->agent_id)->where('current_active_leads', '>', 0)->decrement('current_active_leads');

                // ری‌ست وضعیت لید به نیو جهت ورود مجدد به هاب مچ‌میکینگ
                DB::table('leads')->where('id', $lead->id)->update(['agent_id' => null, 'status' => 'new']);
                
                $behavioralData = json_decode($lead->behavioral_data, true) ?? [];
                $routingService->assignToBestAgent($lead->id, $behavioralData);
            }
        }
    }

    /**
     * 📅 فاز ۴: پایش تسک‌های معوق از روزهای گذشته (Overdue Queue Manager)
     * اعمال اولویت‌بندی بر اساس رتبه بالا در زمان انتقال تسک‌های منقضی شده
     */
    private function auditExpiredAndOverdueTasks($todayShamsi)
    {
        $expiredTasks = DB::table('next_tasks')
            ->where('status', 'pending')
            ->where('due_date_shamsi', '<', $todayShamsi) // تسک‌های معوق از روزهای گذشته [cite: 92]
            ->get();

        foreach ($expiredTasks as $task) {
            Log::info("⚠️ [SUPERVISOR OVERDUE] Expired task ID {$task->id} detected. Elevating priority for today.");

            DB::table('next_tasks')->where('id', $task->id)->update([
                'status' => 'expired_by_supervisor',
                'updated_at' => now()
            ]);

            // درج مجدد تسک معوق در روز جاری با اولویت بحرانی (High) [cite: 92]
            DB::table('next_tasks')->insert([
                'lead_id' => $task->lead_id,
                'task_title' => '🚨 مشاوره/پیگیری معوقه از روزهای گذشته (تزریق ناظر)', // [cite: 92]
                'status' => 'pending',
                'due_date_shamsi' => $todayShamsi,
                'priority' => 'high', // تسک‌های معوق ابتدا با اولویت بالا چیده می‌شوند [cite: 82]
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * ⚡ فاز ۵: موازنه بار کاری پیشرفته و همه‌جانبه (Advanced Rebalance & Overflow Engine)
     * پایش دقیق روزهای کاری، منابع مجاز لید، زمان پرت ساعتی و فلوچارت داینامیک
     */
    private function auditAgentLoadAndAdvancedRebalance($leadEngine, $todayShamsi)
    {
        $dayOfWeek = Carbon::now()->dayOfWeek; // صفر برای یکشنبه تا ۶ برای شنبه در کربن (جمعه = ۵)
        
        // واکشی کارشناسان فعال برای شیفت جاری
        $activeAgents = DB::table('agents')->where('is_active', 1)->get();

        foreach ($activeAgents as $agent) {
            // گارد نجات بومی ۱: چک کردن روز کاری فیزیکی کارمند (جلوگیری از باگ اساین در روزهای تعطیل/جمعه) [cite: 66, 189]
            $allowedDays = json_decode($agent->working_days, true) ?? [1,2,3,4,5]; // آرایه روزهای مجاز کاری [cite: 66]
            if (!in_array($dayOfWeek, $allowedDays)) {
                // اگر امروز روز کار کارشناس نیست ولی لید فعال یا تسک دارد، ناظر سریعاً همه را تخلیه می‌کند! 
                $this->evacuateAgentLeads($agent->id, $todayShamsi);
                continue;
            }

            // محاسبه بار کاری با اعمال فرمول دقیق ۱۰٪ زمان پرت به ازای هر ساعت فعالیت 
            $loadStats = $leadEngine->calculateAgentLoadAndOverflow($agent->id, $todayShamsi);

            if ($loadStats['is_overflowing']) { // وقوع وضعیت سرریز بار کاری روزانه [cite: 81, 150]
                Log::warning("⚡ [SUPERVISOR OVERFLOW] Agent ID {$agent->id} ({$agent->name}) exploded by {$loadStats['overflow_minutes']} minutes."); // [cite: 160]

                // واکشی لیدهای سرریز شده (مثلاً ۵ نفر باقی‌مانده که کارشناس نرسیده تماس بگیرد) [cite: 81]
                $overflowTasks = DB::table('next_tasks')
                    ->join('leads', 'next_tasks.lead_id', '=', 'leads.id')
                    ->where('leads.agent_id', $agent->id)
                    ->where('next_tasks.due_date_shamsi', $todayShamsi)
                    ->where('next_tasks.status', 'pending')
                    ->where('next_tasks.priority', '!=', 'high') // لیدهای با اولویت عادی جابجا می‌شوند
                    ->select('leads.id as lead_id', 'leads.source as lead_source') // واکشی منبع لید [cite: 65]
                    ->limit(5) // سقف انتقال سرریز در هر اسکن [cite: 81]
                    ->get();

                foreach ($overflowTasks as $task) {
                    // گارد نجات بومی ۲: پیدا کردن کارشناس جایگزینی که این «منبع لید خاص» جزو منابع مجاز وی باشد 
                    $backupAgent = DB::table('agents')
                        ->where('is_active', 1)
                        ->where('id', '!=', $agent->id)
                        ->where('department_id', $agent->department_id)
                        ->whereJsonContains('allowed_sources', $task->lead_source) // مچ کردن دقیق منبع لید با فیلتر دسترسی کارمند 
                        ->whereRaw('CAST(current_active_leads AS UNSIGNED) < CAST(max_capacity AS UNSIGNED)') // چک کردن سقف ظرفیت
                        ->orderBy('current_active_leads', 'asc')
                        ->first();

                    if ($backupAgent) {
                        // جابجایی مقتدرانه لید سرریز شده به اولین شیفت یا کارشناس مجاز بعدی [cite: 81, 160]
                        DB::table('leads')->where('id', $task->lead_id)->update([
                            'agent_id' => $backupAgent->id,
                            'updated_at' => now()
                        ]);

                        // موازنه شمارنده لیدهای فعال دو طرف
                        DB::table('agents')->where('id', $agent->id)->where('current_active_leads', '>', 0)->decrement('current_active_leads');
                        DB::table('agents')->where('id', $backupAgent->id)->increment('current_active_leads');

                        Log::info("🔀 [OVERFLOW RESOLVED] Lead ID {$task->lead_id} (Source: {$task->lead_source}) successfully shifted to Agent ID {$backupAgent->id}"); // [cite: 160]

                        // ارسال نوتیفیکیشن ایمیل/داشبورد به اکانت منیجر یا هد دپارتمان مطابق صفحه ۷ سند [cite: 160, 161]
                        event(new \App\Events\IncomingCallEvent($backupAgent->id, [
                            'lead_id' => $task->lead_id,
                            'customer_name' => 'لید سرریز شده جهت توازن مقتدرانه (ناظر)', // [cite: 81]
                            'status' => 'rebalanced'
                        ]));
                    } else {
                        // فالبک نهایی: اگر کارشناس خالی با منبع مجاز در این شیفت نبود، تسک به اولین شیفت روز بعد کارمند منتقل می‌شود [cite: 81]
                        $tomorrowShamsi = class_exists('\Morilog\Jalali\Jalalian')
                            ? \Morilog\Jalali\Jalalian::now()->addDays(1)->format('Y/m/d')
                            : now()->addDays(1)->format('Y/m/d');

                        DB::table('next_tasks')
                            ->where('lead_id', $task->lead_id)
                            ->where('due_date_shamsi', $todayShamsi)
                            ->update(['due_date_shamsi' => $tomorrowShamsi, 'updated_at' => now()]); // [cite: 81]
                            
                        Log::info("📅 [SHIFTED TO TOMORROW] No backup agent available for source '{$task->lead_source}'. Shifting Lead ID {$task->lead_id} to tomorrow."); // [cite: 81]
                    }
                }
            }
        }
    }

    /**
     * متد کمکی جهت تخلیه آنی لیدهای اختصاص یافته در روزهای غیرکاری (مثل جمعه‌ها)
     */
    private function evacuateAgentLeads($agentId, $todayShamsi)
    {
        $strandedLeads = DB::table('leads')->where('agent_id', $agentId)->where('status', 'assigned')->get();
        
        foreach ($strandedLeads as $lead) {
            DB::table('leads')->where('id', $lead->id)->update(['agent_id' => null, 'status' => 'new']);
            DB::table('agents')->where('id', $agentId)->where('current_active_leads', '>', 0)->decrement('current_active_leads');
            Log::warning("⚠️ [EVACUATION] Lead ID {$lead->id} forcefully evacuated from Agent ID {$agentId} due to non-working day audit."); // 
        }
    }
}