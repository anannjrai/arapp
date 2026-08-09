<?php

namespace App\Services;

use App\Models\BankCountry;
use App\Models\CountryReasonCode;
use App\Models\MasterField;
use App\Models\PaymentBatch;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class CsvPaymentImportService
{
    /**
     * @var array<string, string|null>
     */
    private array $capitalCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $purposeCodeCache = [];

    /**
     * @var array<int, string>
     */
    private array $transactionFields = [
        'transfer_type',
        'beneficiary_bank_name',
        'supplier_name',
        'amount',
        'currency',
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
    ];

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    /**
     * @return array<int, string>
     */
    public function transactionFields(): array
    {
        return $this->transactionFields;
    }

    public function editableFields(): mixed
    {
        return MasterField::query()
            ->where('is_active', true)
            ->whereIn('key', $this->transactionFields)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesFromTransaction(PaymentTransaction $transaction): array
    {
        $values = [];

        foreach ($this->transactionFields as $field) {
            $values[$field] = $transaction->{$field};
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function applyManualCorrection(PaymentTransaction $transaction, array $input): PaymentTransaction
    {
        if ($transaction->status === 'exported') {
            throw new RuntimeException('Exported transactions cannot be changed.');
        }

        $fields = MasterField::query()->where('is_active', true)->get()->keyBy('key');
        $payload = $this->payloadFromManualInput($input, $fields);
        $normalized = $this->normalizePayload($payload);
        $errors = $this->validatePayload($normalized, $fields);
        $fingerprint = $this->paymentFingerprint($normalized);
        $normalized['payment_fingerprint'] = $fingerprint;

        if ($this->duplicatePaymentNumberExists($normalized['payment_no'] ?? null, $transaction->id)) {
            $errors[] = 'This payment number was already imported before.';
        }

        $errors = array_values(array_unique($errors));
        $supplier = $this->upsertSupplier($normalized, $payload);

        $transaction->update([
            'supplier_id' => $supplier?->id,
            'transfer_type' => $normalized['transfer_type'] ?: 'TT',
            'beneficiary_bank_name' => $normalized['beneficiary_bank_name'] ?: null,
            'supplier_name' => $normalized['supplier_name'] ?: 'Missing supplier',
            'supplier_reference' => $normalized['supplier_name'] ?: null,
            'beneficiary_bank_account' => $normalized['beneficiary_bank_account'] ?: null,
            'supplier_address' => $normalized['supplier_address'] ?: null,
            'email' => $normalized['email'] ?: null,
            'purpose_of_payment' => $normalized['purpose_of_payment'] ?: null,
            'beneficiary_bank_country' => $normalized['beneficiary_bank_country'] ?: null,
            'bic_code' => $normalized['bic_code'] ?: null,
            'us_routing_no' => $normalized['us_routing_no'] ?: null,
            'uk_sort_code' => $normalized['uk_sort_code'] ?: null,
            'future1' => $normalized['future1'] ?: null,
            'bank_charges' => $normalized['bank_charges'] ?: null,
            'purpose_code' => $normalized['purpose_code'] ?: null,
            'country_purpose_code' => $normalized['country_purpose_code'] ?: null,
            'future2' => $normalized['future2'] ?: null,
            'payment_no' => $normalized['payment_no'] ?: null,
            'future3' => $normalized['future3'] ?: null,
            'address_country' => $normalized['address_country'] ?: null,
            'state' => $normalized['state'] ?: null,
            'city' => $normalized['city'] ?: null,
            'payment_fingerprint' => $normalized['payment_fingerprint'],
            'amount' => (float) ($normalized['amount'] ?? 0),
            'currency' => $normalized['currency'] ?: 'AED',
            'value_date' => $normalized['value_date'] ?: null,
            'country_code' => $normalized['country_code'] ?: null,
            'reason_code' => $normalized['reason_code'] ?: null,
            'invoice_number' => $normalized['payment_no'] ?: null,
            'payment_reference' => $normalized['payment_reference'] ?: null,
            'beneficiary_address' => $normalized['beneficiary_address'] ?: null,
            'remittance_details' => $normalized['remittance_details'] ?: null,
            'status' => $errors === [] ? 'pending' : 'invalid',
            'validation_errors' => $errors ?: null,
            'raw_payload' => $payload,
        ]);

        $this->refreshBatchSummary($transaction->batch()->firstOrFail());

        return $transaction->refresh();
    }

    public function refreshBatchSummary(PaymentBatch $batch): PaymentBatch
    {
        $transactions = $batch->transactions()->get();
        $invalidCount = $transactions->where('status', 'invalid')->count();
        $verifiedCount = $transactions->where('status', 'verified')->count();
        $pendingCount = $transactions->where('status', 'pending')->count();
        $exportedCount = $transactions->where('status', 'exported')->count();

        $status = match (true) {
            $invalidCount > 0 => 'needs_review',
            $transactions->count() > 0 && $exportedCount === $transactions->count() => 'exported',
            $verifiedCount > 0 && $pendingCount === 0 => 'verified',
            default => 'draft',
        };

        $batch->update([
            'status' => $status,
            'row_count' => $transactions->count(),
            'invalid_count' => $invalidCount,
            'total_amount' => $transactions->sum(fn (PaymentTransaction $transaction) => (float) $transaction->amount),
            'currency' => $transactions->firstWhere('currency', '!=', null)?->currency,
            'reviewed_by' => in_array($status, ['draft', 'needs_review'], true) ? null : $batch->reviewed_by,
            'reviewed_at' => in_array($status, ['draft', 'needs_review'], true) ? null : $batch->reviewed_at,
        ]);

        return $batch->refresh();
    }

    public function import(UploadedFile $file, User $user, ?string $notes = null): PaymentBatch
    {
        $delimiter = $this->delimiterForFile($file);
        $reader = new SplFileObject($file->getRealPath());
        $reader->setCsvControl($delimiter);
        $reader->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = $reader->fgetcsv();
        if (! is_array($headers) || $headers === [null]) {
            throw new RuntimeException('The uploaded payment file does not contain a header row.');
        }

        if ($this->isPositionHeaderRow($headers)) {
            $headers = $reader->fgetcsv();
            if (! is_array($headers) || $headers === [null]) {
                throw new RuntimeException('The uploaded payment file does not contain field headers after the position row.');
            }
        }

        $fieldMap = $this->buildHeaderMap($headers);
        $fields = MasterField::query()->where('is_active', true)->get()->keyBy('key');
        $firstDataRow = null;

        if ($fieldMap === [] && count($headers) >= 21) {
            $fieldCount = min(count($headers), count($this->transactionFields));
            $fieldMap = array_combine(range(0, $fieldCount - 1), array_slice($this->transactionFields, 0, $fieldCount));
            $firstDataRow = $headers;
        }

        return DB::transaction(function () use ($reader, $file, $user, $notes, $fieldMap, $fields, $firstDataRow) {
            $batch = PaymentBatch::create([
                'batch_reference' => 'PAY-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'status' => 'draft',
                'source_file_name' => $file->getClientOriginalName(),
                'uploaded_by' => $user->id,
                'notes' => $notes,
            ]);

            $rowCount = 0;
            $invalidCount = 0;
            $totalAmount = 0.0;
            $currency = null;
            $seenPaymentNumbers = [];

            while ($firstDataRow !== null || ! $reader->eof()) {
                $row = $firstDataRow ?? $reader->fgetcsv();
                $firstDataRow = null;

                if (! is_array($row) || $row === [null] || $this->rowIsBlank($row)) {
                    continue;
                }

                $payload = $this->payloadFromRow($row, $fieldMap, $fields);
                $normalized = $this->normalizePayload($payload);
                $errors = $this->validatePayload($normalized, $fields);
                $fingerprint = $this->paymentFingerprint($normalized);
                $normalized['payment_fingerprint'] = $fingerprint;
                $supplier = $this->upsertSupplier($normalized, $payload);
                $paymentNumberKey = $this->paymentNumberKey($normalized['payment_no'] ?? null);

                if ($paymentNumberKey !== '') {
                    if (isset($seenPaymentNumbers[$paymentNumberKey])) {
                        $errors[] = 'Duplicate payment number in this upload.';
                    }

                    if ($this->duplicatePaymentNumberExists($normalized['payment_no'] ?? null)) {
                        $errors[] = 'This payment number was already imported before.';
                    }

                    $seenPaymentNumbers[$paymentNumberKey] = true;
                }

                $rowCount++;
                $amount = (float) ($normalized['amount'] ?? 0);
                $totalAmount += $amount;
                $currency ??= $normalized['currency'] ?? null;

                if ($errors !== []) {
                    $invalidCount++;
                }

                PaymentTransaction::create([
                    'payment_batch_id' => $batch->id,
                    'supplier_id' => $supplier?->id,
                    'transfer_type' => $normalized['transfer_type'] ?: 'TT',
                    'beneficiary_bank_name' => $normalized['beneficiary_bank_name'] ?: null,
                    'supplier_name' => $normalized['supplier_name'] ?: 'Missing supplier',
                    'supplier_reference' => $normalized['supplier_name'] ?: null,
                    'beneficiary_bank_account' => $normalized['beneficiary_bank_account'] ?: null,
                    'supplier_address' => $normalized['supplier_address'] ?: null,
                    'email' => $normalized['email'] ?: null,
                    'purpose_of_payment' => $normalized['purpose_of_payment'] ?: null,
                    'beneficiary_bank_country' => $normalized['beneficiary_bank_country'] ?: null,
                    'bic_code' => $normalized['bic_code'] ?: null,
                    'us_routing_no' => $normalized['us_routing_no'] ?: null,
                    'uk_sort_code' => $normalized['uk_sort_code'] ?: null,
                    'future1' => $normalized['future1'] ?: null,
                    'bank_charges' => $normalized['bank_charges'] ?: null,
                    'purpose_code' => $normalized['purpose_code'] ?: null,
                    'country_purpose_code' => $normalized['country_purpose_code'] ?: null,
                    'future2' => $normalized['future2'] ?: null,
                    'payment_no' => $normalized['payment_no'] ?: null,
                    'future3' => $normalized['future3'] ?: null,
                    'address_country' => $normalized['address_country'] ?: null,
                    'state' => $normalized['state'] ?: null,
                    'city' => $normalized['city'] ?: null,
                    'payment_fingerprint' => $normalized['payment_fingerprint'],
                    'amount' => $amount,
                    'currency' => $normalized['currency'] ?: 'AED',
                    'value_date' => $normalized['value_date'] ?: null,
                    'country_code' => $normalized['country_code'] ?: null,
                    'reason_code' => $normalized['reason_code'] ?: null,
                    'invoice_number' => $normalized['payment_no'] ?: null,
                    'payment_reference' => $normalized['payment_reference'] ?: null,
                    'beneficiary_address' => $normalized['beneficiary_address'] ?: null,
                    'remittance_details' => $normalized['remittance_details'] ?: null,
                    'status' => $errors === [] ? 'pending' : 'invalid',
                    'validation_errors' => $errors ?: null,
                    'raw_payload' => $payload,
                ]);
            }

            if ($rowCount === 0) {
                throw new RuntimeException('No payment rows were found in the uploaded payment file.');
            }

            $batch->update([
                'status' => $invalidCount > 0 ? 'needs_review' : 'draft',
                'row_count' => $rowCount,
                'invalid_count' => $invalidCount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
            ]);

            $this->auditLogger->record('import', $batch, [
                'file_name' => $file->getClientOriginalName(),
                'rows' => $rowCount,
                'invalid_rows' => $invalidCount,
            ], $user);

            return $batch;
        });
    }

    /**
     * @return array<int, string>
     */
    public function templateHeaders(): array
    {
        return MasterField::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label')
            ->all();
    }

    /**
     * @param array<int, string|null> $headers
     * @return array<int, string>
     */
    private function buildHeaderMap(array $headers): array
    {
        $aliases = [];
        foreach (MasterField::query()->where('is_active', true)->get() as $field) {
            $aliases[$this->canonical($field->key)] = $field->key;
            $aliases[$this->canonical($field->label)] = $field->key;

            foreach ($field->import_aliases ?? [] as $alias) {
                $aliases[$this->canonical($alias)] = $field->key;
            }
        }

        $map = [];
        foreach ($headers as $index => $header) {
            $key = $aliases[$this->canonical((string) $header)] ?? null;
            if ($key !== null && in_array($key, $this->transactionFields, true)) {
                $map[$index] = $key;
            }
        }

        return $map;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<int, string> $fieldMap
     * @return array<string, string|null>
     */
    private function payloadFromRow(array $row, array $fieldMap, mixed $fields): array
    {
        $payload = [];
        foreach ($this->transactionFields as $key) {
            $payload[$key] = $fields->get($key)?->default_value;
        }

        foreach ($fieldMap as $index => $key) {
            $payload[$key] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string|null>
     */
    private function payloadFromManualInput(array $input, mixed $fields): array
    {
        $payload = [];

        foreach ($this->transactionFields as $key) {
            $value = $input[$key] ?? $fields->get($key)?->default_value;
            $payload[$key] = is_string($value) ? trim($value) : ($value !== null ? (string) $value : null);
        }

        return $payload;
    }

    /**
     * @param array<string, string|null> $payload
     * @return array<string, string|null>
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = $payload;
        $normalized['transfer_type'] = Str::upper(trim((string) ($payload['transfer_type'] ?: 'TT')));
        $normalized['amount'] = $this->normalizeAmount($payload['amount'] ?? null);
        $normalized['currency'] = Str::upper(trim((string) ($payload['currency'] ?: 'USD')));
        $normalized['beneficiary_bank_account'] = $this->normalizeBankAccount($payload['beneficiary_bank_account'] ?? null);
        $normalized['beneficiary_bank_country'] = trim((string) ($payload['beneficiary_bank_country'] ?? ''));
        $normalized['purpose_code'] = $this->countryPurposeCodeFor($normalized['beneficiary_bank_country'])
            ?? Str::upper(trim((string) ($payload['purpose_code'] ?: 'PRS')));
        $normalized['country_purpose_code'] = Str::upper(trim((string) ($payload['country_purpose_code'] ?? '')));
        $normalized['country_code'] = $normalized['beneficiary_bank_country'];
        $capital = $this->capitalForCountry($normalized['beneficiary_bank_country']);
        if ($capital !== null) {
            $normalized['state'] = $capital;
            $normalized['city'] = $capital;
        }

        if (! $this->isUnitedStates($normalized['beneficiary_bank_country'])) {
            $normalized['us_routing_no'] = '';
        }

        if (! $this->isUnitedKingdom($normalized['beneficiary_bank_country'])) {
            $normalized['uk_sort_code'] = '';
        }

        $normalized['reason_code'] = $normalized['purpose_code'];
        $normalized['payment_reference'] = trim((string) ($payload['payment_no'] ?? ''));
        $normalized['beneficiary_address'] = trim((string) ($payload['supplier_address'] ?? ''));
        $normalized['remittance_details'] = trim((string) ($payload['purpose_of_payment'] ?? ''));
        $normalized['value_date'] = null;

        foreach ($normalized as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = trim($value);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string|null> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload, mixed $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (! in_array($field->key, $this->transactionFields, true)) {
                continue;
            }

            if ($field->is_required && blank($payload[$field->key] ?? null)) {
                $errors[] = "{$field->label} is required.";
            }
        }

        if (($payload['amount'] ?? null) === null || (float) $payload['amount'] <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        }

        if (! blank($payload['currency'] ?? null) && strlen((string) $payload['currency']) !== 3) {
            $errors[] = 'Currency must be a three-letter ISO code.';
        }

        if (! blank($payload['beneficiary_bank_country'] ?? null) && $this->capitalForCountry($payload['beneficiary_bank_country']) === null) {
            $errors[] = 'Beneficiary bank country is not active in the bank country master.';
        }

        if (! blank($payload['country_purpose_code'] ?? null)) {
            $exists = CountryReasonCode::query()
                ->where('country_code', Str::upper($payload['beneficiary_bank_country'] ?? ''))
                ->where('reason_code', Str::upper($payload['country_purpose_code']))
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $errors[] = 'Country reason code is not active in the maintained list.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string|null> $payload
     * @param array<string, string|null> $rawPayload
     */
    private function upsertSupplier(array $payload, array $rawPayload): ?Supplier
    {
        $fingerprint = $this->supplierFingerprint($payload);
        if ($fingerprint === null) {
            return null;
        }

        return Supplier::updateOrCreate(
            ['supplier_fingerprint' => $fingerprint],
            [
                'supplier_name' => $payload['supplier_name'],
                'beneficiary_bank_name' => $payload['beneficiary_bank_name'] ?: null,
                'beneficiary_bank_account' => $payload['beneficiary_bank_account'] ?: null,
                'supplier_address' => $payload['supplier_address'] ?: null,
                'email' => $payload['email'] ?: null,
                'purpose_of_payment' => $payload['purpose_of_payment'] ?: null,
                'beneficiary_bank_country' => $payload['beneficiary_bank_country'] ?: null,
                'bic_code' => $payload['bic_code'] ?: null,
                'us_routing_no' => $payload['us_routing_no'] ?: null,
                'uk_sort_code' => $payload['uk_sort_code'] ?: null,
                'bank_charges' => $payload['bank_charges'] ?: null,
                'purpose_code' => $payload['purpose_code'] ?: null,
                'country_purpose_code' => $payload['country_purpose_code'] ?: null,
                'address_country' => $payload['address_country'] ?: null,
                'state' => $payload['state'] ?: null,
                'city' => $payload['city'] ?: null,
                'last_payload' => $rawPayload,
                'last_imported_at' => now(),
                'is_active' => true,
            ],
        );
    }

    /**
     * @param array<string, string|null> $payload
     */
    private function supplierFingerprint(array $payload): ?string
    {
        if (blank($payload['supplier_name'] ?? null)) {
            return null;
        }

        $parts = [
            $this->fingerprintPart($payload['supplier_name']),
            $this->fingerprintPart($payload['beneficiary_bank_account'] ?? ''),
            $this->fingerprintPart($payload['bic_code'] ?? ''),
            $this->fingerprintPart($payload['beneficiary_bank_country'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<string, string|null> $payload
     */
    private function paymentFingerprint(array $payload): ?string
    {
        $key = $this->paymentNumberKey($payload['payment_no'] ?? null);

        if ($key === '') {
            return null;
        }

        return hash('sha256', $key);
    }

    private function fingerprintPart(?string $value): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');
    }

    private function normalizeAmount(?string $amount): ?string
    {
        if (blank($amount)) {
            return null;
        }

        $clean = str_replace([',', ' '], '', trim($amount));

        return is_numeric($clean) ? number_format((float) $clean, 2, '.', '') : null;
    }

    private function normalizeBankAccount(?string $account): ?string
    {
        $value = trim((string) $account);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[+-]?\d+(?:\.\d+)?e[+-]?\d+$/i', $value) === 1) {
            return number_format((float) $value, 0, '', '');
        }

        if (preg_match('/^[+-]?\d+\.0+$/', $value) === 1) {
            return strstr($value, '.', true) ?: $value;
        }

        return $value;
    }

    private function countryPurposeCodeFor(?string $country): ?string
    {
        $country = Str::upper(trim((string) $country));
        if ($country === '') {
            return null;
        }

        if (array_key_exists($country, $this->purposeCodeCache)) {
            return $this->purposeCodeCache[$country];
        }

        return $this->purposeCodeCache[$country] = CountryReasonCode::query()
            ->where('country_code', $country)
            ->where('is_active', true)
            ->orderBy('reason_code')
            ->value('reason_code');
    }

    private function capitalForCountry(?string $country): ?string
    {
        $key = BankCountry::normalizeName($country);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->capitalCache)) {
            return $this->capitalCache[$key];
        }

        return $this->capitalCache[$key] = BankCountry::query()
            ->where('country_key', $key)
            ->where('is_active', true)
            ->value('capital');
    }

    private function duplicatePaymentNumberExists(?string $paymentNo, ?int $ignoreId = null): bool
    {
        $key = $this->paymentNumberKey($paymentNo);
        if ($key === '') {
            return false;
        }

        $fingerprint = hash('sha256', $key);

        return PaymentTransaction::query()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(function ($q) use ($fingerprint, $key) {
                $q->where('payment_fingerprint', $fingerprint)
                    ->orWhereRaw('UPPER(TRIM(payment_no)) = ?', [$key]);
            })
            ->exists();
    }

    private function paymentNumberKey(?string $paymentNo): string
    {
        return $this->fingerprintPart($paymentNo);
    }

    private function delimiterForFile(UploadedFile $file): string
    {
        return Str::lower($file->getClientOriginalExtension()) === 'txt' ? '|' : ',';
    }

    private function isUnitedStates(?string $country): bool
    {
        return in_array(BankCountry::normalizeName($country), ['us', 'usa', 'unitedstates', 'unitedstatesofamerica'], true);
    }

    private function isUnitedKingdom(?string $country): bool
    {
        return in_array(BankCountry::normalizeName($country), ['uk', 'gb', 'gbr', 'britain', 'greatbritain', 'unitedkingdom'], true);
    }

    private function normalizeDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        $value = trim($date);
        if (is_numeric($value) && (float) $value > 20000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'] as $format) {
            $parsed = Carbon::createFromFormat($format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->toDateString();
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (! blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function isPositionHeaderRow(array $row): bool
    {
        if (count($row) < 23) {
            return false;
        }

        foreach (range(1, 23) as $index) {
            if ((string) ($row[$index - 1] ?? '') !== (string) $index) {
                return false;
            }
        }

        return true;
    }

    private function canonical(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
    }
}
