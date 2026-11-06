<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    public const PASSWORD = 'CmcDemo!2026';

    public function run(): void
    {
        if (! app()->environment('demo')) {
            throw new \RuntimeException('Tài khoản demo chỉ được tạo khi APP_ENV=demo.');
        }

        $accounts = [
            ['demo_customer', 'Khách hàng Demo', 'customer'],
            ['demo_sales', 'Kinh doanh Demo', 'sales'],
            ['demo_manager', 'Quản lý Demo', 'manager'],
            ['demo_warehouse', 'Nhân viên kho Demo', 'warehouse'],
            ['demo_accounting', 'Kế toán Demo', 'accounting'],
            ['demo_delivery', 'Giao nhận Demo', 'delivery'],
            ['demo_admin', 'Quản trị Demo', 'admin'],
        ];

        foreach ($accounts as [$username, $name, $role]) {
            User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@example.test',
                    'role' => $role,
                    'status' => 'active',
                    'password' => self::PASSWORD,
                ],
            );
        }
    }
}
