<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_fingerprint', 64)->unique();
            $table->string('supplier_name');
            $table->string('beneficiary_bank_name')->nullable();
            $table->string('beneficiary_bank_account')->nullable();
            $table->string('supplier_address')->nullable();
            $table->text('email')->nullable();
            $table->text('purpose_of_payment')->nullable();
            $table->string('beneficiary_bank_country')->nullable();
            $table->string('bic_code')->nullable();
            $table->string('us_routing_no')->nullable();
            $table->string('uk_sort_code')->nullable();
            $table->string('bank_charges')->nullable();
            $table->string('purpose_code')->nullable();
            $table->string('country_purpose_code')->nullable();
            $table->string('address_country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('supplier_name');
            $table->index(['beneficiary_bank_country', 'purpose_code']);
            $table->index('is_active');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('payment_batch_id')->constrained()->nullOnDelete();
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
