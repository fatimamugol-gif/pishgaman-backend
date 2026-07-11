<?php
// public/ai-tester.php

echo "<h2>--- تست اختصاصی و مجزای اتصال به GapGPT ---</h2>";

// کانفیگ‌های ارسالی شما
$apiKey = "sk-mhsMuwys4i1YrsE1V6tHKM1WvqFVSu4a6K0mTzk6rV9JZXa4"; // توکن واقعی خودت را بگذار
$baseUrl = "https://api.gapgpt.app/v1/responses"; // آدرس مستقیم اندپوینت پاسخ‌ها
$model = "gapgpt-qwen-3.5";
$testInput = "سلام، من می‌خوام برای سرمایه گذاری در آلمان اقدام کنم. شرایطش چیه؟";

// ساخت بدنه درخواست دقیقاً طبق ساختاری که فرستادی
$data = [
    'model' => $model,
    'input' => $testInput
];

$ch = curl_init($baseUrl);

// ۲. تنظیم زمان‌های انتظار بالاتر برای جلوگیری از ارور ۵۰۰ تایم‌اوت
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // ۲۰ ثانیه زمان برای ایجاد اتصال اولیه
curl_setopt($ch, CURLOPT_TIMEOUT, 90);        // ۹۰ ثانیه زمان برای دریافت کامل پاسخ از هوش مصنوعی

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// 💡 نکته طلایی: اگر روی سیستم از فیلترشکن (مثل v2ray یا پروکسی) استفاده می‌کنی، 
// خط زیر را فعال کن (از کامنت در بیار) تا cURL از پراکسی لوکال ویندوزت عبور کند:
// curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:10809"); 

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

echo "در حال ارسال درخواست به سرور و انتظار برای پردازش مدل... (لطفاً صبور باشید)<br>";
flush(); // خالی کردن بافر برای نشان دادن متن بالا در مرورگر

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<span style='color:red; font-weight:bold;'>❌ خطای شبکه cURL: " . curl_error($ch) . "</span>";
} else {
    echo "<span style='color:green; font-weight:bold;'>✅ ارتباط برقرار شد! وضعیت: {$httpCode}</span><br><br>";
    echo "<b>پاسخ خام دریافتی از سرور:</b><br>";
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($response) . "</pre>";
    
    $result = json_decode($response, true);
    echo "<br><b>نتیجه آرایه پارس شده در PHP:</b><br>";
    echo "<pre style='background:#eef; padding:10px;'>";
    print_r($result);
    echo "</pre>";
}

curl_close($ch);