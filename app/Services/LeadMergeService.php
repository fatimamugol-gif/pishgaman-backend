<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadMergeService
{
    /**
     * ادغام لید فرعی (جدید) درون لید اصلی (سابقه دار قدیمی)
     */
    public function mergeLeads(int $sourceLeadId, int $targetLeadId): bool
    {
        return DB::transaction(function () use ($sourceLeadId, $targetLeadId) {
            $sourceLead = Lead::find($sourceLeadId);
            $targetLead = Lead::find($targetLeadId);

            if (!$sourceLead || !$targetLead) {
                return false;
            }

            Log::info("🔄 [IDENTITY MERGE] Merging Lead ID {$sourceLeadId} into Target Lead ID {$targetLeadId}");

            // ۱. انتقال و اتصال کانال‌های ارتباطی فرعی به لید اصلی
            $meta = [];
            if (empty($targetLead->instagram_sender_id) && !empty($sourceLead->instagram_sender_id)) {
                $meta['instagram_sender_id'] = $sourceLead->instagram_sender_id;
            }
            if (empty($targetLead->telegram_chat_id) && !empty($sourceLead->telegram_chat_id)) {
                $meta['telegram_chat_id'] = $sourceLead->telegram_chat_id;
            }
            if (empty($targetLead->phone) && !empty($sourceLead->phone)) {
                $meta['phone'] = $sourceLead->phone;
            }

            if (!empty($meta)) {
                $targetLead->update($meta);
            }

            // ۲. انتقال کل تاریخچه چت‌های کانال فرعی به کانال اصلی (پیوستگی حافظه RAG)
            DB::table('chat_logs')
                ->where('lead_id', $sourceLeadId)
                ->update(['lead_id' => $targetLeadId]);

            // ۳. ترکیب اطلاعات هوشمند جیسون
            $mergedBehavioral = array_merge(
                $targetLead->behavioral_data ?? [],
                $sourceLead->behavioral_data ?? []
            );
            $mergedBehavioral['title'] = $targetLead->behavioral_data['title'] ?? $targetLead->name;

            $targetLead->update([
                'behavioral_data' => $mergedBehavioral,
                'updated_at' => now()
            ]);

            // ۴. حذف فیزیکی سطر موازی و تکراری برای خلوت شدن پنل فیلامنت
            $sourceLead->delete();

            return true;
        });
    }
}