<?php

use App\Models\BankCountry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('data/bank_countries.csv');
        if (! is_file($path)) {
            return;
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->fgetcsv();

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! is_array($row) || $row === [null] || blank($row[0] ?? null)) {
                continue;
            }

            $countryName = trim((string) $row[0]);
            $capital = trim((string) ($row[1] ?? ''));

            DB::table('bank_countries')->updateOrInsert(
                ['country_key' => BankCountry::normalizeName($countryName)],
                [
                    'country_name' => $countryName,
                    'capital' => $capital,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        //
    }
};
