<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_reference')->unique();
            $table->string('status')->default('draft');
            $table->string('source_file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batches');
    }
};
