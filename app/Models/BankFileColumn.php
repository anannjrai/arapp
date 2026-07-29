<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankFileColumn extends Model
{
    protected $fillable = [
        'bank_file_format_id',
        'position',
        'column_label',
        'source_field',
        'static_value',
        'is_required',
        'max_length',
        'padding_direction',
        'padding_character',
        'format',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'max_length' => 'integer',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(BankFileFormat::class, 'bank_file_format_id');
    }
}
