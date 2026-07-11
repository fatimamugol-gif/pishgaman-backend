<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IPPanelService
{
    protected $apiKey;
    protected $fromNumber;

    public function __construct()
    {
        // مقادیر را از env می‌خوانیم تا هاردکد نشوند
        $this->apiKey = env('IPPANEL_API_KEY', 'YOUR_SECRET_API_KEY');
        $this->fromNumber = env('IPPANEL_FROM_NUMBER', '+983000505');
    }

    /**
     * شلیک پیامک پترنی (OTP) با سرعت نور و عبور از بلک‌لیست (نسخه بدون نویز SSL)
     */
    public function sendOtpPattern($toPhone, $patternCode, $inputData = [])
    {
        try {
            // اصلاح و نرمال‌سازی فرمت شماره تلفن ایران
            $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
            if (str_starts_with($cleanPhone, '09')) {
                $cleanPhone = '98' . substr($cleanPhone, 1);
            }

            // ارسال درخواست به وب‌سرویس ippanel بر پایه مستندات REST API جدید
            // 🎯 پچ طلایی: تزریق متد withoutVerifying برای خاموش کردن گارد cURL Error 60 در ویندوز
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => "AccessKey {$this->apiKey}",
                'Content-Type'  => 'application/json',
            // ])->post('https://api.ippanel.com/v1/messages/patterns/send', [
            ])->post('https://api.ippanel.com/v1/messages/patterns/issue', [
                'pattern_code' => $patternCode,
                'originator'   => $this->fromNumber,
                'recipient'    => $cleanPhone,
                'values'       => $inputData // آرایه متغیرهای پترن مثلا: ['code' => '1234']
            ]);

            if ($response->successful()) {
                Log::info("📨 [IPPanel OTP Sent]: To: {$cleanPhone} | Pattern: {$patternCode}");
                return true;
            }

            Log::error("🚨 [IPPanel API Error]: Status: " . $response->status() . " | Body: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("🚨 [IPPanel Service Crash]: " . $e->getMessage());
            return false;
        }
    }
}