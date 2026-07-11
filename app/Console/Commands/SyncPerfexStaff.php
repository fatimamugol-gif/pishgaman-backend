<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Agent;

class SyncPerfexStaff extends Command
{
    protected $signature = 'perfex:sync-staff';
    protected $description = 'همگام‌سازی و دریافت لیست کارشناسان فروش از پرفکس سی‌آر‌ام';

   public function handle()
    {
        $this->info('در حال اتصال به Perfex CRM برای دریافت کارمندان...');

        $baseUrl = rtrim(env('PERFEX_BASE_URL'), '/');
        // طبق داکیومنت، اندپوینت دریافت و ارسال به صورت جمع (staffs) است
        $apiUrl = $baseUrl . '/staffs'; 
        $apiToken = env('PERFEX_API_TOKEN');

        if (!$baseUrl || !$apiToken) {
            $this->error('خطا: مقادیر PERFEX_BASE_URL یا PERFEX_API_TOKEN در فایل .env تعریف نشده‌اند!');
            return Command::FAILURE;
        }

        // ارسال درخواست با هدر اختصاصی authtoken و غیرفعال کردن SSL در لوکال
        $response = Http::withHeaders([
            'authtoken' => $apiToken 
        ])->withoutVerifying()->get($apiUrl);

        if ($response->failed()) {
            $this->error('خطا در برقراری ارتباط با API پرفکس! وضعیت پاسخ: ' . $response->status());
            return Command::FAILURE;
        }

        $staffList = $response->json();

        // برخی افزونه‌ها دیتا را درون یک کلید مثل data یا خود آرایه اصلی می‌فرستند
        // برای اطمینان، اگر خروجی مستقیما آرایه نبود، وضعیت را بررسی می‌کنیم
        if (!is_array($staffList)) {
            $this->error('دیتا به صورت آرایه معتبر دریافت نشد.');
            return Command::FAILURE;
        }

        // اگر پرفکس پاسخ را در قالب یک آبجکت شامل وضعیت موفقیت فرستاده باشد
        $actualList = $staffList['data'] ?? $staffList;

        foreach ($actualList as $staff) {
            // مپ کردن کلیدهای اختصاصی پرفکس (staffid یا id)
            $staffId = $staff['staffid'] ?? $staff['id'] ?? null;

            if (!$staffId) {
                continue;
            }

            Agent::updateOrCreate(
                ['perfex_staff_id' => $staffId],
                [
                    'name' => ($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''),
                    'email' => $staff['email'] ?? '',
                    'is_active' => isset($staff['active']) ? (bool)$staff['active'] : true,
                    
                    // اضافه کردن مقادیر عددی پیش‌فرض برای فیلدهایی که احتمالاً در دیتابیس شما Nullable نیستند:
                    'max_capacity' => 10, 
                    'current_active_leads' => 0, // مقدار اولیه عددی
                    'conversion_rate' => 0.00,   // مقدار اولیه اعشاری
                ]
            );
        }

        $this->info('همگام‌سازی با موفقیت انجام شد و کارشناسان در لاراول بروز شدند!');
        return Command::SUCCESS;
    }
}