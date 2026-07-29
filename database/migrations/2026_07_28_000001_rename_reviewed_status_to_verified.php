<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_batches')->where('status', 'reviewed')->update(['status' => 'verified']);
        DB::table('payment_transactions')->where('status', 'reviewed')->update(['status' => 'verified']);
    }

    public function down(): void
    {
        DB::table('payment_batches')->where('status', 'verified')->update(['status' => 'reviewed']);
        DB::table('payment_transactions')->where('status', 'verified')->update(['status' => 'reviewed']);
    }
};
