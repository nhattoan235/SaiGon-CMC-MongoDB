<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('demo')) {
            throw new RuntimeException('DemoSeeder chỉ được chạy khi APP_ENV=demo.');
        }

        $this->call([
            DemoAccountSeeder::class,
            DemoOpeningStockSeeder::class,
        ]);
    }
}
