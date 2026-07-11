<?php

namespace App\Services;

use App\Models\ChatLog;
use Illuminate\Support\Facades\Log;

class ChatContextService
{
    /**
     * دریافت پیام‌های اخیر یک لید و تبدیل آن به فرمت کانتکست هوش مصنوعی
     */
    public function getConversationHistory(int $leadId, int $limit = 6): string
    {
        // دریافت آخرین پیام‌های مکالمه به ترتیب زمانی
        $logs = ChatLog::where('lead_id', $leadId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse(); // معکوس کردن برای اینکه چت‌ها به ترتیب درست (قدیم به جدید) چیده شوند

        $formattedHistory = "";

        foreach ($logs as $log) {
            $sender = match($log->sender_type) {
                'user' => 'کاربر (مشتری)',
                'bot' => 'دستیار هوشمند (سیستم)',
                'agent' => 'کارشناس فروش (انسان)',
                default => 'ناشناس'
            };

            $formattedHistory .= "[{$sender}]: {$log->message}\n";
        }

        return $formattedHistory;
    }
}