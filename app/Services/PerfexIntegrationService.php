<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerfexIntegrationService
{
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('PERFEX_BASE_URL'), '/');
        $this->token = env('PERFEX_API_TOKEN');
    }

    /**
     * ۱. دریافت فیلدهای سفارشی و علاقه‌مندی‌های لید از API پرفکس
     */
    /**
     * ۱. دریافت فیلدهای سفارشی لید از API پرفکس
     */
    public function getLeadPreferences(int $perfexLeadId)
    {
        try {
            // 💡 اضافه شدن withoutVerifying برای حل باگ ویندوز
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->token])
                ->get("{$this->baseUrl}/leads/{$perfexLeadId}");

            if ($response->successful()) {
                return collect($response->json('custom_fields') ?? []);
            }
            return collect();
        } catch (\Exception $e) {
            Log::error("❌ [PERFEX API] Error fetching lead: " . $e->getMessage());
            return collect();
        }
    }

    /**
     * ۲. محاسبه مجموع مبالغ پرداختی مشتری از طریق API فاکتورها
     */
    public function getCustomerTotalPaid(int $perfexClientId): float
    {
        if (!$perfexClientId) return 0.0;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->token])
                ->get("{$this->baseUrl}/invoices", ['clientid' => $perfexClientId]);

            if ($response->successful()) {
                $invoices = $response->json() ?? [];
                $totalPaid = 0.0;
                foreach ($invoices as $invoice) {
                    if (data_get($invoice, 'status') == 2 || data_get($invoice, 'status_name') === 'Paid') {
                        $totalPaid += (float) data_get($invoice, 'total', 0);
                    }
                }
                return $totalPaid;
            }
            return 0.0;
        } catch (\Exception $e) {
            Log::error("❌ [PERFEX API] Error fetching payments: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * ۳. دریافت تعداد تیکت‌های فعال مشتری از طریق API تیکت‌ها
     */
    public function getOpenTicketsCount(int $perfexClientId): int
    {
        if (!$perfexClientId) return 0;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->token])
                ->get("{$this->baseUrl}/tickets", ['clientid' => $perfexClientId]);

            if ($response->successful()) {
                $tickets = $response->json() ?? [];
                return count(array_filter($tickets, function($ticket) {
                    return in_array(data_get($ticket, 'status'),); 
                }));
            }
            return 0;
        } catch (\Exception $e) {
            Log::error("❌ [PERFEX API] Error fetching tickets count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * جستجوی لید یا کلاینت در پرفکس بر اساس شناسه شبکه‌های اجتماعی
     */
    public function findLeadBySocialId(string $field, string $value): ?array
    {
        try {
            // استعلام از اندپوینت لیدهای پرفکس
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->token])
                ->get("{$this->baseUrl}/leads", [
                    $field => $value // جستجو بر اساس ستون داینامیک در پرفکس
                ]);

            if ($response->successful() && !empty($response->json())) {
                // برگشت دادن اطلاعات اولین لید پیدا شده
                return collect($response->json())->first();
            }
        } catch (\Exception $e) {
            Log::error("❌ [PERFEX API] Failed to search lead by social ID: " . $e->getMessage());
        }

        return null;
    }

    /**
 * واکشی زنده لیست کارکنان از هسته API پرفکس
 */
        public function getStaff()
        {
            try {
                $perfexApiKey = env('PERFEX_API_KEY', 'Bearer YOUR_TOKEN');
                $url = "https://cip.2visa.ir/api/v1/staff";

                $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $perfexApiKey,
                        'Content-Type' => 'application/json'
                    ])
                    ->timeout(15)
                    ->get($url);

                if ($response->successful()) {
                    return $response->json();
                }
                return [];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("❌ [PERFEX API CRASH] getStaff failed: " . $e->getMessage());
                return [];
            }
        }
}