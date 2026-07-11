<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateMockLeadsCommand extends Command
{
    // نام دستور به همراه پارامتر دلخواه تعداد ورودی
    protected $signature = 'crm:generate-leads {count=50 : تعداد لیدهای فرضی برای تولید}';
    protected $description = 'ربات هوشمند تولید و تزریق دیتای فرضی معتبر ایران منطبق بر ماتریس چهل‌گانه شناسنامه پیشگامان';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $this->warn("🚀 ربات در حال آماده‌سازی برای تولید {$count} لید فرضی با اطلاعات تفکیکی و معتبر است...");

        // بانک اسامی و فامیل‌های متنوع ایرانی جهت تولید داده‌های نشنال و پویا
        $firstNames = ['مهران', 'سارا', 'علیرضا', 'امیرحسین', 'نیلوفر', 'مهدی', 'سپیده', 'پویان', 'فرزاد', 'آناهیتا', 'رضا', 'کیمیا', 'آرش', 'مژگان', 'احسان', 'الناز'];
        $lastNames = ['صادقی', 'شهبازی', 'لطفی', 'صالحی', 'کریمی', 'ارجمنند', 'احمدی', 'موسوی', 'انصاری', 'رضایی', 'حسینی', 'مرادی', 'نیک‌زاد', 'غفاری', 'تهرانی'];
        
        $plans = ['مهاجرت تحصیلی', 'ویزای کاری (Job Seeker)', 'جاب آفر (Job Offer)', 'سرمایه‌گذاری', 'آوسبیلدونگ (Ausbildung)'];
        $countries = ['آلمان', 'اتریش', 'کانادا', 'عمان', 'ایتالیا'];
        $cities = ['تهران', 'مشهد', 'اصفهان', 'شیراز', 'کرج', 'تبریز', 'اهواز'];
        $educationLevels = ['کارشناسی', 'کارشناسی ارشد', 'دیپلم', 'دکتری'];
        $fields = ['مهندسی کامپیوتر', 'مدیریت بازرگانی', 'پرستاری', 'مکانیک', 'حسابداری', 'زبان انگلیسی', 'گرافیک'];
        $discoveryChannels = ['سرچ گوگل', 'اینستاگرام شرکت', 'کمپین تلگرام', 'تبلیغات یکتانت', 'توصیه دوستان'];

        $inserted = 0;

        // اسکن لایو ستون‌های جدول لیدها برای مهار ارور Column not found در دیتابیس شما
        $schemaColumns = Schema::getColumnListing('leads');

        for ($i = 0; $i < $count; $i++) {
            
            // تولید نام ترکیبی تصادفی
            $fullName = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            
            // تولید شماره موبایل ۱۱ رقمی معتبر ایران منحصربه‌فرد با پیش‌شماره‌های رایج
            $prefixes = ['0912', '0915', '0935', '0919', '0936', '0902', '0996'];
            $randomPhone = $prefixes[array_rand($prefixes)] . rand(1000000, 9999999);

            // اطمینان از عدم تکراری بودن شماره در کل فرآیند تست
            $phoneExists = DB::table('leads')->where('phone', $randomPhone)->exists();
            if ($phoneExists) {
                // 🎯 اصلاح لایه تولید شماره تلفن بدون سوختن شمارنده حلقه
            do {
                $prefixes = ['0912', '0915', '0911', '0936', '0937', '0913', '0917', '0919', '0936', '0902', '0996'];
                $randomPhone = $prefixes[array_rand($prefixes)] . rand(1000000, 9999999);
            } while (DB::table('leads')->where('phone', $randomPhone)->exists());
                        }

           // 🎯 الگوریتم هوشمند ضمانت عدم تکراری بودن آیدی پرفکس در حجم داده‌های بالا
            $safePerfexId = (int)(substr(time(), -5) . $i . rand(10, 99));

            // اگر عدد به خاطر طول زیاد از حد مجاز INT دیتابیس (2147483647) رد شد، فالبک تصادفی با چک کردن دیتابیس بزن
            if ($safePerfexId > 2147483647) {
                do {
                    $safePerfexId = rand(100000, 999999);
                } while (DB::table('leads')->where('perfex_lead_id', $safePerfexId)->exists());
            }

            // ظرف خام ماتریکس شناسنامه متقاضی بر اساس ساختار SQL دیتابیس شما
            $rawLeadData = [
                'perfex_lead_id' => $safePerfexId,
                'name' => $fullName,
                'phone' => $randomPhone,
                'current_city' => $cities[array_rand($cities)],
                'age' => rand(20, 42),
                'military_status' => (rand(0, 1) === 1) ? 'معافیت دائم' : 'پایان خدمت',
                
                'education_level' => $educationLevels[array_rand($educationLevels)],
                'field_of_study' => $fields[array_rand($fields)],
                'gpa' => number_format(rand(130, 195) / 10, 2), // معدل بین ۱۳ تا ۱۹.۵
                'lead_score' => rand(45, 95), // امتیازدهی داینامیک اولیه
                'work_and_insurance_history' => rand(1, 8) . ' سال سابقه کار مرتبط به همراه بیمه تامین اجتماعی',
                
                'requested_plan' => $plans[array_rand($plans)],
                'target_country' => $countries[array_rand($countries)],
                'financial_capability_toman' => rand(300, 1500) * 1000000, // تمکن مالی بین ۳۰۰ میلیون تا ۱.۵ میلیارد تومان
                'discovery_channel' => $discoveryChannels[array_rand($discoveryChannels)],
                'import_source' => 'mock_bot',
                'initial_consultation_status' => 'مشاوره جدید',
                'pipeline_stage' => 'initial_contact',
                
                'english_level' => 'B2',
                'german_level' => (rand(0, 1) === 1) ? 'B1' : 'بدون مدرک',
                'marital_status' => (rand(0, 1) === 1) ? 'single' : 'married',
                'children_count' => rand(0, 2),
                
                'department_id' => 1, // دپارتمان اولیه پیش‌فرض
                'agent_id' => rand(1, 2), // تخصیص رندوم به مشاور فرضی ۱ یا ۲ جهت تست اورفلو ناظر
                'status' => 'customer_service',
                'created_at' => now()->subMinutes(rand(1, 1440)), // توزیع زمان ساخت در طول روز برای نمودارهای فرانت
                'updated_at' => now()
            ];

            // 🛡️ جادوی فیلتر اتمیک: حذف فیلدهایی که در دیتابیس فیزیکی فعلی شما وجود ندارند
            $safeInsertData = collect($rawLeadData)->filter(function ($value, $key) use ($schemaColumns) {
                return in_array($key, $schemaColumns);
            })->toArray();

            // درج قطعی در دیتابیس لوکال
            DB::table('leads')->insert($safeInsertData);
            $inserted++;
        }

        $this->info("🏁 عملیات ربات با موفقیت کامل شد!");
        $this->info("📥 تعداد {$inserted} لید معتبر و شناسنامه‌دار ایرانی به کارتابل پنل تزریق شد.");
    }
}