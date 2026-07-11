<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfexTicket extends Model
{
    protected $connection = 'perfex';
    protected $table = 'tickets';

    // شمارش تیکت‌های فعال و پاسخ‌داده‌نشده مشتری
    public static function getOpenTicketsCount($perfexUserId)
    {
        // در پرفکس معمولاً وضعیت ۱ یعنی باز، ۲ یعنی در حال بررسی، ۴ یعنی پاسخ داده شده
        return self::where('userid', $perfexUserId)
            ->whereIn('status',) 
            ->count();
    }
}