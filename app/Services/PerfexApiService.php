<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerfexApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.perfex.base_url');
        $this->token = config('services.perfex.api_token');
    }

    /**
     * متد پایه و مرکزی برای ارسال تمام درخواست‌ها
     */
    public function request(string $method, string $endpoint, array $data = [])
    {
        // در ماژول API پرفکس معمولا توکن در هدر authtoken ارسال می‌شود
        $response = Http::withHeaders([
            'authtoken' => $this->token, 
            'Accept'    => 'application/json',
        ])->$method("{$this->baseUrl}/{$endpoint}", $data);

        // مدیریت خطاها
        if ($response->failed()) {
            Log::error("خطا در ارتباط با API پرفکس", [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'response' => $response->body()
            ]);
            
            return null;
        }

        return $response->json();
    }

    /**
     * گرفتن اطلاعات یک لید خاص از پرفکس
     */
    public function getLead(int $leadId)
    {
        return $this->request('get', "leads/{$leadId}");
    }

    /**
     * تغییر مشاورِ تخصیص‌داده‌شده به یک لید در پرفکس
     */
    public function assignLeadToStaff(int $leadId, int $staffId)
    {
        return $this->request('put', "leads/{$leadId}", [
            'assigned' => $staffId
        ]);
    }
}