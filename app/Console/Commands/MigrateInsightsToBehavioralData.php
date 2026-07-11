<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateInsightsToBehavioralData extends Command
{
    // نام دستوری که در ترمینال می‌زنیم
    protected $signature = 'leads:migrate-insights';
    protected $description = 'انتقال دیتای تحلیل شده قدیمی از جدول insights به ستون behavioral_data در جدول لیدها';

    public function handle()
    {
        $this->info('🚀 Starting data migration for Perfex Brain...');

        // خواندن تمام دیتای قدیمی
        $insights = DB::table('customer_insights')->get();
        $count = 0;

        foreach ($insights as $insight) {
            // پیدا کردن لید متصل به این رکورد
            $lead = DB::table('leads')->where('perfex_lead_id', $insight->customer_id)->first();

            if ($lead) {
                // حفظ دیتای موجود (اگر قبلاً چیزی به صورت ناقص ثبت شده بود)
                $existingData = json_decode($lead->behavioral_data, true) ?? [];

                // ساخت پکیج یکپارچه جدید دقیقاً با استانداردی که مشخص کردیم
                $newData = [
                    'lead_id' => (string)$lead->id,
                    'source' => $lead->source ?? 'Unknown',
                    'channel' => $existingData['channel'] ?? 'unknown',
                    'visit_frequency' => $existingData['visit_frequency'] ?? 1,
                    'last_chat' => $insight->last_activity_summary ?? ($existingData['last_chat'] ?? ''),
                    'intent' => $insight->last_intent ?? 'general_inquiry',
                    'destination' => $insight->likely_destination ?? 'نامشخص',
                    'urgency' => ((int)$insight->urgency_score > 6) ? 'high' : 'medium',
                    'interest_level' => $insight->interest_level ?? 'medium',
                    'conversation_summary' => $insight->last_activity_summary ?? '',
                    'keywords' => json_decode($insight->top_keywords, true) ?? [],
                    'recommended_action' => $insight->recommended_action ?? '',
                    'entry' => $existingData['entry'] ?? []
                ];

                // آپدیت ستون در جدول اصلی لید
                DB::table('leads')->where('id', $lead->id)->update([
                    'behavioral_data' => json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $count++;
                $this->line("Migrated data for Lead ID: {$lead->id}");
            }
        }

        $this->info("✅ Success! Total {$count} records migrated flawlessly.");
        $this->warn("💡 You can now remove fallback codes from LeadResource and safely drop the 'customer_insights' table.");
    }
}