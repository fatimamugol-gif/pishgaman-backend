<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 💡 اتصال اتوماتیک لایه مای‌اس‌کیوال به دیتابیس برداری داکر
        \App\Models\KnowledgeBase::observe(\App\Observers\KnowledgeBaseObserver::class);
    }
}
