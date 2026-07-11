<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WebhookTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Lead;
use App\Services\PerfexIntegrationService;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        Log::info('Whatsapp Raw Payload:', $payload);

        $phone = $payload['from'] ?? $payload['sender'] ?? null; 
        $text = $payload['body'] ?? $payload['text'] ?? null;

        if (!$phone || !$text) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $cleanPhone = str_replace(['+', '@c.us'], '', $phone);

        // 🎯 ۱. موتور یکپارچه‌سازی هویت چندکاناله (Omnichannel Identity Resolution)
        // شانس اول: جستجو در دیتابیس لوکال خودمان بر اساس شماره موبایل
        $lead = Lead::where('phone', $cleanPhone)->first();

        if (!$lead) {
            // شانس دوم: متقاضی در لوکال نیست، استعلام زنده شماره تلفن از Perfex CRM
            $perfexService = app(PerfexIntegrationService::class);
            
            // استعلام لایو از پرفکس
            $perfexLead = $perfexService->findLeadBySocialId('phonenumber', $cleanPhone);

            if ($perfexLead) {
                // 🎉 متقاضی در پرفکس پرونده دارد! لید لوکال را متصل به همان آیدی واقعی پرفکس می‌سازیم
                $lead = Lead::create([
                    'perfex_lead_id' => $perfexLead['id'],
                    'phone' => $cleanPhone,
                    'source' => 'Whatsapp Chat',
                    'status' => 'new',
                    'behavioral_data' => [
                        'title' => $perfexLead['name'] ?? "مشتری واتساپ ({$cleanPhone})",
                        'channel' => 'whatsapp'
                    ]
                ]);
                Log::info("🎉 Omnichannel Match: Whatsapp user discovered in Perfex CRM with ID: {$perfexLead['id']}");
            } else {
                // 🛑 متقاضی کاملاً جدید است و در پرفکس هم وجود ندارد -> ساخت با آیدی موقت غیرتکراری
                $lead = Lead::create([
                    'perfex_lead_id' => (substr(time(), -6) . rand(100, 999)), 
                    'phone' => $cleanPhone,
                    'source' => 'Whatsapp Chat',
                    'status' => 'new',
                    'behavioral_data' => [
                        'title' => "مشتری واتساپ ({$cleanPhone})",
                        'channel' => 'whatsapp'
                    ]
                ]);
            }
        }

        // 🎯 ۲. اجرای موتور هوش تجاری و امتیازدهی لید (فاز ۳)
        $this->calculateAndStoreScore($lead);

        // ۳. ادغام آیدی داینامیک و اطلاعات کانتکست جهت ارسال به هاب مرکزی
        $request->merge([
            'lead_id' => $lead->id, 
            'source' => 'Whatsapp Chat',
            'channel' => 'whatsapp',
            'visit_frequency' => 1,
            'last_chat' => $text
        ]);

        $brainController = new WebhookTestController();
        $brainResponse = $brainController->handleTestWebhook($request);

        return response()->json(['status' => 'processed'], 200);
    }

    /**
     * 📊 لایه میانی محاسبه امتیاز تراکنش‌های مالی پرفکس برای لید واتساپ
     */
    // private function calculateAndStoreScore($lead)
    // {
    //     try {
    //         $perfexService = app(PerfexIntegrationService::class);
    //         $clientId = $lead->perfex_client_id ?? $lead->perfex_lead_id ?? 0;
            
    //         $totalPaid = $perfexService->getCustomerTotalPaid($clientId);
    //         $openTickets = $perfexService->getOpenTicketsCount($clientId);

    //         $score = 0;
    //         if ($totalPaid > 5000) $score += 35;
    //         elseif ($totalPaid > 1000) $score += 20;
    //         elseif ($totalPaid > 0) $score += 10;

    //         if ($openTickets > 0) $score += 15;

    //         $urgency = isset($lead->urgency_score) ? $lead->urgency_score : 5;
    //         $score += ($urgency * 4.5);

    //         $lead->update(['lead_score' => min(round($score), 100)]);
    //     } catch (\Exception $e) {
    //         Log::error("⚠️ [SCORING ERROR Whatsapp] " . $e->getMessage());
    //     }
    // }
    private function calculateAndStoreScore($lead)
    {
        // صدا زدن موتور یکپارچه و متمرکز جدید
        app(\App\Services\LeadScoringService::class)->calculateAndStoreScore($lead);
    }
}