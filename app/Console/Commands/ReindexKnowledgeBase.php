<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeBase;
use App\Services\VectorService;

class ReindexKnowledgeBase extends Command
{
    protected $signature = 'knowledgebase:reindex';
    protected $description = 'Sync all MySQL knowledge base records into Qdrant Vector DB';

    public function handle()
    {
        $this->info('🚀 Starting Knowledge Base Reindexing to Qdrant...');
        
        $records = KnowledgeBase::where('is_active', true)->get();
        
        if ($records->isEmpty()) {
            $this->warn('⚠️ No active knowledge base records found in MySQL.');
            return 0;
        }

        $vectorService = new VectorService();
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $record) {
            // 💡 اصلاح طلایی: پاس دادن آرگومان چهارم (file_path) به متد ایندکس
            $success = $vectorService->indexText(
                $record->id, 
                $record->title, 
                $record->content, 
                $record->file_path
            );
            
            if (!$success) {
                $this->error("\n❌ Failed to index Document ID: {$record->id}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n\n🎯 All records have been successfully synchronized to Qdrant!");
        return 0;
    }
}