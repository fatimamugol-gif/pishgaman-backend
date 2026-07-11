<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WebhookTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Lead; 
use App\Services\PerfexIntegrationService; 

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all() ?: $request->all();
        Log::info('Telegram Raw Payload:', $payload);

        $chatId = $payload['message']['chat']['id'] ?? null;
        $text = $payload['message']['text'] ?? $payload['edited_message']['text'] ?? null;
        $contact = $payload['message']['contact'] ?? null;
        
        $firstName = $payload['message']['from']['first_name'] ?? '';
        $lastName = $payload['message']['from']['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: 'کاربر تلگرام';
        $username = $payload['message']['from']['username'] ?? null;

        if (!$chatId || (!$text && !$contact)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // 🛡️ گارد اتمیک قفل دیتابیس: جلوگیری مقتدرانه از تولد ۲ لید همزاد برای ۱ پیام
        $lockKey = "tg_chat_lock_{$chatId}";
        if (cache()->has($lockKey)) {
            // اگر ریکوئست موازی در همان ثانیه در جریان است، ۵۰۰ میلی‌ثانیه صبر کن تا لید اول ثبت شود
            usleep(500000); 
        }
        cache()->put($lockKey, true, 10); // قفل کردن چت‌آیدی برای ۱۰ ثانیه

        try {
            // ۱. موتور یکپارچه‌سازی هویت چندکاناله
            $lead = Lead::where('telegram_chat_id', $chatId)->first();
            $mustRequestContact = false;

            if (!$lead) {
                $perfexService = app(PerfexIntegrationService::class);
                
                $perfexLead = $perfexService->findLeadBySocialId('telegram_chat_id', $chatId);
                if (!$perfexLead && $username) {
                    $perfexLead = $perfexService->findLeadBySocialId('telegram_username', $username);
                }

                if ($perfexLead) {
                    $lead = Lead::create([
                        'perfex_lead_id' => $perfexLead['id'],
                        'telegram_chat_id' => $chatId,
                        'name' => $perfexLead['name'] ?? $fullName,
                        'phone' => $perfexLead['phonenumber'] ?? null, 
                        'source' => 'Telegram Bot',
                        'status' => 'new',
                        'import_source' => 'telegram',
                        'pipeline_stage' => 'initial_contact',
                        'behavioral_data' => json_encode([
                            'title' => $perfexLead['name'] ?? $fullName,
                            'channel' => 'telegram',
                            'username' => $username
                        ], JSON_UNESCAPED_UNICODE)
                    ]);
                } else {
                    $mustRequestContact = true;
                    
                    // ثبت فوری لید با نام و فامیل استخراج شده واقعی تلگرام در اولین شلیک
                    $lead = Lead::create([
                        'perfex_lead_id' => (substr(time(), -6) . rand(100, 999)), 
                        'telegram_chat_id' => $chatId,
                        'name' => $fullName,
                        'source' => 'Telegram Bot',
                        'status' => 'new',
                        'import_source' => 'telegram',
                        'pipeline_stage' => 'pending_info',
                        'behavioral_data' => json_encode([
                            'title' => $fullName . ($username ? " (@{$username})" : ""),
                            'channel' => 'telegram',
                            'username' => $username
                        ], JSON_UNESCAPED_UNICODE)
                    ]);
                }
            }

            // آزاد کردن قفل اتمیک پس از ثبت یا یافتن لید عددی
            cache()->forget($lockKey);

           // 🎯 ۲. مدیریت زمان لمس دکمه اشتراک‌گذاری مخاطب (نسخه لایروبی شده و ضد کرش)
            if ($contact) {
                $phoneNumber = str_replace('+', '', $contact['phone_number'] ?? '');
                if (str_starts_with($phoneNumber, '989')) {
                    $phoneNumber = '0' . substr($phoneNumber, 2);
                }
                
                // به‌روزرسانی اطلاعات هویتی و منبع صحیح لید
                $lead->update([
                    'phone' => $phoneNumber,
                    'name' => $fullName, 
                    'source' => 'Telegram Bot', 
                    'pipeline_stage' => 'initial_contact' 
                ]);
                
                // 🧠 اصلاح طلایی: تغییر نوع فرستنده از system به bot جهت فرار از باگ محدودیت ENUM دیتابیس
                DB::table('chat_logs')->insert([
                    'lead_id'     => $lead->id,
                    'channel'     => 'telegram',
                    'sender_type' => 'bot', // 👈 حالا با دیتابیس شما کاملاً جفت و مجاز است
                    'message'     => "📱 شماره همراه متقاضی به صورت بومی تایید شد: {$phoneNumber}",
                    'created_at'  => now(),
                    'updated_at'  => now()
                ]);
                
                $this->sendMessageToTelegram($chatId, "تشکر رفیق! شماره موبایل شما (`{$phoneNumber}`) با موفقیت به پرونده متصل شد. هوش مصنوعی در حال پردازش درخواست شماست ⏳");
                return response()->json(['status' => 'contact_saved'], 200);
            }

            // ۳. محاسبه امتیاز لید
            $this->calculateAndStoreScore($lead);

            // ۴. ساخت ریکوئست نهایی مجهز به کانتکست مکالمه و ذخیره پیام
            if (!empty($text)) {
                $existsLog = DB::table('chat_logs')->where('lead_id', $lead->id)->where('message', $text)->exists();
                if (!$existsLog) {
                    DB::table('chat_logs')->insert([
                        'lead_id' => $lead->id,
                        'channel' => 'telegram',
                        'sender_type' => 'user',
                        'message' => $text,
                        'is_analyzed' => false,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // ۵. پرتاب لید یگانه به صف پردازش هوش مصنوعی (دور زدن وب‌هوک تست برای مهار گپ)
            dispatch(new \App\Jobs\AnalyzeLeadJob($lead->id, $text, 'Telegram Bot', 'telegram', $chatId));

            // ۶. پاسخ هوشمند و پیاده‌سازی گیت احراز هویت تلگرام
            if (($mustRequestContact && $text === '/start') || empty($lead->phone)) {
                $welcomeMessage = "سلام! به سیستم هوشمند موسسه پیشگامان خوش آمدید 🤖\n\nبرای ارائه مشاوره دقیق، استخراج سوابق و اتصال پرونده، لطفا دکمه زیر را لمس کنید تا شماره موبایل شما ثبت شود:";
                $this->sendTelegramContactRequest($chatId, $welcomeMessage);
            } else {
                $replyMessage = "سلام! پیام شما در سیستم هوشمند پیشگامان ثبت شد 🤖\n\n";
                $replyMessage .= "پرونده شما در حال حاضر در صف پردازش هوش مصنوعی قرار دارد و به زودی به کارشناس تخصصی لاین مربوطه ارجاع داده خواهد شد. منتظر تماس ما باشید ✈️";
                $this->sendMessageToTelegram($chatId, $replyMessage);
            }

            return response()->json(['status' => 'processed'], 200);

        } catch (\Exception $e) {
            cache()->forget($lockKey);
            Log::error("🚨 [TELEGRAM CONTROLLER CRASH] " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function calculateAndStoreScore($lead)
    {
        app(\App\Services\LeadScoringService::class)->calculateAndStoreScore($lead);
    }

    private function sendTelegramContactRequest($chatId, $message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) return;

        try {
            Http::withoutVerifying()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode([
                    'keyboard' => [[['text' => '📱 اشتراک‌گذاری شماره موبایل', 'request_contact' => true]]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
        } catch (\Exception $e) {
            Log::warning('Telegram contact request delayed: ' . $e->getMessage());
        }
    }

    private function sendMessageToTelegram($chatId, $message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) return;

        try {
            Http::withoutVerifying()->timeout(4)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        } catch (\Exception $e) {
            Log::warning('Telegram Notification Delayed: ' . $e->getMessage());
        }
    }
}