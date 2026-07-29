<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'supplier_fingerprint',
        'supplier_name',
        'beneficiary_bank_name',
        'beneficiary_bank_account',
        'supplier_address',
        'email',
        'purpose_of_payment',
        'beneficiary_bank_country',
        'bic_code',
        'us_routing_no',
        'uk_sort_code',
        'bank_charges',
        'purpose_code',
        'country_purpose_code',
        'address_country',
        'state',
        'city',
        'last_payload',
        'last_imported_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_payload' => 'array',
            'last_imported_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
