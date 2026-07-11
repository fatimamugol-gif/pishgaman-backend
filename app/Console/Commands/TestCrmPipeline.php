<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Models\Agent;
use App\Jobs\AnalyzeLeadJob;
use Carbon\Carbon;

class TestCrmPipeline extends Command
{
    protected $signature = 'crm:test-pipeline';
    protected $description = 'شبیه‌سازی و تست ۳۶۰ درجه ورود لید، پردازش جاب، و پایش ناظر هوشمند';

    public function handle()
    {
        $this->warn("🚀 [TESTER] Starting E2E Pipeline Testing for Pishgaman CRM...");

        // ۱. آماده‌سازی کارشناسان فرضی جهت تست دسترسی منابع و ظرفیت
        $this->info("👥 Step 1: Setting up mock agents and their working rules...");
        $this->setupMockAgents();

        // ۲. شبیه‌سازی ورود لید از دایرکت اینستاگرام
        $this->info("📥 Step 2: Simulating an incoming lead from Instagram Webhook...");
        $mockLeadId = $this->simulateIncomingLead();

        // ۳. اجرای زنده و مستقیم AnalyzeLeadJob جهت شبیه‌سازی پردازش هوش مصنوعی و RAG
        $this->info("🤖 Step 3: Triggering AI Analysis and RAG indexing...");
        
        // ایجاد نمونه چت تستی متقاضی عجول (Fast-Track) جهت تست ماتریکس پرسونایی
        $mockMessage = "سلام، من مهران هستم ۲۴ ساله. کارشناسی ارشد مهندسی کامپیوتر دارم و فوری می‌خوام برای ویزای کاری آلمان اقدام کنم. تمکن مالی من حدود ۶۰۰ میلیون تومنه. لطفاً سریعاً راهنمایی کنید.";
        
        // اجرای مستقیم متد handle برای دیدن لاگ‌ها در ترمینال بدون نیاز به باز کردن ورکر صف
        $job = new AnalyzeLeadJob($mockLeadId, $mockMessage, 'Instagram Direct', 'instagram', 'insta_user_99');
        $job->handle();

        $this->info("🎉 AI and Matchmaking process completed for the Lead.");

        // ۴. شبیه‌سازی باگ مستندات: تسک معوقه و کارشناس در حال سرریز (Overflow)
        $this->info("⚡ Step 4: Structuring Overdue Tasks and Agent Overflow to test the Supervisor...");
        $this->createOverflowAndOverdueScenario($mockLeadId);

        // ۵. بیدار کردن و فراخوانی زنده ناظر هوشمند سیستم
        $this->info("🕵️‍♂️ Step 5: Awakening the Smart Supervisor Core...");
        $this->call('crm:smart-supervisor');

        $this->warn("🏁 [TESTER END] End-to-End simulation completed. Check laravel.log for deep tracing.");
        return Command::SUCCESS;
    }

    private function setupMockAgents()
{
    // 🛡️ غیرفعال کردن موقت گارد کلید خارجی برای دور زدن خطای Truncate
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    DB::table('agents')->truncate();
    DB::table('leads')->truncate(); // تخلیه لیدهای قدیمی برای یک تست تمیز
    DB::table('next_tasks')->truncate(); // تخلیه تسک‌های قدیمی

    // 🛡️ فعال‌سازی مجدد گارد کلید خارجی
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    // کارشناس اول: شلوغ و در حال سرریز (فقط دسترسی به منبع اینستاگرام دارد)
    DB::table('agents')->insert([
        'id' => 1,
        'perfex_staff_id' => 101, // 🎯 تزریق آیدی پرفکس برای رفع خطای دیتابیس
        'name' => 'کارشناس الف (شلوغ)',
        'email' => 'agent_a@pishgaman.com',
        'is_active' => 1,
        'role' => 'call_center',
        'department_id' => 1,
        'max_capacity' => 10,
        'current_active_leads' => 9, 
        'working_days' => json_encode([1, 2, 3, 4, 6]), 
        'allowed_sources' => json_encode(['Instagram Direct']),
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // کارشناس دوم: پشتیبان ارشد و خلوت (دسترسی به تمام منابع)
    DB::table('agents')->insert([
        'id' => 2,
        'perfex_staff_id' => 102, // 🎯 تزریق آیدی پرفکس برای رفع خطای دیتابیس
        'name' => 'کارشناس ب (پشتیبان)',
        'email' => 'agent_b@pishgaman.com',
        'is_active' => 1,
        'role' => 'senior_agent',
        'department_id' => 1,
        'max_capacity' => 50,
        'current_active_leads' => 2, 
        'working_days' => json_encode([1, 2, 3, 4, 6]),
        'allowed_sources' => json_encode(['Instagram Direct', 'Telegram Bot']),
        'created_at' => now(),
        'updated_at' => now()
    ]);
}

    private function simulateIncomingLead()
{
    // 🎯 تولید یک شناسه عددی تصادفی برای مچ شدن با نوع داده Integer در دیتابیس شما
    return DB::table('leads')->insertGetId([
        'perfex_lead_id' => rand(100000, 999999), 
        'instagram_sender_id' => 'insta_user_99',
        'source' => 'Instagram Direct',
        'status' => 'new',
        'pipeline_stage' => 'call_center',
        'created_at' => now(),
        'updated_at' => now()
    ]);
}

    private function createOverflowAndOverdueScenario($leadId)
{
    $todayShamsi = class_exists('\Morilog\Jalali\Jalalian')
        ? \Morilog\Jalali\Jalalian::now()->format('Y/m/d')
        : now()->format('Y/m/d');

    $yesterdayShamsi = class_exists('\Morilog\Jalali\Jalalian')
        ? \Morilog\Jalali\Jalalian::now()->subDays(1)->format('Y/m/d')
        : now()->format('Y/m/d');

    // 🎯 اختصاص دادن لید به کارشناس شماره ۱ (شلوغ) برای تحریک کردن ناظر هوشمند
    DB::table('leads')->where('id', $leadId)->update([
        'agent_id' => 1, // فیکس روی ۱
        'status' => 'assigned',
        'updated_at' => Carbon::now()->subHours(3) // ۳ ساعت پیش رها شده است
    ]);

    DB::table('next_tasks')->insert([
        'lead_id' => $leadId,
        'task_title' => '📞 تماس مشاوره اولیه از روز قبل (جامانده)',
        'status' => 'pending',
        'due_date_shamsi' => $yesterdayShamsi, // تاریخ دیروز برای تست اکسپایر
        'priority' => 'medium',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay()
    ]);

    // انباشت تسک‌های سنگین روی کارشناس ۱ برای امروز جهت تست وضعیت سرریز (Overflow)
    for ($i = 1; $i <= 3; $i++) {
        DB::table('next_tasks')->insert([
            'lead_id' => $leadId,
            'task_title' => '📞 جلسه مشاوره عالی تخصصی فرضی شماره ' . $i,
            'status' => 'pending',
            'due_date_shamsi' => $todayShamsi,
            'priority' => 'medium',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
}