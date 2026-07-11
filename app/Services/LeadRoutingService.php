<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadRoutingService
{
    /**
     * ۱. محاسبه امتیاز اولیه پیش‌بینانه (Predictive Pre-Scoring)
     */
    public function calculatePreScore(array $aiResult, string $source): int
    {
        $score = 0;

        // فاکتور الف: درجه فوریت شناسایی شده توسط AI (وزن حداکثر: ۴۰ امتیاز)
        $urgency = (int)($aiResult['urgency_score'] ?? 5);
        $score += ($urgency * 4);

        // فاکتور ب: نوع ویزا و ارزش تجاری آن برای موسسه (وزن حداکثر: ۳۰ امتیاز)
        $intent = $aiResult['intent'] ?? 'general_inquiry';
        $intentWeights = [
            'investment_visa' => 30, // سرمایه‌گذاری بالاترین ارزش مالی
            'work_visa' => 25,
            'study_visa' => 20,
            'tourist_visa' => 10,
            'general_inquiry' => 5
        ];
        $score += $intentWeights[$intent] ?? 5;

        // فاکتور ج: پایداری کانال ورودی (وزن حداکثر: ۳۰ امتیاز)
        // واتساپ و دایرکت به خاطر داشتن شماره یا تعامل مستقیم امتیاز بالاتری می‌گیرند
        if ($source === 'Whatsapp Chat') $score += 30;
        elseif ($source === 'Instagram Direct') $score += 25;
        else $score += 15; // تلگرام یا بات

        return min($score, 100);
    }

    /**
     * ۲. توزیع و تخصیص هوشمند کارشناسان (AI Matchmaking Routing)
     * مبتنی بر ظرفیت فعال، دپارتمان فلوچارت، و میزان خستگی (ثانیه مکالمه ایزابل)
     */
    public function assignToBestAgent(int $leadId, array $aiResult)
    {
        $lead = Lead::find($leadId);
        if (!$lead || !empty($lead->agent_id)) {
            return $lead?->agent_id; // اگر از قبل کارشناس دارد، تغییر نمی‌کند (Sticky Agent)
        }

        // الف. تشخیص دپارتمان هدف بر اساس فلوچارت
        $stage = $lead->pipeline_stage ?? 'call_center';
        $targetRole = 'call_center'; // پیش‌فرض ۴ نفر کال‌سنتر

        if ($stage === 'contract_followup') {
            $targetRole = 'contract_team';
        } elseif ($stage === 'executive_team') {
            $targetRole = 'executive_team';
        }

        // ب. واکشی کارشناسان واجد شرایط و فعال در این دپارتمان
        $agents = DB::table('agents')
            ->where('is_active', '1')
            ->where('role', $targetRole)
            ->get();

        if ($agents->isEmpty()) {
            Log::warning("⚠️ [ROUTING WARNING] No active agents found for role: {$targetRole}");
            return null;
        }

        $bestAgentId = null;
        $highestMatchScore = -1;

        // ج. الگوریتم ماتریسی پیدا کردن بهترین کارشناس (Matchmaking)
        foreach ($agents as $agent) {
            // چک کردن سقف ظرفیت
            $currentLeads = (int)$agent->current_active_leads;
            $maxCapacity = (int)($agent->max_capacity ?? 50);
            if ($currentLeads >= $maxCapacity) {
                continue; // ظرفیت این کارشناس پر است
            }

            $agentScore = 0;

            // ۱. فاکتور ظرفیت خالی (وزن بیشتر = امتیاز بیشتر برای کارشناس خلوت‌تر)
            $capacityRatio = ($maxCapacity - $currentLeads) / $maxCapacity;
            $agentScore += ($capacityRatio * 50); // حداکثر ۵۰ امتیاز

            // ۲. فاکتور خستگی و ثانیه مکالمه ایزابل (وزن معکوس = کسی که امروز کمتر صحبت کرده امتیاز بیشتری می‌گیرد)
            // سقف مکالمه روزانه استاندارد را ۵ ساعت (۱۸۰۰۰ ثانیه) فرض می‌کنیم
            $talkTime = (int)$agent->daily_talk_time_seconds;
            $fatigueRatio = max(0, (18000 - $talkTime) / 18000);
            $agentScore += ($fatigueRatio * 50); // حداکثر ۵۰ امتیاز

            // انتخاب کارشناسی که بالاترین امتیاز نهایی مچ‌میکینگ را دارد
            if ($agentScore > $highestMatchScore) {
                $highestMatchScore = $agentScore;
                $bestAgentId = $agent->id;
            }
        }

        // د. ثبت نهایی تخصیص در دیتابیس
        if ($bestAgentId) {
            DB::table('leads')->where('id', $leadId)->update([
                'agent_id' => $bestAgentId,
                'status' => 'assigned',
                'updated_at' => now()
            ]);

            DB::table('agents')->where('id', $bestAgentId)->increment('current_active_leads');
            Log::info("🎯 [MATCHMAKER SUCCESS] Lead ID {$leadId} routed to Agent ID {$bestAgentId} with Match Score: {$highestMatchScore}");
            
            return $bestAgentId;
        }

        Log::critical("🚨 [ROUTING FAILED] All agents for role {$targetRole} are over capacity!");
        return null;
    }
}