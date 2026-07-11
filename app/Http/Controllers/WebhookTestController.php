<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Agent;
use App\Models\Lead;

class WebhookTestController extends Controller
{
    /**
     * 🧠 ساخت یکپارچه دیتای پِندینگ برای تمام ورودی‌های سیستم
     */
    private function generatePendingBehavioralData($leadId, $source, $channel, $message, $userId = 'unknown')
    {
        return [
            'lead_id' => (string)$leadId,
            'source' => $source,
            'channel' => $channel,
            'visit_frequency' => 1,
            'last_chat' => $message,
            'intent' => 'pending', // وضعیت انتظار برای فایلمنت
            'destination' => 'در حال تحلیل هوشمند...',
            'urgency' => 'medium',
            'interest_level' => 'low',
            'conversation_summary' => "لید جدید از طریق {$source} وارد سیستم شد و در صف پردازش هوش مصنوعی است.",
            'keywords' => ['در_حال_پردازش'],
            'recommended_action' => 'سیستم در پس‌زمینه در حال استخراج اطلاعات از GapGPT است.',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => (string)$userId],
                            'message' => ['text' => $message]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function handleTestWebhook(Request $request)
    {
        // ۱. دریافت دیتا از کنترلرهای واسط (تلگرام، اینستا، واتساپ یا پستمین)
        $allData = $request->all();

        $perfexLeadId = $allData['lead_id'] ?? $request->input('lead_id') ?? "1";
        $source = $allData['source'] ?? $request->input('source', 'Direct');
        $channel = $allData['channel'] ?? $request->input('channel', 'site');
        $chatText = $allData['last_chat'] ?? $request->input('last_chat', 'بدون پیام اولیه');
        
        // استخراج آیدی کاربر از ریکوئست اصلی (اگر موجود بود)
        $userId = $request->input('message.from.id') ?? $request->input('entry.0.messaging.0.sender.id') ?? 'system_user';

        // ۲. ساخت یا آپدیت لید در لاراول با دیتای اولیه (پِندینگ)
        $lead = Lead::updateOrCreate(
            ['perfex_lead_id' => $perfexLeadId],
            [
                'source' => $source,
                'ai_score' => 0, 
                'status' => 'unassigned', 
                'behavioral_data' => $this->generatePendingBehavioralData($perfexLeadId, $source, $channel, $chatText, $userId)
            ]
        );

        // ==========================================
        // 🧠 ذخیره پیام کاربر در حافظه تاریخی سیستم (chat_logs)
        // ==========================================
        if (!empty($chatText)) {
            \App\Models\ChatLog::create([
                'lead_id' => $lead->id,
                'channel' => $channel,
                'sender_type' => 'user', // فرستنده: مشتری
                'message' => $chatText,
                'is_analyzed' => false, // این پیام بعداً باید توسط NLP بررسی/خلاصه شود
            ]);
        }
        // ==========================================

        // ۳. پرتاب لید به صف پردازش هوش مصنوعی
        \App\Jobs\AnalyzeLeadJob::dispatch($lead->id, $chatText, $source, $channel, $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'لید با موفقیت دریافت، با ساختار استاندارد ثبت و جهت تحلیل به صف هوش مصنوعی ارجاع شد.',
            'ai_analysis' => [
                'intent' => 'pending',
                'destination' => 'در حال تحلیل هوشمند...',
                'recommended_action' => 'سیستم در پس‌زمینه در حال استخراج اطلاعات از GapGPT است.'
            ],
            'assigned_agent' => 'در انتظار تخصیص...',
            'perfex_synced' => false // سینک پرفکس را به بعد از تعیین قطعی کارشناس در جاب موکول کنید
        ], 200);
    }
   
    // این متد فعلا اینجا می‌ماند تا اگر در بخش‌های دیگر (مثل جاب) خواستی از آن استفاده کنی
    private function updateLeadAgentInPerfex($leadId, $staffId)
    {
        $response = Http::withHeaders([
            'authtoken' => env('PERFEX_API_TOKEN')
        ])->withoutVerifying()->post(env('PERFEX_BASE_URL') . '/leads/' . $leadId, [
            'assigned' => $staffId
        ]);
        return $response->successful();
    }
}