<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    // ✅ ۱. باز کردن کامل قفل مدل (آرایه fillable قدیمی را کاملاً حذف کن)
    protected $guarded = [];

    // مدیریت کستینگ برای خواندن بدون خطای دیتای جیسون چهل‌گانه و پرفکس
    protected $casts = [
        'behavioral_data' => 'array',
        'lead_score' => 'integer',
        'ai_score' => 'integer',
        
        // 💡 فیلدهای چهل‌گانه جدید اضافه شده به کستینگ
        'tags' => 'array',
        'language_test_history' => 'array',
        'call_today_flag' => 'boolean',
        'financial_capability_toman' => 'integer',
        'children_count' => 'integer',
        'age' => 'integer',
    ];

    /**
     * 👥 ارتباط با کارشناس فروش
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    /**
     * 💬 ارتباط با لاگ چت‌ها (با هر دو نام برای سازگاری کامل سیستم)
     */
    public function chatLogs(): HasMany
    {
        return $this->hasMany(ChatLog::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(ChatLog::class, 'lead_id');
    }

    /**
     * 📊 ارتباط با جدول تحلیل هوشمند
     */
    public function customerInsight(): HasOne
    {
        return $this->hasOne(CustomerInsight::class, 'customer_id', 'perfex_lead_id');
    }

    public function insight(): HasOne
    {
        return $this->hasOne(CustomerInsight::class, 'customer_id', 'perfex_lead_id');
    }

    /**
     * ⚡ بوت‌متد اختصاصی برای مدیریت زنده ظرفیت کارشناسان
     */
    protected static function booted()
    {
        static::updated(function ($lead) {
            // اگر وضعیت لید به یکی از حالت‌های پایانی تغییر کرد
            if ($lead->isDirty('status') && in_array($lead->status, ['closed', 'converted', 'rejected', 'archived'])) {
                $agentId = $lead->agent_id;
                
                if ($agentId) {
                    // کاهش خودکار یک واحد از لیدهای فعال کارشناس
                    \DB::table('agents')
                        ->where('id', $agentId)
                        ->where('current_active_leads', '>', 0)
                        ->decrement('current_active_leads');
                        
                    \Log::info("🔀 Streamlining: Agent ID {$agentId} capacity released from table 'agents'.");
                }
            }
        });
    }
}