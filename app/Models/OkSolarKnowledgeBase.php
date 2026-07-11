<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OkSolarKnowledgeBase extends Model
{
    // اتصال مستقیم به جدول اختصاصی اوکی‌سولار
    protected $table = 'ok_solar_knowledge_bases';

    protected $fillable = [
        'title',
        'category',
        'content'
    ];
}