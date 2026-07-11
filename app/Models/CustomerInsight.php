<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInsight extends Model
{

    protected $table = 'customer_insights';

    // فیلدهای پرشدنی
    protected $fillable = [
        'customer_id', 'last_intent', 'likely_destination', 
        'urgency_score', 'recommended_action', 'top_keywords'
    ];
    protected $guarded = [];

    // این بخش به لاراول می‌گوید دیتاهای جی‌سان دیتابیس را اتوماتیک به آرایه پی‌اچ‌پب تبدیل کند
    protected $casts = [
        'top_keywords' => 'array',
    ];
}