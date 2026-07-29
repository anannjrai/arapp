<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_file_formats', function (Blueprint $table) {
            $table->string('filename_pattern')->default('MENDubai{currency}{batch}{datetime}{random}');
            $table->boolean('trailing_delimiter')->default(true);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('transfer_type')->nullable()->after('payment_batch_id');
            $table->string('supplier_address')->nullable()->after('beneficiary_bank_account');
            $table->text('email')->nullable()->after('supplier_address');
            $table->text('purpose_of_payment')->nullable()->after('email');
            $table->string('beneficiary_bank_country')->nullable()->after('purpose_of_payment');
            $table->string('bic_code')->nullable()->after('beneficiary_bank_country');
            $table->string('us_routing_no')->nullable()->after('bic_code');
            $table->string('uk_sort_code')->nullable()->after('us_routing_no');
            $table->string('future1')->nullable()->after('uk_sort_code');
            $table->string('bank_charges')->nullable()->after('future1');
            $table->string('purpose_code')->nullable()->after('bank_charges');
            $table->string('country_purpose_code')->nullable()->after('purpose_code');
            $table->string('future2')->nullable()->after('country_purpose_code');
            $table->string('payment_no')->nullable()->after('future2');
            $table->string('future3')->nullable()->after('payment_no');
            $table->string('address_country')->nullable()->after('future3');
            $table->string('state')->nullable()->after('address_country');
            $table->string('city')->nullable()->after('state');
            $table->string('payment_fingerprint', 64)->nullable()->after('city');

            $table->index('payment_no');
            $table->index('payment_fingerprint');
        });

        Schema::create('payment_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_file_format_id')->constrained()->restrictOnDelete();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name')->unique();
            $table->string('file_path');
            $table->string('file_hash', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('exported_by');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_exports');

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_no']);
            $table->dropIndex(['payment_fingerprint']);
            $table->dropColumn([
                'transfer_type',
                'supplier_address',
                'email',
                'purpose_of_payment',
                'beneficiary_bank_country',
                'bic_code',
                'us_routing_no',
                'uk_sort_code',
                'future1',
                'bank_charges',
                'purpose_code',
                'country_purpose_code',
                'future2',
                'payment_no',
                'future3',
                'address_country',
                'state',
                'city',
                'payment_fingerprint',
            ]);
        });

        Schema::table('bank_file_formats', function (Blueprint $table) {
            $table->dropColumn(['filename_pattern', 'trailing_delimiter']);
        });
    }
};
