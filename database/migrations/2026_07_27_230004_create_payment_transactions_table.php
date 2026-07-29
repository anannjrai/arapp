<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_reference')->nullable();
            $table->string('beneficiary_bank_account')->nullable();
            $table->string('beneficiary_bank_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->date('value_date')->nullable();
            $table->string('country_code', 120)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('beneficiary_address')->nullable();
            $table->text('remittance_details')->nullable();
            $table->string('status')->default('pending');
            $table->json('validation_errors')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'reason_code']);
            $table->index(['currency', 'value_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
