<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterField extends Model
{
    protected $fillable = [
        'key',
        'label',
        'data_type',
        'is_required',
        'default_value',
        'options',
        'import_aliases',
        'help_text',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
            'import_aliases' => 'array',
        ];
    }
}
