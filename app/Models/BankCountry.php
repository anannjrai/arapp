<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCountry extends Model
{
    protected $fillable = [
        'country_name',
        'country_key',
        'capital',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeName(?string $country): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $country))) ?? '';
    }
}
