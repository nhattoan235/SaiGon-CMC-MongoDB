<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_bin', 6);
            $table->string('bank_name', 100);
            $table->string('account_no', 19);
            $table->string('account_name', 100);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('bank_account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 20);
            $table->json('before_data')->nullable();
            $table->json('after_data');
            $table->timestamp('created_at');
        });
        Schema::create('vietqr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('debt_id')->constrained('debts');
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('bank_bin', 6);
            $table->string('bank_name', 100);
            $table->string('account_no', 19);
            $table->string('account_name', 100);
            $table->decimal('amount', 18, 2);
            $table->string('transfer_content', 25)->unique();
            $table->string('status', 20);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('claimed_at')->nullable();
            $table->string('customer_reference', 100)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->string('bank_reference', 100)->nullable()->unique();
            $table->string('rejected_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['debt_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vietqr_requests');
        Schema::dropIfExists('bank_account_events');
        Schema::dropIfExists('bank_accounts');
    }
};
