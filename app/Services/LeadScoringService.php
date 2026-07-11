<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class LeadScoringService
{
    /**
     * موتور رتبه‌بندی ماتریسی و داینامیک ارزش لیدها
     */
    public function calculateAndStoreScore(Lead $lead): int
    {
        try {
            $perfexService = app(PerfexIntegrationService::class);
            $clientId = $lead->perfex_client_id ?? $lead->perfex_lead_id ?? 0;

            // 💵 ۱. فاکتور اول: سوابق مالی و تراکنش‌های پرداخت شده مشتری در پرفکس (وزن: ۳۵٪)
            $totalPaid = $perfexService->getCustomerTotalPaid($clientId);
            $financialScore = 0;
            if ($totalPaid > 10000) $financialScore = 35;      // متقاضی طلایی (VIP)
            elseif ($totalPaid > 5000) $financialScore = 25;   // متقاضی نقره‌ای
            elseif ($totalPaid > 1000) $financialScore = 15;
            elseif ($totalPaid > 0) $financialScore = 5;

            // ✉️ ۲. فاکتور دوم: تیکت‌های باز و دغدغه‌های فعال در پشتیبانی (وزن: ۲۰٪)
            $openTickets = $perfexService->getOpenTicketsCount($clientId);
            $ticketScore = 0;
            if ($openTickets >= 4) $ticketScore = 20;       // بحرانی و نیازمند تماس فوری
            elseif ($openTickets > 0) $ticketScore = 10;

            // 🧠 ۳. فاکتور سوم: درجه فوریت استخراج شده توسط هوش مصنوعی RAG (وزن: ۳۵٪)
            // خواندن زنده ستون جیسون یا مقدار پیش‌فرض
            $aiUrgency = data_get($lead->behavioral_data, 'urgency_score', 5);
            $urgencyScore = (int)$aiUrgency * 3.5; // نگاشت مقیاس ۱-۱۰ به وزن حداکثر ۳۵

            // 📡 ۴. فاکتور چهارم: وزن کانال ارتباطی بر اساس پایداری (وزن: ۱۰٪)
            $sourceScore = 0;
            if ($lead->source === 'Whatsapp Chat') $sourceScore = 10;
            elseif ($lead->source === 'Telegram Bot') $sourceScore = 7;
            else $sourceScore = 5;

            // 🎯 محاسبه رتبه نهایی متراکم (حداکثر ۱۰۰)
            $finalScore = $financialScore + $ticketScore + $urgencyScore + $sourceScore;
            $finalScore = min(round($finalScore), 100);

            // ۵. به‌روزرسانی فورا ستون امتیاز لید در دیتابیس مایکروسرویس
            $lead->update(['lead_score' => $finalScore]);
            
            Log::info("🎯 [DYNAMIC SCORING ENGINE] Lead ID {$lead->id} ranked perfectly: {$finalScore}/100");

            // 📡 ۶. ارسال رتبه نهایی به فیلد اختصاصی امتیاز در پرفکس
            $perfexApiKey = env('PERFEX_API_KEY', 'Bearer YOUR_PERFEX_TOKEN_HERE');
            if ($lead->perfex_lead_id && strlen($lead->perfex_lead_id) < 9) {
                
                $scoreUrl = "https://cip.2visa.ir/api/v1/leads/" . trim($lead->perfex_lead_id);
                
                if (filter_var($scoreUrl, FILTER_VALIDATE_URL)) {
                    Http::withoutVerifying()
                        ->withHeaders([
                            'Authorization' => $perfexApiKey,
                            'Content-Type' => 'application/json'
                        ])
                        ->put($scoreUrl, [
                            'custom_fields' => [
                                'lead' => [
                                    '4' => (string)$finalScore // شماره فیلد سفارشی رتبه در پرفکس
                                ]
                            ]
                        ]);
                    Log::info("📡 [SCORING SYNCED TO PERFEX] Score {$finalScore} synced successfully.");
                }
            }

            return $finalScore;

        } catch (\Exception $e) {
            Log::error("⚠️ [SCORING ENGINE SYSTEM CRASH] " . $e->getMessage());
            return 50; // فالبک ایمن در صورت قطعی API پرفکس
        }
    }
}