<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DelayCompensationRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'rule_name' => 'نیم ساعت اول تاخیر (بدون جبران)',
                'delay_start_minutes' => 0,
                'delay_end_minutes' => 30,
                'compensation_minutes' => 0,
                'auto_leave_hours' => false,
                'auto_leave_duration_hours' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_name' => 'نیم ساعت دوم تاخیر (۱۰ دقیقه جبران)',
                'delay_start_minutes' => 30,
                'delay_end_minutes' => 60,
                'compensation_minutes' => 10,
                'auto_leave_hours' => false,
                'auto_leave_duration_hours' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_name' => 'نیم ساعت سوم تاخیر (۲۰ دقیقه جبران)',
                'delay_start_minutes' => 60,
                'delay_end_minutes' => 90,
                'compensation_minutes' => 20,
                'auto_leave_hours' => false,
                'auto_leave_duration_hours' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_name' => 'نیم ساعت چهارم تاخیر (۳۰ دقیقه جبران + مرخصی ۴ ساعته)',
                'delay_start_minutes' => 90,
                'delay_end_minutes' => 120,
                'compensation_minutes' => 30,
                'auto_leave_hours' => true,
                'auto_leave_duration_hours' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('next_delay_compensation_rules')->insert($rules);
    }
}
