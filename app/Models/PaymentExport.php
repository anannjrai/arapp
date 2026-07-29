<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentExport extends Model
{
    protected $fillable = [
        'payment_batch_id',
        'bank_file_format_id',
        'exported_by',
        'file_name',
        'file_path',
        'file_hash',
        'row_count',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(BankFileFormat::class, 'bank_file_format_id');
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
