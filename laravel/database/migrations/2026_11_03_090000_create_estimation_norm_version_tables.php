<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_norm_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('building_type', 50);
            $table->unsignedInteger('version');
            $table->string('label', 100);
            $table->string('change_note', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['building_type', 'version']);
        });

        Schema::create('estimation_norm_version_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('version_id')->constrained('estimation_norm_versions')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_per_square_meter', 18, 4);
            $table->boolean('per_floor')->default(false);
            $table->string('rounding', 15)->default('whole');
            $table->string('source', 255);
            $table->timestamps();
            $table->unique(['version_id', 'product_id']);
        });

        Schema::create('estimation_norm_active', function (Blueprint $table): void {
            $table->id();
            $table->string('building_type', 50)->unique();
            $table->foreignId('version_id')->unique()->constrained('estimation_norm_versions')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
        });

        Schema::create('estimation_norm_activation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('building_type', 50);
            $table->foreignId('version_id')->constrained('estimation_norm_versions')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
            $table->index(['building_type', 'activated_at']);
            $table->index(['version_id', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_norm_activation_events');
        Schema::dropIfExists('estimation_norm_active');
        Schema::dropIfExists('estimation_norm_version_items');
        Schema::dropIfExists('estimation_norm_versions');
    }
};
