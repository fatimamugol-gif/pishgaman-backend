<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class VoIpWebhookController extends Controller
{
    /**
     * دریافت لاگ تماس از ایزابل پس از قطع شدن تلفن
     */
    public function handleCallLog(Request $request)
    {
        // دیتای ورودی از سیستم ایزابل / Issabel CDR
        $extension = $request->input('extension');     // داخلی کارشناس (مثلا 102)
        $customerPhone = $request->input('phone');     // شماره موبایل مشتری
        $duration = (int)$request->input('duration');  // مدت مکالمه به ثانیه (Billable Seconds)
        $disposition = $request->input('status');     // ANSWERED یا NO ANSWER
        
        Log::info("📞 [VOIP EVENT] Call ended from Ext {$extension} to {$customerPhone}. Duration: {$duration}s. Status: {$disposition}");

        // ۱. پیدا کردن کارشناس بر اساس داخلی ایزابل و بروزرسانی عملکرد روزانه او
        $agent = DB::table('agents')->where('voip_extension', $extension)->first();
        if ($agent) {
            if ($disposition === 'ANSWERED') {
                DB::table('agents')->where('id', $agent->id)->increment('daily_talk_time_seconds', $duration);
                DB::table('agents')->where('id', $agent->id)->increment('daily_successful_calls');
            } else {
                DB::table('agents')->where('id', $agent->id)->increment('daily_unanswered_calls');
            }
        }

        // ۲. پیدا کردن لید بر اساس شماره تلفن تروتمیز شده مشتری
        $cleanPhone = str_replace(['+', ' '], '', $customerPhone);
        $lead = Lead::where('phone', 'like', "%{$cleanPhone}%")->first();

        if ($lead) {
            if ($disposition === 'ANSWERED' && $duration > 30) {
                // تایید موفقیت مکالمه و صفر کردن شمارنده تماس‌های بی‌پاسخ در فلوچارت
                $lead->update([
                    'unanswered_calls_count' => 0,
                    'updated_at' => now()
                ]);
                Log::info("🎯 [FLOW AUTO-UPDATE] Lead ID {$lead->id} answered. Unanswered counter reset.");
            } 
            
            // اگر تماس ناموفق بود، شمارنده لوپ فلوچارت را ۱ دانه زیاد می‌کنیم
            if ($disposition !== 'ANSWERED') {
                $lead->increment('unanswered_calls_count');
                
                // طبق فلوچارت شما: اگر به ۱۶ تماس بی‌پاسخ رسید، خودکار معلق (Suspend) شود
                if ($lead->unanswered_calls_count >= 16) {
                    $lead->update([
                        'pipeline_stage' => 'suspend',
                        'suspend_reason' => 'تعلیق سیستمی: بیش از ۱۶ تماس تلفنی بی‌پاسخ از ایزابل دریافت شد.'
                    ]);
                    Log::warning("🚨 [LEAD SUSPENDED AUTOMATICALLY] Lead ID {$lead->id} moved to Suspend Stage due to 16 missed calls.");
                }
            }

            // ۳. ثبت رویداد تماس در چت‌لاگ/تاریخچه تعاملات برای حافظه کانتکست RAG
            $statusText = $disposition === 'ANSWERED' ? "موفق (مدت: {$duration} ثانیه)" : "بی‌پاسخ";
            DB::table('chat_logs')->insert([
                'lead_id' => $lead->id,
                'sender' => 'system',
                'message' => "📞 تماس تلفنی توسط کارشناس: تماس {$statusText}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'logged_and_synced'], 200);
    }
}