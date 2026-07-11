<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRecentPerfexLeadsCommand extends Command
{
    protected $signature = 'perfex:import-recent-leads';
    protected $description = 'واکشی ۱۰۰۰ لید اخیر از پرفکس و مپینگ اتمیک با ساختار فیزیکی دیتابیس پیشگامان';

    public function handle()
    {
        $this->warn('🚀 استارت فاز ایمپورت و همگام‌سازی ۱۰۰۰ لید اخیر پرفکس...');
        Log::info('🚀 [PERFEX IMPORT] Starting synchronization of 1000 recent leads.');

        // ۱. واکشی دقیق توکن از فایل .env
        $perfexApiKey = env('PERFEX_API_TOKEN');
        if (empty($perfexApiKey)) {
            $this->error("❌ خطا: متغیر PERFEX_API_TOKEN در فایل .env یافت نشد!");
            return Command::FAILURE;
        }

        // تضمین فرمت توکن (پاک کردن کلمه Bearer در صورتی که به اشتباه در env وارد شده باشد)
        $cleanToken = str_replace('Bearer ', '', $perfexApiKey);

        // آدرس اندپوینت ماژول لیدهای پرفکس موسسه
        $baseUrl = "https://xip.2visa.ir/api/v1/leads"; 

        $totalToImport = 1000;
        $perPage = 100; 
        $importedCount = 0;
        $skippedCount = 0;

        // واکشی گام‌به‌گام ۱۰۰ تایی
        for ($page = 1; $importedCount + $skippedCount < $totalToImport; $page++) {
            
            try {
                // شلیک ریکوئست رسمی به پرفکس
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $cleanToken, // ارسال توکن لایروبی شده
                        'Content-Type' => 'application/json'
                    ])
                    ->get($baseUrl, [
                        'limit' => $perPage,
                        'page' => $page
                    ]);

                // 🕵️‍♂️ گارد کنترل عیب‌یابی: اگر صفحه اول ناموفق بود یا خالی برگشت، دیتای خام را اسکن میکنیم
                if (!$response->successful()) {
                    $this->error("\n❌ خطا در ارتباط با API پرفکس | کد وضعیت: " . $response->status());
                    Log::error("[PERFEX IMPORT ERROR] API failed status: " . $response->status());
                    break;
                }

               $leads = $response->json();

// 🕵️‍♂️ تزریق ردیاب فوق‌پیشرفته: چاپ ساختار بدنه پاسخ پرفکس برای کشف گره کور
$this->warn("\n🔍 [DEBUG RAW RESPONSE] Page {$page} body content:");
$this->line($response->body()); 

if (isset($leads['data'])) {
    $leads = $leads['data'];
}

                if (empty($leads) || !is_array($leads)) {
                    $this->warn("\n🔔 اطلاعاتی در صفحه {$page} یافت نشد یا به انتهای لیدها رسیدیم.");
                    break;
                }

                foreach ($leads as $perfexLead) {
                    if ($importedCount + $skippedCount >= $totalToImport) {
                        break;
                    }

                    // استخراج ایمن شناسه عددی پرفکس
                    $perfexId = isset($perfexLead['id']) ? (int)$perfexLead['id'] : (isset($perfexLead['leadid']) ? (int)$perfexLead['leadid'] : null);
                    if (!$perfexId) {
                        continue;
                    }

                    // استخراج و لایروبی شماره تلفن متقاضی
                    $phone = $perfexLead['phonenumber'] ?? $perfexLead['phone'] ?? null;
                    if ($phone) {
                        $phone = preg_replace('/[^0-9]/', '', $phone);
                        if (str_starts_with($phone, '989')) {
                            $phone = '0' . substr($phone, 2);
                        } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
                            $phone = '0' . $phone;
                        }
                    }

                    // 🛡️ گارد نجات اتمیک: بررسی عدم تداخل لید بر اساس ساختار کلید دیتابیس شما
                    $localLead = DB::table('leads')->where('perfex_lead_id', $perfexId)->first();
                    if (!$localLead && !empty($phone)) {
                        $localLead = DB::table('leads')->where('phone', $phone)->first();
                    }

                    // واکشی فیلدهای چهل‌گانه سن و توان مالی از زیرآرایه سفارشی پرفکس
                    $customFields = $perfexLead['customfields'] ?? [];
                    $age = null;
                    $financialCap = 0;

                    foreach ($customFields as $field) {
                        $fieldId = $field['id'] ?? $field['fieldid'] ?? '';
                        if ($fieldId == '76' || ($field['slug'] ?? '') == 'age') {
                            $age = !empty($field['value']) ? (int)$field['value'] : null;
                        }
                        if ($fieldId == '139' || ($field['slug'] ?? '') == 'financial_capability') {
                            $financialCap = !empty($field['value']) ? (int)preg_replace('/[^0-9]/', '', $field['value']) : 0;
                        }
                    }

                    // ساختاردهی مپینگ لید کاملاً منطبق بر ساختار فایل SQL دیتابیس crmpishgaman_ai-brain
                    $dataToSave = [
                        'perfex_lead_id' => $perfexId,
                        'name' => $perfexLead['name'] ?? 'کاربر پرفکس',
                        'email' => $perfexLead['email'] ?? null,
                        'phone' => $phone,
                        'age' => $age,
                        'financial_capability_toman' => $financialCap,
                        'source' => $perfexLead['source_name'] ?? $perfexLead['source'] ?? 'Perfex CRM',
                        'initial_consultation_status' => 'مشاوره جدید',
                        'import_source' => 'perfex_sync',
                        'behavioral_data' => json_encode([
                            'company' => $perfexLead['company'] ?? '',
                            'assigned_staff_id' => $perfexLead['assigned'] ?? 0,
                            'date_added' => $perfexLead['dateadded'] ?? now()->format('Y-m-d H:i:s'),
                            'last_status' => $perfexLead['status_name'] ?? 'new'
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now()
                    ];

                    if ($localLead) {
                        // لید از قبل وجود دارد -> به‌روزرسانی مشخصات و قفل کردن هماهنگی
                        DB::table('leads')->where('id', $localLead->id)->update($dataToSave);
                        $skippedCount++;
                    } else {
                        // لید جدید است -> درج فیزیکی در دیتابیس لوکال
                        $dataToSave['created_at'] = now();
                        DB::table('leads')->insert($dataToSave);
                        $importedCount++;
                    }
                }

                $this->info("📋 صفحه {$page} با موفقیت تحلیل شد. (جدید: {$importedCount} | به‌روزرسانی: {$skippedCount})");

            } catch (\Exception $e) {
                $this->error("\n⚠️ خطای فنی در پردازش لایه تراکنش: " . $e->getMessage());
                Log::error("[PERFEX IMPORT CRASH] " . $e->getMessage());
                break;
            }
        }

        $this->info("\n🏁 عملیات با موفقیت به پایان رسید!");
        $this->info("📥 تعداد لیدهای جدید وارد شده: {$importedCount}");
        $this->info("🔄 تعداد لیدهای قدیمی همگام شده: {$skippedCount}");
        
        return Command::SUCCESS;
    }
}