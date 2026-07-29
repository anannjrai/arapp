<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryReasonCode extends Model
{
    protected $fillable = [
        'country_code',
        'reason_code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getDisplayCodeAttribute(): string
    {
        return "{$this->country_code}-{$this->reason_code}";
    }
}
