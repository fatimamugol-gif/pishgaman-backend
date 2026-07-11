<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Services\PerfexIntegrationService;

class InstagramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all() ?: $request->all();
        Log::info('Instagram Raw Payload:', $payload);

        $senderId = data_get($payload, 'entry.0.messaging.0.sender.id');
        $text = data_get($payload, 'entry.0.messaging.0.message.text');
        $username = data_get($payload, 'entry.0.messaging.0.sender.username');

        if (!$senderId || !$text) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // ۱. موتور هویت متقاطع
        $lead = Lead::where('instagram_sender_id', $senderId)->first();

        if (!$lead) {
            // استعلام از پرفکس
            $perfexService = app(PerfexIntegrationService::class);
            $perfexLead = $perfexService->findLeadBySocialId('instagram_sender_id', $senderId);
            
            if ($perfexLead) {
                $lead = Lead::create([
                    'perfex_lead_id' => $perfexLead['id'],
                    'instagram_sender_id' => $senderId,
                    'phone' => $perfexLead['phonenumber'] ?? null,
                    'source' => 'Instagram Direct',
                    'status' => 'new',
                    'behavioral_data' => [
                        'title' => $perfexLead['name'] ?? "مشتری اینستاگرام",
                        'channel' => 'instagram',
                        'username' => $username
                    ]
                ]);
            } else {
                $lead = Lead::create([
                    'perfex_lead_id' => (substr(time(), -6) . rand(100, 999)),
                    'instagram_sender_id' => $senderId,
                    'source' => 'Instagram Direct',
                    'status' => 'new',
                    'behavioral_data' => [
                        'title' => "مشتری دایرکت اینستاگرام " . ($username ? "(@{$username})" : "({$senderId})"),
                        'channel' => 'instagram',
                        'username' => $username
                    ]
                ]);
            }
        }

        // ۲. ذخیره مستقیم پیام در لاگ چت لوکال
        \DB::table('chat_logs')->insert([
            'lead_id' => $lead->id,
            'sender' => 'user',
            'message' => $text,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ۳. شلیک مستقیم به صف پردازش جاب (حذف کنترلر تست برای جلوگیری از لید تکراری)
        dispatch(new \App\Jobs\AnalyzeLeadJob($lead->id, $text, 'Instagram Direct', 'instagram', $senderId));

        return response()->json(['status' => 'processed'], 200);
    }

    private function calculateAndStoreScore($lead)
    {
        // صدا زدن موتور یکپارچه و متمرکز جدید
        app(\App\Services\LeadScoringService::class)->calculateAndStoreScore($lead);
    }
}