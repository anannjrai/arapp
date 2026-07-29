<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    protected $fillable = [
        'batch_reference',
        'status',
        'source_file_name',
        'uploaded_by',
        'reviewed_by',
        'exported_by',
        'row_count',
        'invalid_count',
        'total_amount',
        'currency',
        'notes',
        'reviewed_at',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'exported_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'row_count' => 'integer',
            'invalid_count' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(PaymentExport::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public function isReviewable(): bool
    {
        return $this->invalid_count === 0 && in_array($this->status, ['draft', 'needs_review'], true);
    }

    public function isExportable(): bool
    {
        return $this->status === 'verified';
    }
}
