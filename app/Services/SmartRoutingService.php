<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmartRoutingService
{
    protected PerfexApiService $perfexApi;

    // تزریق کلاینت API پرفکس برای اعمال تغییرات نهایی
    public function __construct(PerfexApiService $perfexApi)
    {
        $this->perfexApi = $perfexApi;
    }

    /**
     * پیدا کردن بهترین مشاور و ارجاع لید به او
     */
    public function assign(Lead $lead)
    {
        // استفاده از Database Transaction برای جلوگیری از تداخل در تخصیص همزمان (Race Condition)
        return DB::transaction(function () use ($lead) {
            
            // ۱. پیدا کردن مشاوران فعال که هنوز به سقف ظرفیت خود نرسیده‌اند
            $query = Agent::where('is_active', true)
                ->whereRaw('current_active_leads < max_capacity');

            // ۲. استراتژی تخصیص بر اساس امتیاز لید
            if ($lead->ai_score >= 70) {
                // لید بسیار باکیفیت است -> ارجاع به مشاور با بالاترین نرخ تبدیل موفق
                $agent = $query->orderBy('conversion_rate', 'desc')->first();
            } else {
                // لید معمولی است -> ارجاع به خلوت‌ترین مشاور برای حفظ تعادل حجم کار
                $agent = $query->orderBy('current_active_leads', 'asc')->first();
            }

            // ۳. اگر هیچ مشاوری خالی یا فعال نبود
            if (!$agent) {
                Log::warning("هیچ مشاور فعالی با ظرفیت خالی برای لید شماره {$lead->perfex_lead_id} پیدا نشد.");
                $lead->update(['status' => 'unassigned']);
                return null;
            }

            // ۴. به‌روزرسانی اطلاعات در دیتابیس لوکال لاراول
            $lead->update([
                'agent_id' => $agent->id,
                'status' => 'assigned'
            ]);

            // افزایش تعداد لیدهای فعال مشاور
            $agent->increment('current_active_leads');

            // ۵. شلیک دستور به پرفکس از طریق API جهت تغییر کارشناس لید در CRM اصلی
            $response = $this->perfexApi->assignLeadToStaff($lead->perfex_lead_id, $agent->perfex_staff_id);

            if ($response) {
                Log::info("لید {$lead->perfex_lead_id} با موفقیت به مشاور {$agent->name} ارجاع داده شد.");
                return $agent;
            }

            // اگر به هر دلیلی API پرفکس خطا داد، تغییرات لوکال را رول‌بک می‌کنیم (به لطف Transaction)
            throw new \Exception("خطا در به‌روزرسانی کارشناس لید در پرفکس");
        });
    }
}