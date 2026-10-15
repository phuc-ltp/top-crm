<?php

namespace Database\Seeders;

use App\Services\ActivityLogger;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        ActivityLogger::$enabled = false;

        $this->call([
            RolePermissionSeeder::class,
            ReferenceDataSeeder::class,
        ]);

        if (config('crm.demo_data') && ! app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        } else {
            $this->command?->info('Bỏ qua dữ liệu demo (CRM_DEMO_DATA=false hoặc môi trường production).');
        }

        ActivityLogger::$enabled = true;
    }
}
