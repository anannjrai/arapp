<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankFileFormat extends Model
{
    protected $fillable = [
        'name',
        'delimiter',
        'extension',
        'include_header',
        'date_format',
        'decimal_places',
        'filename_pattern',
        'trailing_delimiter',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'include_header' => 'boolean',
            'trailing_delimiter' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BankFileColumn::class)->orderBy('position');
    }

    public function activeColumns(): HasMany
    {
        return $this->columns()->where('is_active', true);
    }
}
