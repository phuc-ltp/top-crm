<?php

namespace Tests;

use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // An toàn: không bao giờ chạy test trên database chính
        if (config('database.connections.mysql.database') === 'orangecrm_db') {
            $this->fail('Từ chối chạy test trên orangecrm_db.');
        }
        ActivityLogger::$enabled = true;
        $this->seed([RolePermissionSeeder::class, ReferenceDataSeeder::class]);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    protected function admin(): User
    {
        return $this->userWithRole('Super Admin');
    }

    protected function customer(array $attributes = []): Customer
    {
        return Customer::create($attributes + ['name' => 'Khách test '.uniqid(), 'type' => 'individual', 'status' => 'active']);
    }
}
