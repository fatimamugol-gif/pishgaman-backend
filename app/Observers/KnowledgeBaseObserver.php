<?php

namespace App\Observers;

use App\Models\KnowledgeBase;
use App\Services\VectorService;

class KnowledgeBaseObserver
{
    protected $vectorService;

    public function __construct()
    {
        $this->vectorService = new VectorService();
    }

    /**
     * بعد از ایجاد شدن ردیف در دیتابیس اصلی، آن را به دیتابیس برداری بفرست
     */
    public function created(KnowledgeBase $knowledgeBase): void
    {
        if ($knowledgeBase->is_active) {
            $this->vectorService->indexText(
                $knowledgeBase->id, 
                $knowledgeBase->title, 
                $knowledgeBase->content, 
                $knowledgeBase->file_path // 💡 پاس دادن مسیر فایل پی‌دی‌اف
            );
        }
    }

    /**
     * بعد از ویرایش شدن قانون، نسخه برداری داکر را هم آپدیت کن
     */
    public function updated(KnowledgeBase $knowledgeBase): void
    {
        if ($knowledgeBase->is_active) {
            $this->vectorService->indexText(
                $knowledgeBase->id, 
                $knowledgeBase->title, 
                $knowledgeBase->content, 
                $knowledgeBase->file_path
            );
        }
    }
}