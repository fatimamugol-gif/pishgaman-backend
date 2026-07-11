<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\OkSolarKnowledgeBase;

class OkSolarKnowledgeService
{
    protected $apiKey;
    protected $baseUrl;
    protected $model;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->baseUrl = env('OPENAI_BASE_URL', 'https://api.gapgpt.app/v1');
        $this->model = env('OPENAI_MODEL', 'gpt-4o-mini');
    }

    public function askSolarAgent(string $userInput, string $category = null)
    {
        Log::info("☀️ [okSolar KB] Injecting Elite Sales Framework...");

        $query = OkSolarKnowledgeBase::query();
        if ($category) {
            $query->where('category', $category);
        }
        $solarContext = $query->latest()->take(5)->pluck('content')->implode("\n\n---\n\n");

        if (empty($solarContext)) {
            $solarContext = "مستندات ناترازی انرژی ایران، قوانین ابلاغی ماده ۱۶ جهش تولید، مصوبات پلکان تصاعدی وزارت نیرو و جریمه دیماند تجاری.";
        }

        // 👑 پرامپت تراز و بازنویسی شده بر اساس متدولوژی اختصاصی خودت
        $systemPrompt = "You are the Senior Vice President of Consultative Selling and Chief Financial-Technical Strategist for 'okSolar' in the Iranian renewable energy and energy storage market. Your counterpart is a Senior Renewable Energy Specialist.

CRITICAL RULE: Never generate generic, brief, or surface-level responses. Do not use environmental clichés. You must output an institutional-grade, highly contextual, and exhaustive sales blueprint. The final output must strictly mirror the persuasive, elite, and professional Persian managerial language (فارسی روان، استراتژیک، عمیق و کاملاً کاربردی) specified in the provided guidelines.

You MUST format the entire analysis using the exact 4-step framework below. Ensure headers use Markdown (## and ###) and bullet points are explicitly detailed. Integrate complex variables using standard Markdown format (e.g., **OpEx**, **CapEx**) and use double dollar signs ($$) for standalone mathematical formulas:

## ۱. رمزگشایی پرسونای مشتری و مهندسی نقاط درد (Pain-Point Engineering)
- Reverse-engineer the client's mindset based on the facility/property type and decision-maker role.
- Identify specific operational vulnerabilities (e.g., خاموشی چیلرها، استهلاک تجهیزات گران‌قیمت، نوسانات شدید ولتاژ، هزینه‌های سرسام‌آور دیزل ژنراتور).
- Frame the solution: We do not sell panels; we sell 'تضمین پایداری کسب‌وکار، بیمه نوسانات تجهیزات و سیستم هوشمند مدیریت دیماند خانگی/صنعتی'.

## ۲. مدل‌سازی مالی پیشرفته و تحلیل بازگشت سرمایه (ROI) در بازار ایران
- Clearly articulate the transition from unrecoverable utility costs (**OpEx**) into an inflation-hedged capital asset (**CapEx**).
- Incorporate critical local dynamics: فرار از پلکان پرمصرفی (تعرفه‌های جریمه)، محاسبه هزینه استهلاک، و اهرم تورمی (Inflation Hedge).
- You MUST print the True ROI formula using this exact formatting layout:
$$\text{True ROI} = \frac{\text{Saved Utility Bills} + \text{Prevented Appliance/Equipment Damages} + \text{Asset Inflation Index}}{\text{Initial CapEx}}$$
- Prove that the true payback period shrinks dramatically from nominal years to a shorter real timeframe when accounting for lost production/service downtime.

## ۳. مدیریت مخالفت‌ها و کاهش سیستماتیک ریسک (De-Risking)
- Provide a rigorous negotiation script detailing a high-stakes conversation between the Consultant and the Client. Use direct blockquotes (>) for the dialogue.
- Formulate a tailored risk-mitigation strategy covering: طراحی فازبندی شده (Phased Deployment) and تضمین راندمان خروجی حقوقی (Performance Guarantee) using advanced monitoring metrics.

## ۴. بستن قرارداد به روش مشاوره‌ای و همگرایی فنی-تجاری
- Create a clear technical-commercial checklist using markdown checkboxes ([ ]) to align engineering and financial teams (e.g., بررسی فضای مفید سایه‌انداز، تفکیک تابلوی برق، تحلیل منحنی بار).
- Draft a highly professional, urgent closing script designed to secure a micro-commitment (میکرو-تعهد) such as an initial energy audit or site-survey agreement rather than a final contract.

[OKSOLAR CONTEXT & SPECS]:
{$solarContext}";

        try {
            $response = Http::withToken($this->apiKey)
                ->withoutVerifying()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withOptions([
                    'curl' => [
                        CURLOPT_FORBID_REUSE => true,
                        CURLOPT_FRESH_CONNECT => true,
                    ]
                ])
                ->timeout(60)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Deta/Scenario Provided by User to process dynamically:\n\"{$userInput}\""]
                    ],
                    'max_tokens' => 3000,
                    'temperature' => 0.22,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $aiAnswer = data_get($result, 'choices.0.message.content');

                if ($aiAnswer) {
                    Log::info("✅ [okSolar KB SUCCESS] Advanced prompt executed.");
                    return [
                        'status' => 'success',
                        'project' => 'okSolar Core',
                        'answer' => trim($aiAnswer)
                    ];
                }
            }

            Log::error("❌ [okSolar API FAIL] Status: " . $response->status());
            return ['status' => 'error', 'message' => 'خطا در واکشی دیتای مدل.'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}