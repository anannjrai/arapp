<?php

namespace App\Services;

use App\Models\BankFileColumn;
use App\Models\BankFileFormat;
use App\Models\PaymentBatch;
use App\Models\PaymentExport;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BankPaymentExportService
{
    private const BANK_ACCOUNTS_BY_CURRENCY = [
        'USD' => '5503',
        'AED' => '5504',
        'EUR' => '5505',
    ];

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function previewColumns(BankFileFormat $format): mixed
    {
        return $format->activeColumns()->get();
    }

    /**
     * @return array<int, string>
     */
    public function previewRow(PaymentTransaction $transaction, BankFileFormat $format): array
    {
        return $this->buildRow($transaction, $this->previewColumns($format)->all(), $format);
    }

    public function export(PaymentBatch $batch, BankFileFormat $format, User $user): string
    {
        $batch->loadMissing('transactions');
        $columns = $format->activeColumns()->get();

        if ($columns->isEmpty()) {
            throw new RuntimeException('The selected bank file format has no active columns.');
        }

        if ($batch->transactions->isEmpty()) {
            throw new RuntimeException('The payment batch has no transactions to export.');
        }

        return DB::transaction(function () use ($batch, $format, $columns, $user) {
            $directory = storage_path('app/exports');
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = $this->uniqueFilename($batch, $format, $directory);
            $path = $directory.DIRECTORY_SEPARATOR.$filename;

            $transactionsQuery = $batch->transactions()->where('status', 'verified');
            $transactions = $transactionsQuery->orderBy('id')->get();

            if ($transactions->isEmpty()) {
                throw new RuntimeException('There are no verified transactions available for export.');
            }

            $lines = [];
            if ($format->include_header) {
                $lines[] = $this->buildLine($columns->pluck('column_label')->all(), $format);
            }

            foreach ($transactions as $transaction) {
                $lines[] = $this->buildLine($this->buildRow($transaction, $columns->all(), $format), $format);
            }

            file_put_contents($path, implode(PHP_EOL, $lines));
            $fileHash = hash_file('sha256', $path);

            $export = PaymentExport::create([
                'payment_batch_id' => $batch->id,
                'bank_file_format_id' => $format->id,
                'exported_by' => $user->id,
                'file_name' => $filename,
                'file_path' => $path,
                'file_hash' => $fileHash,
                'row_count' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
            ]);

            $batch->transactions()->whereIn('id', $transactions->pluck('id'))->update(['status' => 'exported']);

            $batch->update([
                'status' => 'exported',
                'exported_by' => $user->id,
                'exported_at' => now(),
            ]);

            $this->auditLogger->record('export', $batch, [
                'format' => $format->name,
                'file_name' => $filename,
                'file_hash' => $fileHash,
                'rows' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
            ], $user);

            return $export->file_path;
        });
    }

    private function uniqueFilename(PaymentBatch $batch, BankFileFormat $format, string $directory): string
    {
        $currency = Str::upper((string) ($batch->currency ?: 'USD'));
        $bankAccount = $this->bankAccountForCurrency($currency);
        $date = now()->format('Ymd');
        $prefix = 'MENDubai'.$currency.$bankAccount.$date;
        $extension = ltrim($format->extension ?: 'txt', '.');

        for ($sequence = 1; $sequence <= 99999999; $sequence++) {
            $fileName = $prefix.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
            if ($extension !== '') {
                $fileName .= '.'.$extension;
            }

            if (! PaymentExport::query()->where('file_name', $fileName)->exists() && ! file_exists($directory.DIRECTORY_SEPARATOR.$fileName)) {
                return $fileName;
            }
        }

        throw new RuntimeException('Could not generate a unique export file name.');
    }

    private function bankAccountForCurrency(string $currency): string
    {
        $account = self::BANK_ACCOUNTS_BY_CURRENCY[$currency] ?? null;

        if ($account === null) {
            throw new RuntimeException("No bank account number is maintained for {$currency} exports.");
        }

        return $account;
    }

    /**
     * @param array<int, string> $fields
     */
    private function buildLine(array $fields, BankFileFormat $format): string
    {
        $values = array_map(fn ($value) => $this->sanitizeValue($value, $format->delimiter), $fields);
        $line = implode($format->delimiter, $values);

        return $format->trailing_delimiter ? $line.$format->delimiter : $line;
    }

    /**
     * @param array<int, BankFileColumn> $columns
     * @return array<int, string>
     */
    private function buildRow(PaymentTransaction $transaction, array $columns, BankFileFormat $format): array
    {
        return array_map(function (BankFileColumn $column) use ($transaction, $format) {
            $value = $column->static_value;

            if (! blank($column->source_field)) {
                $value = $transaction->{$column->source_field};
            }

            if ($column->source_field === 'amount') {
                $value = rtrim(rtrim(number_format((float) $value, $format->decimal_places, '.', ''), '0'), '.');
            }

            if ($column->source_field === 'value_date' && $transaction->value_date) {
                $value = $transaction->value_date->format($format->date_format);
            }

            $value = (string) ($value ?? '');

            if ($column->max_length !== null && $column->max_length > 0) {
                $value = Str::limit($value, $column->max_length, '');
            }

            if ($column->padding_direction !== 'none' && $column->max_length !== null && $column->max_length > 0) {
                $pad = $column->padding_character !== '' ? $column->padding_character[0] : ' ';
                $type = $column->padding_direction === 'left' ? STR_PAD_LEFT : STR_PAD_RIGHT;
                $value = str_pad($value, $column->max_length, $pad, $type);
            }

            return $value;
        }, $columns);
    }

    private function sanitizeValue(mixed $value, string $delimiter): string
    {
        return str_replace(["\r", "\n", $delimiter], ' ', (string) ($value ?? ''));
    }
}
