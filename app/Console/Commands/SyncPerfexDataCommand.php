<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PerfexIntegrationService;
use Illuminate\Support\Facades\DB;

class SyncPerfexDataCommand extends Command
{
    protected $signature = 'perfex:sync';
    protected $description = 'Sync staff and crucial lead data from Perfex CRM directly to local Read-Models';

    public function handle()
    {
        $this->info('🔄 Starting synchronization with Perfex CRM...');
        $perfexService = app(PerfexIntegrationService::class);

        // ۱. دانلود و بروزرسانی خودکار لیست مشاوران/کارمندان پرفکس
        $staffList = $perfexService->getStaff(); // فرض بر وجود این متد در سرویس شما بر اساس مستندات /v1/staff

        if (!empty($staffList)) {
            foreach ($staffList as $staff) {
                if (($staff['is_not_staff'] ?? 0) == 1) continue;

                DB::table('agents')->updateOrInsert(
                    ['perfex_staff_id' => $staff['staffid']],
                    [
                        'name' => $staff['firstname'] . ' ' . $staff['lastname'],
                        'email' => $staff['email'],
                        'is_active' => $staff['active'] ?? 1,
                        'updated_at' => now()
                    ]
                );
            }
            $this->info('👥 Staff list synced perfectly.');
        }

        $this->info('🏁 Syncing finished successfully!');
    }
}