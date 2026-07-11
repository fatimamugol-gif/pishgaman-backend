<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadScoringService;
use App\Services\SmartRoutingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPerfexWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $event;
    protected array $payload;

    public function __construct(string $event, array $payload)
    {
        $this->event = $event;
        $this->payload = $payload;
    }

    /**
     * لاراول به صورت خودکار کلاس‌های سرویس را در متد handle تزریق (Inject) می‌کند
     */
    public function handle(LeadScoringService $scoringService)
    {
        Log::info("در حال پردازش ایونت پس‌زمینه پرفکس: {$this->event}");

        if ($this->event === 'lead_created' || $this->event === 'lead_updated') {
            $leadData = $this->payload['lead'] ?? [];
            
            if (empty($leadData['id'])) {
                return;
            }

            // ۲. محاسبه امتیاز لید با استفاده از سرویس
            $aiScore = $scoringService->calculate($leadData);

            // ۳. ذخیره یا به‌روزرسانی لید همراه با امتیاز محاسبه شده
            $lead = Lead::updateOrCreate(
                ['perfex_lead_id' => $leadData['id']],
                [
                    'source' => $leadData['source_name'] ?? null,
                    'ai_score' => $aiScore,
                    'status' => 'processing', // تغییر وضعیت به در حال پردازش
                    'behavioral_data' => $leadData, // ذخیره کل دیتا برای آرشیو و تحلیل‌های عمیق‌تر بعدی
                ]
            );
            
            Log::info("لید با شناسه پرفکس {$leadData['id']} امتیازدهی شد. امتیاز: {$aiScore}");
            
            $routingService->assign($lead);        
        }
    }
}