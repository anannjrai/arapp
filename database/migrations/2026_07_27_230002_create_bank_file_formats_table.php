<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_file_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('delimiter', 5)->default(',');
            $table->string('extension', 10)->default('csv');
            $table->boolean('include_header')->default(true);
            $table->string('date_format')->default('Y-m-d');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_file_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_file_format_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('column_label');
            $table->string('source_field')->nullable();
            $table->string('static_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('max_length')->nullable();
            $table->string('padding_direction', 10)->default('none');
            $table->string('padding_character', 5)->default(' ');
            $table->string('format')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bank_file_format_id', 'position']);
            $table->index(['bank_file_format_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_file_columns');
        Schema::dropIfExists('bank_file_formats');
    }
};
