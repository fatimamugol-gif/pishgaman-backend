<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PAMI\Client\Impl\ClientImpl as PamiClient;
use App\Services\AmiCallListener;

class ListenAsteriskAmi extends Command
{
    protected $signature = 'asterisk:ami-listen';
    protected $description = 'شنود زنده و هوشمند تماس‌های ایزابل با استفاده از پکیج قدرتمند PAMI';

    public function handle()
    {
        $options = [
            'host' => env('ASTERISK_AMI_HOST', '192.168.1.5'),
            'port' => (int)env('ASTERISK_AMI_PORT', 5038),
            'username' => env('ASTERISK_AMI_USER', 'laravel_brain'),
            'secret' => env('ASTERISK_AMI_SECRET', 'your_secure_ami_password'),
            'connect_timeout' => 10,
            'read_timeout' => 10000,
            
            // 🎯 تزریق مستقیم کارخانه رویداد سازگار با PHP 8
            'eventfactory' => new \App\Services\PamiPhp8EventFactory() 
        ];

        $this->info("🔌 Initializing PAMI Client with PHP 8 Compatibility...");

        try {
            $client = new PamiClient($options);
            $client->open();
            $this->info("✅ [PAMI SUCCESS] Connected and Authenticated perfectly!");

            $client->registerEventListener(new AmiCallListener());

            while (true) {
                $client->process();
                usleep(1000); 
            }

            $client->close();
        } catch (\Exception $e) {
            $this->error("❌ PAMI Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}