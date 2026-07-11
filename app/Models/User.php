<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * فیلدهای مجاز جهت ثبت و ویرایش انبوه در هسته کورتکس سیستم
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'department_id',
        'voip_extension',
        'permissions'
    ];

    /**
     * فیلدهای پنهان در پاسخ‌های جیسون جهت حفظ حریم خصوصی
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * کستینگ هوشمند دسترسی‌ها و کلمه‌های عبور هش‌شده
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array', // 🎯 کستینگ اتوماتیک جیسون پرمیشن‌ها به آرایه امن PHP
    ];
}