<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 120);
            $table->string('reason_code', 20);
            $table->string('description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_code', 'reason_code']);
            $table->index(['country_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_reason_codes');
    }
};
