<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\ProcessPerfexWebhookJob;
use Illuminate\Support\Facades\Log;

class PerfexWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // ۱. اعتبارسنجی توکن امنیتی وب‌هوک (مثلا پرفکس توکن را در هدر می‌فرستد)
        $clientSecret = $request->header('X-Perfex-Webhook-Secret');
        
        if ($clientSecret !== config('services.perfex.webhook_secret')) {
            Log::warning('درخواست مشکوک و غیرمجاز به وب‌هوک پرفکس صادر شده است.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $event = $request->input('event'); // فرضا پرفکس نام ایونت مثل lead_created را می‌فرستد

        // ۲. فرستادن اطلاعات به صف برای پردازش غیرهمزمان
        ProcessPerfexWebhookJob::dispatch($event, $payload);

        // ۳. پاسخ سریع به پرفکس
        return response()->json(['status' => 'success', 'message' => 'Event queued'], 200);
    }
}