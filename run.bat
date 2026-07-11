@echo off
title CRM Pishgaman Core Launcher

echo 🚀 Starting Backend Services...
cd /d "C:\Users\payon\Desktop\crm\perfex-brain"

:: 1. اجرای وب‌سرور لاراول
start "Laravel Server" php artisan serve

:: 2. اجرای سرور وب‌سوکت ریورب
start "Laravel Reverb" php artisan reverb:start

:: 3. اجرای صف‌ها و ورکر دیتابیس
start "Laravel Queue Worker" php artisan queue:work

:: 4. اجرای لیسنر مرکز تلفن آستریسک
start "Asterisk AMI Listener" php artisan asterisk:ami-listen

echo 🎨 Starting Frontend Next.js Server...
:: اینجا اگر مسیر پروژه فرانتت در پوشه دیگری است آدرسش را اصلاح کن
cd /d "C:\Users\payon\Desktop\crm\front\my-pishgaman-dashboard" 
start "Next.js Frontend" pnpm dev --port 3001

echo 🔥 All engines started successfully! Close this window to keep them running.
pause