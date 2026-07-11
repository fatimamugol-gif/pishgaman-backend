<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfexPayment extends Model
{
    protected $connection = 'perfex'; // استفاده از کانکشن پرفکس
    protected $table = 'invoicepaymentrecords'; // جدول پرداخت‌های پرفکس

    // ارتباط با لید یا مشتری در پرفکس
    public static function getCustomerTotalPaid($perfexUserId)
    {
        // این متد لایو تمام پرداخت‌های یک کاربر را جمع می‌زند
        return self::whereHas('invoice', function($query) use ($perfexUserId) {
            $query->where('clientid', $perfexUserId);
        })->sum('amount');
    }

    public function invoice()
    {
        return $this->belongsTo(PerfexInvoice::class, 'invoiceid');
    }
}