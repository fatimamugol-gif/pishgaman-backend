<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use DB;

class AnalyzeLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $leadId;
    protected $text;
    protected $source;
    protected $channel;
    protected $userId;

    public function __construct($leadId, $text, $source = 'Telegram Bot', $channel = 'telegram', $userId = '998277')
    {
        $this->leadId = $leadId;
        $this->text = $text;
        $this->source = $source;
        $this->channel = $channel;
        $this->userId = $userId;
    }

    public function handle()
    {
        try {
            Log::info("🕵️‍♂️ [RAG START] Starting analysis for Lead ID: {$this->leadId} with message: '{$this->text}'");
            
            // 🧠 لایروبی و حل ارور: واکشی اولیه لید در بالاترین سطح متد جهت مقداردهی به متغیر
            $currentLeadData = DB::table('leads')->where('id', $this->leadId)->first();
            if (!$currentLeadData) {
                Log::warning("⚠️ [RAG ABORT] Lead ID {$this->leadId} not found in database. Terminating job.");
                return;
            }

            // ۱. استخراج تاریخچه مکالمات برای معماری RAG
            $contextService = new \App\Services\ChatContextService();
            $history = $contextService->getConversationHistory($this->leadId, 6);
            Log::info("💬 [RAG CONTEXT] Chat History retrieved length: " . strlen($history));

            // ۲. استخراج قوانین مرتبط از کیودرنت (Semantic Search)
            $vectorService = new \App\Services\VectorService();
            $relatedLaws = $vectorService->searchRelatedKnowledge($this->text, 2);

            if (empty($relatedLaws)) {
                Log::warning("⚠️ [RAG KNOWLEDGE] Qdrant search returned EMPTY!");
                $relatedLaws = "اطلاعاتی یافت نشد.";
            } else {
                Log::info("📚 [RAG KNOWLEDGE] Related Laws successfully retrieved:\n" . $relatedLaws);
            }
            
            $apiKey = env('OPENAI_API_KEY');
            $baseUrl = "https://api.gapgpt.app/v1/chat/completions"; 
            $currentDate = now()->format('Y-m-d');
            
            $prompt = "You are a precise data extractor and senior immigration assistant. 
            IMPORTANT CONTEXT: The current year is 2026. Today's date is {$currentDate}. 
            Analyze the LATEST user message inside the context of conversation history AND the provided law documents.
            Output strictly a valid JSON object. Do NOT include markdown formatting like ```json.

            Required JSON Keys:
            1. intent (Must be one of: 'study_visa', 'work_visa', 'investment_visa', 'tourist_visa', 'general_inquiry')
            2. destination (The country name in Persian, e.g., 'آلمان')
            3. urgency_score (An integer from 1 to 10 based on urgency)
            4. keywords (An array of maximum 3 important words in Persian)
            5. recommended_action (A Persian sentence guide for the sales agent based on the provided laws)";

            $data = [
                'model' => 'gpt-4o-mini', 
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt
                    ],
                    [
                        'role' => 'user',
                        'content' => "Base Knowledge:\n{$relatedLaws}\n\nHistory:\n{$history}\n\nLatest Message:\n'{$this->text}'"
                    ]
                ],
                'temperature' => 0.2
            ];

            // شلیک ریکوئست چت به هوش مصنوعی با cURL
            $ch = curl_init($baseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $apiKey
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            Log::info('!! RAW DEBBUGING GAPGPT RESPONSE !!', ['raw_body' => $response]);

            $result = json_decode($response, true);
            $aiTextOutput = data_get($result, 'choices.0.message.content');

            if (!empty($aiTextOutput)) {
                $cleanJsonText = trim($aiTextOutput);
                $aiResult = json_decode($cleanJsonText, true);

                if (is_array($aiResult)) {
                    $urgencyScore = (int)($aiResult['urgency_score'] ?? 5);
                    
                    // تولید خلاصه‌سازی مکالمه با پلتفرم NLP
                    $conversationSummary = $this->generateConversationSummary($this->leadId, $this->text, $apiKey);

                    $behavioralData = [
                        'lead_id' => (string)$this->leadId,
                        'source' => $this->source,
                        'channel' => $this->channel,
                        'visit_frequency' => 1,
                        'last_chat' => $this->text,
                        'intent' => $aiResult['intent'] ?? 'general_inquiry',
                        'destination' => $aiResult['destination'] ?? 'نامشخص',
                        'urgency' => $urgencyScore > 6 ? 'high' : 'medium',
                        'interest_level' => $urgencyScore > 7 ? 'high' : 'medium',
                        'conversation_summary' => $conversationSummary,
                        'keywords' => $aiResult['keywords'] ?? [],
                        'recommended_action' => $aiResult['recommended_action'] ?? 'تماس جهت بررسی اولیه رزومه',
                    ];

                    // 📱 رِجکس هوشمند برای استخراج شماره موبایل‌های شورت‌کات از چت (09...)
                    if (empty($currentLeadData->phone)) {
                        if (preg_match('/(09\d{9})|(\+?989\d{9})|(9\d{9})/', $this->text, $matches)) {
                            $extractedPhone = str_replace(['+', ' '], '', $matches);
                            if (str_starts_with($extractedPhone, '9') && strlen($extractedPhone) === 10) {
                                $extractedPhone = '0' . $extractedPhone;
                            }
                            
                            DB::table('leads')->where('id', $this->leadId)->update([
                                'phone' => $extractedPhone
                            ]);
                            Log::info("📱 [PHONE AUTO-CAPTURED] Extracted: {$extractedPhone}");
                        }
                    }

                    $finalJsonPayload = json_encode($behavioralData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    // 🧠 محاسبه امتیاز اولیه پیش‌بینانه (Pre-Scoring) توسط سرویس متمرکز شما
                    $preScore = $urgencyScore * 10;
                    if (app()->bound(\App\Services\LeadRoutingService::class)) {
                        try {
                            $preScore = app(\App\Services\LeadRoutingService::class)->calculatePreScore($aiResult, $this->source);
                        } catch (\Exception $e) { Log::warning("RoutingService preScore fallback applied."); }
                    }

                    // 💾 به‌روزرسانی نهایی و اتمیک با نام ستون‌های فیزیکی دقیق دیتابیس crmpishgaman_ai-brain شما
                    DB::table('leads')->where('id', $this->leadId)->update([
                        'lead_score' => $preScore,
                        'target_country' => $aiResult['destination'] ?? $currentLeadData->target_country,
                        'pipeline_stage' => 'initial_contact', // هدایت اصولی لید تلگرام به کارتابل مشاوران
                        'age' => data_get($aiResult, 'age') ?: $currentLeadData->age,
                        'field_of_study' => data_get($aiResult, 'field_of_study') ?: $currentLeadData->field_of_study,
                        
                        // ذخیره کانتکست در جدول انبار کانتکست هوش مصنوعی
                        'behavioral_data' => $finalJsonPayload,
                        'updated_at' => now()
                    ]);

                    // ذخیره خروجی مستقیم در جدول مکمل کانتکست‌ها جهت لود زنده در شناسنامه ۳۶۰ درجه فرانت
                    DB::table('customer_insights')->updateOrInsert(
                        ['customer_id' => $this->leadId],
                        [
                            'last_intent' => $aiResult['intent'] ?? 'general_inquiry',
                            'likely_destination' => $aiResult['destination'] ?? 'نامشخص',
                            'urgency_score' => $urgencyScore,
                            'recommended_action' => $aiResult['recommended_action'] ?? 'بررسی کانتکست چت تلگرام',
                            'top_keywords' => json_encode($aiResult['keywords'] ?? []),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                    
                    Log::info("🚀 [LEADS PRE-SCORE UPDATED] Lead ID {$this->leadId} processed successfully.");

                    // 📡 🔗 شلیک مستقیم نتایج آنالیز و رتبه به فیلدهای سفارشی Perfex CRM (نسخه اصلاح شده بدون تداخل آدرس)
                    try {
                        $perfexApiKey = env('PERFEX_API_TOKEN'); 
                        if ($currentLeadData && !empty($currentLeadData->perfex_lead_id) && $currentLeadData->perfex_lead_id < 2147483647) {
                            
                            // 🎯 استفاده مستقیم از آدرس معتبر تایید شده xip موسسه برای مهار کرش پروتکل
                            $perfexUrl = "https://xip.2visa.ir/api/v1/leads/" . trim($currentLeadData->perfex_lead_id);
                            
                            Http::withoutVerifying()
                                ->withHeaders([
                                    'authtoken'    => $perfexApiKey, 
                                    'Content-Type' => 'application/json',
                                    'Accept'       => 'application/json'
                                ])
                                ->put($perfexUrl, [
                                    'custom_fields' => [
                                        'lead' => [
                                            '1' => $aiResult['intent'] ?? 'general_inquiry',             
                                            '2' => (string)$urgencyScore,                                 
                                            '3' => $aiResult['recommended_action'] ?? 'بررسی اولیه رزومه' 
                                        ]
                                    ]
                                ]);
                            Log::info("📡 [PERFEX CRM SYNCED] Sent to Perfex ID: {$currentLeadData->perfex_lead_id}");
                        }
                    } catch (\Exception $e) {
                        Log::error("❌ [PERFEX SYNC FAILED] " . $e->getMessage());
                    }

                    // 🎯 ارجاع و موازنه لود کاری (AI Matchmaking)
                    if (app()->bound(\App\Services\LeadRoutingService::class)) {
                        try {
                            app(\App\Services\LeadRoutingService::class)->assignToBestAgent($this->leadId, $aiResult);
                        } catch (\Exception $e) { Log::warning("Auto-routing skipped."); }
                    }

                    // 🎯 محاسبه رتبه پویای کل سیستم
                    try {
                        app(\App\Services\LeadScoringService::class)->calculateAndStoreScore(\App\Models\Lead::find($this->leadId));
                    } catch (\Exception $e) { Log::warning("ScoringService skipped."); }
                }
            }
        } catch (\Exception $e) {
            Log::error('Queue Worker Core Processing Failed: ' . $e->getMessage());
        }
    }

    protected function generateConversationSummary($leadId, $latestText, $apiKey)
    {
        try {
            $contextService = new \App\Services\ChatContextService();
            $fullHistory = $contextService->getConversationHistory($leadId, 10);

            if (empty($fullHistory)) {
                return "مشتری پیام فرستاد: " . $latestText;
            }

            $summaryPrompt = "کُل مکالمه زیر را در یک جمله کوتاه، دقیق و خلاصه به زبان فارسی بیان کن که کارشناس مهاجرت بفهمد هدف اصلی مشتری چیست:\n\n" . $fullHistory;

            $ch = curl_init("[https://api.gapgpt.app/v1/chat/completions](https://api.gapgpt.app/v1/chat/completions)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $summaryPrompt]
                ]
            ], JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $apiKey
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            $summaryText = data_get($result, 'choices.0.message.content');

            if (!empty($summaryText)) {
                return trim($summaryText);
            }
        } catch (\Exception $e) {
            Log::warning('Auto Summarization Failed: ' . $e->getMessage());
        }

        return "مشتری پیام فرستاد: " . $latestText;
    }
}