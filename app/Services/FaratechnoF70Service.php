<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;

class FaratechnoF70Service
{
    protected $ip;
    protected $port = 5005; // پورت ثابت دستگاه‌های ریلند/فراتکنو 

    public function __construct()
    {
        $this->ip = env('ATTENDANCE_DEVICE_IP', '192.168.1.145');
    }

    /**
     * برقراری ارتباط و دریافت لاگ‌ها
     */
    public function getLogs()
    {
        // باز کردن سوکت TCP 
        $socket = @fsockopen($this->ip, $this->port, $errno, $errstr, 5);
        if (!$socket) {
            throw new Exception("امکان اتصال به دستگاه وجود ندارد: $errstr ($errno)");
        }

        // ۱. دست‌تکانی اولیه (Handshake) 
        $handshakePacket = pack('H*', '55aa018000000000000000000000010000000000'); [cite: 33174]
        fwrite($socket, $handshakePacket);
        
        // خواندن پاسخ دست‌تکانی (۱۰ بایت) 
        $response = fread($socket, 10);
        if (bin2hex($response) !== 'aa550101000000000000') { [cite: 33180]
            fclose($socket);
            throw new Exception("تایید هویت اولیه با دستگاه ناموفق بود.");
        }

        // ۲. ارسال دستور درخواست لاگ‌ها (Get Logs) 
        // پکت دستور خواندن لاگ‌ها بر اساس لاگ ارسالی شما 
        $getLogsPacket = pack('H*', '55aa01a40000000000630000046b0000'); [cite: 31427]
        fwrite($socket, $getLogsPacket);

        // ۳. دریافت پاسخ اولیه (۱۰ بایت تاییدیه دستگاه) 
        $ack = fread($socket, 10);

        // ۴. خواندن جریان لاگ‌ها
        $allData = '';
        // تا زمانی که دستگاه داده ارسال می‌کند، آن‌ها را دریافت می‌کنیم
        while (!feof($socket)) {
            $chunk = fread($socket, 1024);
            if ($chunk === false || strlen($chunk) === 0) {
                break;
            }
            $allData .= $chunk;
        }
        fclose($socket);

        // ۵. پارس کردن داده‌های دریافتی به آرایه منظم
        return $this->parseLogData($allData);
    }

    /**
     * تجزیه دیتای باینری به رکوردهای قابل فهم
     */
    protected function parseLogData($binaryData)
    {
        $logs = [];
        $recordLength = 12; // هر رکورد دقیقاً ۱۲ بایت است 
        $totalBytes = strlen($binaryData);

        // تقسیم کل جریان باینری به رکوردهای ۱۲ بایتی 
        for ($i = 0; $i < $totalBytes; $i += $recordLength) {
            $record = substr($binaryData, $i, $recordLength);
            
            if (strlen($record) < $recordLength) {
                continue;
            }

            // باز کردن باینری (Unpack)
            // N: 32-bit unsigned long (big-endian) برای Timestamp و UserID 
            // C: unsigned char (1 byte) برای وضعیت‌ها 
            $unpacked = unpack('Ntimestamp/Nuserid/Cverify_mode/Cstatus/vreserved', $record); [cite: 19124]

            if ($unpacked) {
                // تبدیل کاربر به فرمت رشته‌ای تمیز (مانند ۱۱۲۱۰۲۳۷) 
                $userId = (string)$unpacked['userid'];

                // تبدیل زمان به Carbon لاراول 
                // توجه داشته باشید اگر تاریخ دستگاه شما اشتباه باشد (مثل ۲۰۹۴)، در دیتابیس هم همان ثبت می‌شود.
                $dateTime = Carbon::createFromTimestamp($unpacked['timestamp'])->toDateTimeString(); [cite: 19124]

                $logs[] = [
                    'user_id'       => $userId,
                    'datetime'      => $dateTime,
                    'verify_mode'   => $unpacked['verify_mode'], // نوع اعتبارسنجی 
                    'status'        => $unpacked['status'],      // ورود یا خروج 
                ];
            }
        }

        return $logs;
    }
}