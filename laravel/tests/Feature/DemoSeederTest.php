<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_role_accounts_and_opening_stock_idempotently(): void
    {
        $this->app->instance('env', 'demo');
        $this->seed(DatabaseSeeder::class);

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseHas('users', ['username' => 'demo_customer', 'role' => 'customer']);
        $this->assertDatabaseHas('users', ['username' => 'demo_sales', 'role' => 'sales']);
        $this->assertDatabaseHas('users', ['username' => 'demo_manager', 'role' => 'manager']);
        $this->assertDatabaseHas('users', ['username' => 'demo_warehouse', 'role' => 'warehouse']);
        $this->assertDatabaseHas('users', ['username' => 'demo_accounting', 'role' => 'accounting']);
        $this->assertDatabaseHas('users', ['username' => 'demo_delivery', 'role' => 'delivery']);
        $this->assertDatabaseHas('users', ['username' => 'demo_admin', 'role' => 'admin']);
        $this->assertTrue(Hash::check(DemoAccountSeeder::PASSWORD, (string) DB::table('users')->where('username', 'demo_customer')->value('password')));
        $this->assertDatabaseHas('stock_documents', ['code' => 'TON-DAU-KY-DEMO', 'status' => 'approved']);
        $this->assertDatabaseCount('stock_documents', 1);
        $this->assertGreaterThan(0, DB::table('inventory')->where('quantity', '>', 0)->count());
    }
}
