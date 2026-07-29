<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_batch_id',
        'supplier_id',
        'transfer_type',
        'beneficiary_bank_name',
        'supplier_name',
        'supplier_reference',
        'beneficiary_bank_account',
        'supplier_address',
        'email',
        'purpose_of_payment',
        'beneficiary_bank_country',
        'bic_code',
        'us_routing_no',
        'uk_sort_code',
        'future1',
        'bank_charges',
        'purpose_code',
        'country_purpose_code',
        'future2',
        'payment_no',
        'future3',
        'address_country',
        'state',
        'city',
        'payment_fingerprint',
        'amount',
        'currency',
        'value_date',
        'country_code',
        'reason_code',
        'invoice_number',
        'payment_reference',
        'beneficiary_address',
        'remittance_details',
        'status',
        'validation_errors',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'value_date' => 'date',
            'validation_errors' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
