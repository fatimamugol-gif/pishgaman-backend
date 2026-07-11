<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminRoleUpgradeSeeder extends Seeder
{
    public function run()
    {
        // پیدا کردن کاربر قدیمی شما (مثلاً بر اساس اولین رکورد یا ایمیلی که لاگین می‌کردید)
        // می‌توانید ایمیل دقیق را اینجا بنویسید یا بگذارید روی رکورد اول دیتابیس فیکس شود
        $admin = DB::table('users')->first();

        if ($admin) {
            DB::table('users')->where('id', $admin->id)->update([
                'role' => 'supervisor', // 💡 ارتقای آنی به ناظر ارشد پیشگامان
                'password' => Hash::make('12345678'), // 🔐 ست کردن پسورد ایمن برای تست پرتال جدید
                'updated_at' => now()
            ]);
            $this->command->info("🎉 کاربر قدیمی فیلامنت با موفقیت به ناظر ارشد (Supervisor) ارتقا یافت!");
        } else {
            // اگر دیتابیس خالی بود، یک ناظر ارشد خام می‌سازد
            DB::table('users')->insert([
                'name' => 'ناظر ارشد پیشگامان',
                'email' => 'admin@pishgaman.com',
                'phone' => '09120000000',
                'role' => 'supervisor',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->command->info("✨ کاربر ناظر جدید به عنوان فالبک ساخته شد.");
        }
    }
}