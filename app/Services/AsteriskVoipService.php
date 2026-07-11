<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AsteriskVoipService
{
    protected $host;
    protected $port;
    protected $username;
    protected $secret;

    public function __construct()
    {
        // 💡 حل ریشه‌ای: خواندن مستقیم و پویا از فایل .env شما جهت جلوگیری از تداخل هاردکد
        $this->host = env('ASTERISK_AMI_HOST', '192.168.1.5');
        $this->port = (int)env('ASTERISK_AMI_PORT', 5038);
        $this->username = env('ASTERISK_AMI_USER', 'admin');
        $this->secret = env('ASTERISK_AMI_SECRET', 'pishgaman_ami_secret');
    }

    /**
     * شلیک دستور Originate به AMI جهت برقراری تماس دوطرفه
     */
    public function originateCall($extension, $customerPhone)
    {
        Log::info("🔌 Trying to connect to Asterisk AMI at {$this->host}:{$this->port} for Ext {$extension}");

        // باز کردن سوکت فیزیکی با تایم‌اوت بهبودیافته
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$socket) {
            Log::error("🚨 AMI Connection Failed physically: $errstr ($errno) on host {$this->host}");
            return false;
        }

        // فعال کردن حالت غیرمسدودکننده برای پایداری در پاسخ‌ها
        stream_set_timeout($socket, 3);

        // ۱. لاگین به هسته AMI
        fwrite($socket, "Action: Login\r\n");
        fwrite($socket, "Username: {$this->username}\r\n");
        fwrite($socket, "Secret: {$this->secret}\r\n\r\n");

        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 8192);
            $response .= $line;
            if (trim($line) == '') break;
        }

        // بررسی دقیق موفقیت لاگین اعتباری آستریسک
        if (str_contains($response, 'Success') || str_contains($response, 'Accepted')) {
            Log::info("🔑 AMI Login Successful for user: {$this->username}");

            // ۲. شلیک دستور تماس دوطرفه
            fwrite($socket, "Action: Originate\r\n");
            fwrite($socket, "Channel: SIP/{$extension}\r\n"); 
            fwrite($socket, "Exten: {$customerPhone}\r\n");   
            fwrite($socket, "Context: from-internal\r\n");
            fwrite($socket, "Priority: 1\r\n");
            fwrite($socket, "CallerID: Pishgaman CRM <{$extension}>\r\n\r\n");

            // خواندن پاسخ شلیک جهت اطمینان از قرارگیری در صف تماس
            $originateResponse = '';
            while (!feof($socket)) {
                $line = fgets($socket, 8192);
                $originateResponse .= $line;
                if (trim($line) == '') break;
            }
            Log::info("📞 Asterisk Originate Response: " . trim($originateResponse));

            // ۳. خروج امن و بستن سوکت جهت جلوگیری از پر شدن مکس-کانکشن آستریسک
            fwrite($socket, "Action: Logoff\r\n\r\n");
            fclose($socket);
            return true;
        }

        Log::error("❌ AMI Authentication Denied by Asterisk for user {$this->username}. Response: " . trim($response));
        fclose($socket);
        return false;
    }
}