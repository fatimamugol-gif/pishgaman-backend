<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\NotificationService;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $channels;
    protected $title;
    protected $description;
    protected $phone;

    public function __construct($channels, $title, $description, $phone)
    {
        $this->channels = $channels;
        $this->title = $title;
        $this->description = $description;
        $this->phone = $phone;
    }

    public function handle()
    {
        // اجرای امن متد سرویس ناتیفیکیشن در پس‌زمینه ورکر
        NotificationService::send($this->channels, $this->title, $this->description, $this->phone);
    }
}