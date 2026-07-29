<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->string('role')->default('viewer')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        DB::table('users')->orderBy('id')->get()->each(function (object $user): void {
            $base = Str::of($user->email)
                ->before('@')
                ->lower()
                ->replaceMatches('/[^a-z0-9_]+/', '_')
                ->trim('_')
                ->value() ?: 'user_'.$user->id;

            $username = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $base.'_'.$suffix;
                $suffix++;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'username' => $username,
                    'role' => $user->email === 'admin@example.com' ? 'admin' : 'viewer',
                    'is_active' => true,
                ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'is_active']);
        });
    }
};
