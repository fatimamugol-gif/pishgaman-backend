<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class MonitorSessionDeadlines extends Command
{
    protected $signature = 'next:monitor-sessions';
    protected $description = 'پایش مکرر ددلاین ۲ ساعته فرم‌های ارزیابی جلسات مشاور عالی';

    public function handle()
    {
        $expiredReports = DB::table('next_session_reports')
            ->where('status', 'pending')
            ->where('deadline_at', '<', now())
            ->get();

        $notifier = new NotificationService();

        foreach ($expiredReports as $report) {
            // ۱. آپدیت وضعیت به منقضی شده
            DB::table('next_session_reports')->where('id', $report->id)->update(['status' => 'expired']);

            // ۲. آماده‌سازی پکت هشدار تخلف برای ناظر ارشد
            $targets = [
                'phone'            => '09100816547', // شماره ناظر ارشد شرق کشور
                'email'            => 'm.r.shahbazi1991@gmail.com',
                'telegram_chat_id' => env('TELEGRAM_SUPERVISOR_CHAT_ID'),
            ];

            $payload = [
                'title' => '🚨 هشدار تخلف ددلاین ۲ ساعته پیشگامان',
                'body'  => "ناظر ارشد محترم، کارشناس مربوطه از تکمیل فرم ارزیابی جلسه حضوری امتناع کرده است.\n\n" .
                           "👤 کلاینت: {$report->client_name}\n" .
                           "👨‍⚕️ مشاور عالی: {$report->senior_consultant_name}\n" .
                           "⏱️ زمان پایان جلسه: {$report->session_end_at}\n" .
                           "❌ وضعیت: فرم به علت انقضای وقت قفل شد. جهت بررسی اقدام فرمایید.",
                'pattern_code' => 'ERROR_SMS_PATTERN'
            ];

            // 🔥 شلیک آلارم ۳ کاناله به ناظر ارشد
            $notifier->send($targets, $payload, ['sms', 'telegram', 'whatsapp']);
        }
    }
}