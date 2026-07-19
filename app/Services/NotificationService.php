<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected $channels = [];
    protected $config = [];

    public function __construct()
    {
        // 🎯 بارگذاری کانفیگ‌های همه‌جانبه پروداکشن و لوکال
        $this->config = [
            'ippanel_key'     => env('IPPANEL_API_KEY'),
            'ippanel_from'    => env('IPPANEL_FROM_NUMBER', '+983000505'),
            'telegram_token'  => env('TELEGRAM_BOT_TOKEN'),
            'telegram_token'    => env('TELEGRAM_BOT_TOKEN'),
            'whatsapp_token'    => env('WHATSAPP_API_TOKEN'), // 🟢 توکن متمرکز واتساپ (UltraMsg / یا سورس‌های موازی)
            'whatsapp_instance' => env('WHATSAPP_INSTANCE_ID'), // شناسه اینسنتس کلود
            'bale_token'      => env('BALE_BOT_TOKEN'), // 💬 توکن بازوی بله
            'onesignal_app_id'=> env('ONESIGNAL_APP_ID'), // 🔔 پوش نوتیفیکیشن
            'onesignal_key'   => env('ONESIGNAL_REST_API_KEY'),
            'fcm_server_key'    => env('FIREBASE_SERVER_KEY'),
        ];
    }

    /**
     * 🛰️ متد متمرکز شلیک نوتیفیکیشن چندگانه به صورت همزمان یا انتخابی
     * @param array $targets ['phone' => '...', 'user_id' => 1, 'telegram_id' => '...']
     * @param array $payload ['title' => '...', 'body' => '...', 'pattern_code' => '...', 'values' => []]
     * @param array $channels ['sms', 'in_app', 'telegram', 'system_log']
     */
    public function send(array $targets, array $payload, array $channels = ['in_app'])
    {
        $results = [];

        foreach ($channels as $channel) {
            try {
                $methodName = 'sendTo' . ucfirst($channel);
                if (method_exists($this, $methodName)) {
                    $results[$channel] = $this->$methodName($targets, $payload);
                } else {
                    Log::warning("⚠️ [Notification Engine]: Channel [{$channel}] not supported.");
                }
            } catch (\Exception $e) {
                Log::error("🚨 [Notification System Failure] Channel: {$channel} | Error: " . $e->getMessage());
                $results[$channel] = false;
            }
        }

        return $results;
    }

    /**
     * 🔥 ۹. شلیک پوش‌نوتیفیکیشن لایو فایربیس (Firebase Cloud Messaging API)
     */
    protected function sendToFirebase(array $targets, array $payload)
    {
        // نیاز به توکن ثبت‌شده مرورگر کاربر (FCM Device Token) دارد که از فرانت ذخیره می‌شود
        $fcmToken = $targets['fcm_token'] ?? null;
        if (!$fcmToken || !$this->config['fcm_server_key']) return false;

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'key=' . $this->config['fcm_server_key'],
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $payload['title'],
                    'body'  => $payload['body'],
                    'sound' => 'default',
                    'icon'  => '/logo.png', // آیکون پورتال در فرانت
                    'click_action' => $payload['click_action'] ?? '/dashboard/leads' // هدایت کاربر پس از کلیک روی پوش
                ],
                'data' => $payload['data'] ?? [] // پکت دیتای اضافه برای کامپوننت‌های فرانت
            ]);

        if ($response->successful()) {
            Log::info("🔥 [FCM Push Notification Sent]: To Token Successfully.");
            return true;
        }

        Log::error("🚨 [FCM API Error]: " . $response->body());
        return false;
    }

    /**
     * 🤖 ۳. شلیک به بات تلگرام رسمی سازمان (پیام‌های مارک‌داون لوکس)
     */
    protected function sendToTelegram(array $targets, array $payload)
    {
        $chatId = $targets['telegram_chat_id'] ?? env('TELEGRAM_SUPERVISOR_CHAT_ID');
        if (empty($chatId) || empty($this->config['telegram_token'])) return false;

        $text = "🔔 *{$payload['title']}*\n\n" . $payload['body'];

        $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->config['telegram_token']}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown'
        ]);

        return $response->successful();
    }

    /**
     * 🟢 ۸. شلیک به پیام‌رسان بین‌المللی واتساپ (WhatsApp Business API / UltraMsg Hub)
     */
    protected function sendToWhatsapp(array $targets, array $payload)
    {
        if (empty($targets['phone']) || empty($this->config['whatsapp_token'])) return false;

        $phone = preg_replace('/[^0-9]/', '', $targets['phone']);
        // تراز کردن پیش‌شماره بین‌المللی برای کشور ایران
        if (str_starts_with($phone, '09')) { $phone = '98' . substr($phone, 1); }

        $message = "🔔 *{$payload['title']}*\n\n" . $payload['body'];

        // استفاده از اندپوینت استاندارد مستندات وب‌سرویس واتساپ
        $response = Http::withoutVerifying()->post("https://api.ultramsg.com/{$this->config['whatsapp_instance']}/messages/chat", [
            'token' => $this->config['whatsapp_token'],
            'to'    => '+' . $phone,
            'body'  => $message,
            'priority' => 10
        ]);

        return $response->successful();
    }

    /**
     * 📱 ۱. شلیک از طریق وب‌سرویس پترن IPPanel (بدون نویز SSL در لوکال)
     */
    protected function sendToSms(array $targets, array $payload)
    {
        if (empty($targets['phone']) || empty($payload['pattern_code'])) return false;

        $phone = preg_replace('/[^0-9]/', '', $targets['phone']);
        if (str_starts_with($phone, '09')) {
            $phone = '98' . substr($phone, 1);
        }

        // استفاده از پچ طلایی Http::withoutVerifying جهت دور زدن خطای SSL cURL در ویندوز
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => "AccessKey {$this->config['ippanel_key']}",
            'Content-Type'  => 'application/json',
        ])->post('https://api.ippanel.com/v1/messages/pattern', [ // 💡 تغییر به /pattern
            'pattern_code' => $payload['pattern_code'],
            'originator'   => $this->config['ippanel_from'],
            'recipient'    => $phone,
            'values'       => $payload['values'] ?? []
        ]);

        if ($response->successful()) {
            Log::info("📨 [Notification SMS Sent]: To {$phone} via Pattern {$payload['pattern_code']}");
            return true;
        }

        Log::error("🚨 [Notification SMS API Error]: " . $response->body());
        return false;
    }

    /**
     * 💻 ۲. پلمب فیزیکی در جدول نوتیفیکیشن‌های درون‌برنامه‌ای فرانت‌آند (In-App)
     */
    protected function sendToInApp(array $targets, array $payload)
    {
        if (empty($targets['user_id'])) return false;

        return DB::table('next_reminders')->insert([
            'user_id'     => $targets['user_id'],
            'lead_id'     => $targets['lead_id'] ?? null,
            'title'       => $payload['title'],
            'description' => $payload['body'],
            'reminder_date_shamsi' => $payload['date_shamsi'] ?? now()->toIso8601String(),
            'reminder_time'        => $payload['time'] ?? '09:00',
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * 📝 ۴. کانال لاگ‌سیستم امنیتی (System Log Backup)
     */
    protected function sendToSystemLog(array $targets, array $payload)
    {
        Log::channel('single')->info("🔔 [SYSTEM NOTIFICATION LOG]: Title: {$payload['title']} | Body: {$payload['body']} | Target Data: " . json_stringify($targets));
        return true;
    }

    protected function sendToBale(array $targets, array $payload)
    {
        $chatId = $targets['bale_chat_id'] ?? null;
        if (!$chatId || !$this->config['bale_token']) return false;

        $text = "🔔 *{$payload['title']}*\n\n" . $payload['body'];

        // ارسال پکت به وب‌هوک رسمی بازوی بله
        $response = Http::withoutVerifying()->post("https://tapi.bale.ai/bot{$this->config['bale_token']}/sendMessage", [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);

        return $response->successful();
    }

    protected function sendToPush(array $targets, array $payload)
    {
        // نیاز به دستگاه ثبت شده (Player ID) در فرانت دارد
        $playerId = $targets['onesignal_player_id'] ?? null; 
        if (!$playerId || !$this->config['onesignal_app_id']) return false;

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Basic ' . $this->config['onesignal_key'],
                'Content-Type'  => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $this->config['onesignal_app_id'],
                'include_player_ids' => [$playerId],
                'headings' => ['en' => $payload['title'], 'fa' => $payload['title']],
                'contents' => ['en' => $payload['body'], 'fa' => $payload['body']],
            ]);

        return $response->successful();
    }

    protected function sendToEmail(array $targets, array $payload)
    {
        if (empty($targets['email'])) return false;

        $emailTarget = $targets['email'];
        $subject = $payload['title'];
        $content = $payload['body'];

        // استفاده از سیستم ارسال ایمیل آنلاین لاراول با قالب متنی ساده یا HTML سریع
        Mail::raw($content, function ($message) use ($emailTarget, $subject) {
            $message->to($emailTarget)
                    ->subject($subject);
        });

        Log::info("📧 [Notification Email Sent]: To {$emailTarget}");
        return true;
    }
}                         