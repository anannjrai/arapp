<?php

namespace Tests\Feature;

use App\Models\BankFileFormat;
use App\Models\BankCountry;
use App\Models\PaymentBatch;
use App\Models\PaymentExport;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_batch_create_route_is_not_shadowed_by_show_route(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payment-batches.create'))
            ->assertOk()
            ->assertSee('Import Payments');
    }

    public function test_payment_csv_import_extracts_supplier_and_exports_bank_txt(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsv('supplier-payment.csv'),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $this->assertSame(1, $batch->row_count);
        $this->assertSame(0, $batch->invalid_count);
        $this->assertSame('draft', $batch->status);
        $this->assertSame(1, Supplier::query()->count());

        $this->actingAs($user)
            ->post(route('payment-batches.review', $batch))
            ->assertRedirect();

        $this->assertSame('verified', $batch->refresh()->status);
        $this->assertSame('verified', $batch->transactions()->firstOrFail()->status);

        $this->actingAs($user)
            ->post(route('payment-batches.export', $batch), [
                'bank_file_format_id' => BankFileFormat::query()->firstOrFail()->id,
            ])
            ->assertOk();

        $export = PaymentExport::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^MENDubaiUSD5503'.$export->created_at->format('Ymd').'[0-9]{8}\.txt$/', $export->file_name);
        $this->assertSame(
            'TT|ABU DHABI COMMERCIAL BANK|NORTHTELECOM LLC|43749.96|USD|AE650030000609372193001|Grosvenor Business Tower|3a3bfb2c.mbcsp.onmicrosoft.com@emea.teams.ms|Payment for goods or services for media broadcasting, production, events, etc.|United Arab Emirates|ADCBAEAA||||2|PRS|||123426||United Arab Emirates|Abu Dhabi|Abu Dhabi|',
            file_get_contents($export->file_path),
        );
    }

    public function test_get_export_url_redirects_back_to_batch_instead_of_404(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $batch = PaymentBatch::create([
            'batch_reference' => 'BATCH-GET-EXPORT',
            'status' => 'verified',
            'uploaded_by' => $user->id,
            'row_count' => 0,
            'invalid_count' => 0,
            'total_amount' => 0,
            'currency' => 'USD',
        ]);

        $this->actingAs($user)
            ->get(route('payment-batches.export.instructions', $batch))
            ->assertRedirect(route('payment-batches.show', $batch))
            ->assertSessionHasErrors('batch');
    }

    public function test_batch_show_displays_export_shaped_payment_preview(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsv('preview.csv'),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('payment-batches.show', $batch))
            ->assertOk()
            ->assertSee('Payment Output Preview')
            ->assertSee('23 columns')
            ->assertSee('Transfer type')
            ->assertSee('IBAN / Account No.')
            ->assertSee('Country purpose code')
            ->assertSee('Address / Country')
            ->assertSee('TT')
            ->assertSee('NORTHTELECOM LLC')
            ->assertSee('123426');
    }

    public function test_batch_payment_form_displays_records_one_by_one(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsvRows('payment-form.csv', ['123426', '123427']),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $transactions = $batch->transactions()->orderBy('id')->get();

        $this->actingAs($user)
            ->get(route('payment-batches.show', $batch))
            ->assertOk()
            ->assertSee(route('payment-transactions.form', [$batch, $transactions[0]]), false)
            ->assertDontSee(route('payment-transactions.edit', [$batch, $transactions[0]]), false)
            ->assertDontSee('action="'.route('payment-transactions.destroy', [$batch, $transactions[0]]).'"', false);

        $this->actingAs($user)
            ->get(route('payment-transactions.form', [$batch, $transactions[0]]))
            ->assertOk()
            ->assertSee('Payment Form')
            ->assertSee('1 / 2')
            ->assertSee('Payment No.')
            ->assertSee('123426')
            ->assertSee('Next Payment')
            ->assertSee(route('payment-transactions.form', [$batch, $transactions[1]]), false)
            ->assertDontSee(route('payment-transactions.edit', [$batch, $transactions[0]]), false)
            ->assertDontSee('123427');

        $this->actingAs($user)
            ->get(route('payment-transactions.form', [$batch, $transactions[1]]))
            ->assertOk()
            ->assertSee('2 / 2')
            ->assertSee('123427')
            ->assertSee('Previous Payment')
            ->assertSee(route('payment-transactions.form', [$batch, $transactions[0]]), false);
    }

    public function test_import_inserts_country_purpose_code_into_column_16_from_bank_country(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsvRows('jordan-payment.csv', ['123500'], 'Jordan'),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $transaction = $batch->transactions()->firstOrFail();

        $this->assertSame('Jordan', $transaction->beneficiary_bank_country);
        $this->assertSame('0807', $transaction->purpose_code);
        $this->assertNull($transaction->country_purpose_code);
        $this->assertSame('Amman', $transaction->state);
        $this->assertSame('Amman', $transaction->city);
        $this->assertNull($transaction->validation_errors);

        $this->actingAs($user)
            ->post(route('payment-batches.review', $batch))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('payment-batches.export', $batch), [
                'bank_file_format_id' => BankFileFormat::query()->firstOrFail()->id,
            ])
            ->assertOk();

        $export = PaymentExport::query()->firstOrFail();
        $this->assertStringContainsString('|2|0807|||123500||United Arab Emirates|Amman|Amman|', file_get_contents($export->file_path));
    }

    public function test_invalid_duplicate_transaction_can_be_corrected_then_verified(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)->post(route('payment-batches.store'), [
            'payment_file' => $this->paymentCsv('first.csv'),
        ]);

        $this->actingAs($user)->post(route('payment-batches.store'), [
            'payment_file' => $this->paymentCsv('second.csv'),
        ]);

        $batch = PaymentBatch::query()->latest('id')->firstOrFail();
        $transaction = $batch->transactions()->firstOrFail();
        $fields = $transaction->raw_payload;
        $fields['payment_no'] = '123427';

        $this->actingAs($user)
            ->patch(route('payment-transactions.update', [$batch, $transaction]), [
                'fields' => $fields,
            ])
            ->assertRedirect(route('payment-batches.show', $batch));

        $this->assertSame('pending', $transaction->refresh()->status);
        $this->assertNull($transaction->validation_errors);
        $this->assertSame(0, $batch->refresh()->invalid_count);
        $this->assertSame('draft', $batch->status);

        $this->actingAs($user)
            ->post(route('payment-batches.review', $batch))
            ->assertRedirect();

        $this->assertSame('verified', $batch->refresh()->status);
        $this->assertSame('verified', $transaction->refresh()->status);
    }

    public function test_export_always_includes_the_whole_verified_batch(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsvRows('selected.csv', ['123426', '123427']),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.review', $batch))
            ->assertRedirect();

        $transactions = $batch->transactions()->orderBy('id')->get();

        $this->actingAs($user)
            ->post(route('payment-batches.export', $batch), [
                'bank_file_format_id' => BankFileFormat::query()->firstOrFail()->id,
                'transaction_ids' => [$transactions[0]->id],
            ])
            ->assertOk();

        $this->assertSame('exported', $transactions[0]->refresh()->status);
        $this->assertSame('exported', $transactions[1]->refresh()->status);
        $this->assertSame('exported', $batch->refresh()->status);

        $export = PaymentExport::query()->firstOrFail();
        $this->assertSame(2, $export->row_count);
        $this->assertStringContainsString('123426', file_get_contents($export->file_path));
        $this->assertStringContainsString('123427', file_get_contents($export->file_path));
    }

    public function test_admin_can_delete_transaction_and_batch_summary_is_recalculated(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsvRows('cleanup.csv', ['123426', '123427']),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $transaction = $batch->transactions()->orderBy('id')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('payment-transactions.destroy', [$batch, $transaction]))
            ->assertRedirect(route('payment-batches.show', $batch));

        $this->assertDatabaseMissing('payment_transactions', ['id' => $transaction->id]);
        $this->assertSame(1, $batch->refresh()->row_count);
        $this->assertSame(0, $batch->invalid_count);
        $this->assertSame('draft', $batch->status);
        $this->assertSame('43749.96', (string) $batch->total_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delete_transaction']);
    }

    public function test_admin_can_delete_payment_batch_and_related_transactions(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsv('delete-batch.csv'),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $transaction = $batch->transactions()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('payment-batches.destroy', $batch))
            ->assertRedirect(route('payment-batches.index'));

        $this->assertDatabaseMissing('payment_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('payment_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delete_batch']);
    }

    public function test_duplicate_payment_records_are_rejected_on_import(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)->post(route('payment-batches.store'), [
            'payment_file' => $this->paymentCsv('first.csv'),
        ]);

        $this->actingAs($user)->post(route('payment-batches.store'), [
            'payment_file' => $this->paymentCsv('second.csv'),
        ]);

        $duplicate = PaymentBatch::query()->latest('id')->firstOrFail();
        $transaction = PaymentTransaction::query()->where('payment_batch_id', $duplicate->id)->firstOrFail();

        $this->assertSame('needs_review', $duplicate->status);
        $this->assertSame(1, $duplicate->invalid_count);
        $this->assertSame('invalid', $transaction->status);
        $this->assertContains('This payment number was already imported before.', $transaction->validation_errors);
    }

    public function test_headerless_twenty_one_column_import_derives_country_fields(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(BankCountry::query()->where('country_name', 'United States')->where('capital', 'Washington')->exists());

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->headerlessPaymentCsvRows('headerless.csv', [
                    ['payment_no' => '123600', 'country' => 'United States', 'us_routing_no' => '26009593', 'uk_sort_code' => '112233'],
                    ['payment_no' => '123601', 'country' => 'United Kingdom', 'us_routing_no' => '26009594', 'uk_sort_code' => '123456'],
                ]),
            ])
            ->assertRedirect();

        $batch = PaymentBatch::query()->firstOrFail();
        $this->assertSame(2, $batch->row_count);
        $this->assertSame(0, $batch->invalid_count);

        $transactions = $batch->transactions()->orderBy('payment_no')->get();
        $usPayment = $transactions[0];
        $ukPayment = $transactions[1];

        $this->assertSame('United States', $usPayment->beneficiary_bank_country);
        $this->assertSame('26009593', $usPayment->us_routing_no);
        $this->assertNull($usPayment->uk_sort_code);
        $this->assertSame('Washington', $usPayment->state);
        $this->assertSame('Washington', $usPayment->city);
        $this->assertSame('PRS', $usPayment->purpose_code);

        $this->assertSame('United Kingdom', $ukPayment->beneficiary_bank_country);
        $this->assertNull($ukPayment->us_routing_no);
        $this->assertSame('123456', $ukPayment->uk_sort_code);
        $this->assertSame('London', $ukPayment->state);
        $this->assertSame('London', $ukPayment->city);
        $this->assertSame('PRS', $ukPayment->purpose_code);
    }

    public function test_scientific_bank_account_imports_as_whole_number_text(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payment-batches.store'), [
                'payment_file' => $this->paymentCsvRows('scientific-account.csv', ['123700'], 'United States', '2.26008E+11'),
            ])
            ->assertRedirect();

        $transaction = PaymentTransaction::query()->firstOrFail();

        $this->assertSame('226008000000', $transaction->beneficiary_bank_account);
        $this->assertDatabaseHas('suppliers', [
            'beneficiary_bank_account' => '226008000000',
        ]);
    }

    private function paymentCsv(string $name): UploadedFile
    {
        return $this->paymentCsvRows($name, ['123426']);
    }

    /**
     * @param array<int, array{payment_no: string, country: string, us_routing_no?: string, uk_sort_code?: string}> $rows
     */
    private function headerlessPaymentCsvRows(string $name, array $rows): UploadedFile
    {
        $content = '';

        foreach ($rows as $row) {
            $content .= $this->csvLine([
                'TT',
                'ABU DHABI COMMERCIAL BANK',
                'NORTHTELECOM LLC',
                '43749.96',
                'USD',
                'AE650030000609372193001',
                'Grosvenor Business Tower',
                '3a3bfb2c.mbcsp.onmicrosoft.com@emea.teams.ms',
                'Payment for goods or services for media broadcasting, production, events, etc.',
                $row['country'],
                'ADCBAEAA',
                $row['us_routing_no'] ?? '',
                $row['uk_sort_code'] ?? '',
                '',
                '2',
                '',
                '',
                '',
                $row['payment_no'],
                '',
                'United Arab Emirates',
            ]);
        }

        return UploadedFile::fake()->createWithContent(
            $name,
            $content,
        );
    }

    /**
     * @param array<int, string> $paymentNumbers
     */
    private function paymentCsvRows(
        string $name,
        array $paymentNumbers,
        string $bankCountry = 'United Arab Emirates',
        string $bankAccount = 'AE650030000609372193001'
    ): UploadedFile {
        $headers = [
            'Transfer type',
            'Beneficiary bank name',
            'Supplier name',
            'Amount',
            'Currency',
            'IBAN / Account No.',
            'Supplier address',
            'Email',
            'Purpose of payment',
            'Beneficiary bank country',
            'BIC Code',
            'US Routing No.',
            'UK Sort code',
            'Future1',
            'Bank Charges',
            'Purpose code',
            'Country purpose code',
            'Future 2',
            'Payment No.',
            'Future 3',
            'Address / Country',
            'State',
            'City',
        ];

        $content = $this->csvLine($headers);

        foreach ($paymentNumbers as $paymentNumber) {
            $content .= $this->csvLine([
                'TT',
                'ABU DHABI COMMERCIAL BANK',
                'NORTHTELECOM LLC',
                '43749.96',
                'USD',
                $bankAccount,
                'Grosvenor Business Tower',
                '3a3bfb2c.mbcsp.onmicrosoft.com@emea.teams.ms',
                'Payment for goods or services for media broadcasting, production, events, etc.',
                $bankCountry,
                'ADCBAEAA',
                '',
                '',
                '',
                '2',
                'PRS',
                '',
                '',
                $paymentNumber,
                '',
                'United Arab Emirates',
                'Dubai',
                'Dubai',
            ]);
        }

        return UploadedFile::fake()->createWithContent(
            $name,
            $content,
        );
    }

    /**
     * @param array<int, string> $values
     */
    private function csvLine(array $values): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $values);
        rewind($stream);

        return stream_get_contents($stream);
    }
}
