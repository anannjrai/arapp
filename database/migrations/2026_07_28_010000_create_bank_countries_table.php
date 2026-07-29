<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_countries', function (Blueprint $table) {
            $table->id();
            $table->string('country_name', 120);
            $table->string('country_key', 120)->unique();
            $table->string('capital', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_countries');
    }
};
