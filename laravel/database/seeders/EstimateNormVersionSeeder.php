<?php

namespace Database\Seeders;

use App\Services\EstimateNormWorkflow;
use Illuminate\Database\Seeder;

class EstimateNormVersionSeeder extends Seeder
{
    public function run(): void
    {
        app(EstimateNormWorkflow::class)->importLegacy(null);
    }
}
