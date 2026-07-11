<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public static function send($channels, $title, $description = '', $phoneNumber = null)
    {
        if (in_array('telegram', $channels)) {
            self::toTelegram($title, $description);
        }

        if (in_array('sms', $channels) && !empty($phoneNumber)) {
            self::toSms($phoneNumber, $title);
        }
    }

    /**
     * ✈️ شلیک اعلان به ربات تلگرام مانیتورینگ پیشگامان
     */
    public static function toTelegram($title, $description)
    {
        // مقدار توکن و چت‌آیدی را از .env می‌خواند
        $botToken = env('TELEGRAM_BOT_TOKEN', '7234567890:ABCdefGhIJKlmNoPQRsTUVwXyZ'); 
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID', '123456789'); 

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "🔔 *یادآور وظیفه دپارتمان فروش*\n\n📌 *موضوع:* {$title}\n📝 *توضیحات:* {$description}\n⏱ *زمان:* " . now()->toTimeString(),
                'parse_mode' => 'Markdown'
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram Notification Fail: " . $e->getMessage());
        }
    }

    /**
     * 💬 شلیک پیامک از طریق پترن وب‌سرویس خدماتی
     */
    public static function toSms($receptor, $taskTitle)
    {
        // نمونه کاوه‌نگار (Pattern Base):
        $apiKey = env('KAVENEGAR_API_KEY', 'YOUR_KEY');
        
        try {
            // نمونه ساختار متداول ارسال بر اساس پترن (کارت یادآور پیگیری)
            Http::post("https://api.kavenegar.com/v1/{$apiKey}/verify/lookup.json", [
                'receptor' => $receptor,
                'token' => str_replace(' ', '_', $taskTitle), // ارسال عنوان تسک بدون فاصله
                'template' => 'next_reminder_pattern' // نام قالب تایید شده شما در پنل پیامک
            ]);
        } catch (\Exception $e) {
            Log::error("SMS Notification Fail: " . $e->getMessage());
        }
    }
}