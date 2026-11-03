<?php

use App\Mongo\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(DB::connection('mongodb'));
    }

    public function down(): void
    {
        throw new RuntimeException('MongoDB schema rollback requires restoring a verified backup; documents are never dropped automatically.');
    }
};
