<?php

namespace Tests\Feature;

use App\Models\PaymentBatch;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_filters_include_dropdown_suggestions_and_payment_reference_search(): void
    {
        $this->seed();
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $batch = PaymentBatch::create([
            'batch_reference' => 'PAY-SUPPLIER-FILTERS',
            'status' => 'verified',
            'uploaded_by' => $admin->id,
            'row_count' => 2,
            'invalid_count' => 0,
            'total_amount' => 300,
            'currency' => 'USD',
        ]);

        $alpha = Supplier::create([
            'supplier_fingerprint' => 'alpha-fingerprint',
            'supplier_name' => 'Alpha Trading',
            'beneficiary_bank_name' => 'Alpha Bank',
            'beneficiary_bank_account' => 'ACC-ALPHA',
            'beneficiary_bank_country' => 'United Arab Emirates',
            'purpose_code' => 'PRS',
            'last_imported_at' => now(),
        ]);

        $beta = Supplier::create([
            'supplier_fingerprint' => 'beta-fingerprint',
            'supplier_name' => 'Beta Trading',
            'beneficiary_bank_name' => 'Beta Bank',
            'beneficiary_bank_account' => 'ACC-BETA',
            'beneficiary_bank_country' => 'Qatar',
            'purpose_code' => 'GDE',
            'last_imported_at' => now(),
        ]);

        $alphaTransaction = PaymentTransaction::create([
            'payment_batch_id' => $batch->id,
            'supplier_id' => $alpha->id,
            'supplier_name' => $alpha->supplier_name,
            'beneficiary_bank_name' => $alpha->beneficiary_bank_name,
            'beneficiary_bank_account' => $alpha->beneficiary_bank_account,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'verified',
            'payment_no' => 'REF-001',
            'payment_reference' => 'REF-001',
        ]);
        $alphaTransaction->forceFill([
            'created_at' => '2026-07-15 10:00:00',
            'updated_at' => '2026-07-15 10:00:00',
        ])->save();

        $betaTransaction = PaymentTransaction::create([
            'payment_batch_id' => $batch->id,
            'supplier_id' => $beta->id,
            'supplier_name' => $beta->supplier_name,
            'beneficiary_bank_name' => $beta->beneficiary_bank_name,
            'beneficiary_bank_account' => $beta->beneficiary_bank_account,
            'amount' => 200,
            'currency' => 'USD',
            'status' => 'verified',
            'payment_no' => 'REF-002',
            'payment_reference' => 'REF-002',
        ]);
        $betaTransaction->forceFill([
            'created_at' => '2026-08-05 10:00:00',
            'updated_at' => '2026-08-05 10:00:00',
        ])->save();

        $response = $this->actingAs($admin)->get(route('suppliers.index'));

        $response
            ->assertOk()
            ->assertSee('list="supplierNameOptions"', false)
            ->assertSee('list="paymentReferenceOptions"', false)
            ->assertSee('id="supplierDateMode"', false)
            ->assertSee('data-date-fields="period"', false)
            ->assertSee('REF-002');
        $this->assertSame(300.0, $response->viewData('summary')['amount']);

        $filtered = $this->actingAs($admin)->get(route('suppliers.index', [
            'payment_reference' => 'REF-002',
        ]));

        $filtered->assertOk();

        $this->assertSame(
            ['Beta Trading'],
            $filtered->viewData('suppliers')->getCollection()->pluck('supplier_name')->all(),
        );
        $this->assertSame(1, $filtered->viewData('summary')['suppliers']);
        $this->assertSame(1, $filtered->viewData('summary')['payments']);
        $this->assertSame(200.0, $filtered->viewData('summary')['amount']);

        $singleDate = $this->actingAs($admin)->get(route('suppliers.index', [
            'date_mode' => 'date',
            'date' => '2026-08-05',
        ]));
        $this->assertSame(['Beta Trading'], $singleDate->viewData('suppliers')->getCollection()->pluck('supplier_name')->all());
        $this->assertSame(200.0, $singleDate->viewData('summary')['amount']);

        $period = $this->actingAs($admin)->get(route('suppliers.index', [
            'date_mode' => 'period',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));
        $this->assertSame(['Alpha Trading'], $period->viewData('suppliers')->getCollection()->pluck('supplier_name')->all());
        $this->assertSame(100.0, $period->viewData('summary')['amount']);

        $month = $this->actingAs($admin)->get(route('suppliers.index', [
            'date_mode' => 'month',
            'month' => '2026-08',
        ]));
        $this->assertSame(['Beta Trading'], $month->viewData('suppliers')->getCollection()->pluck('supplier_name')->all());
        $this->assertSame(200.0, $month->viewData('summary')['amount']);

        $year = $this->actingAs($admin)->get(route('suppliers.index', [
            'date_mode' => 'year',
            'year' => '2026',
        ]));
        $this->assertSame(['Alpha Trading', 'Beta Trading'], $year->viewData('suppliers')->getCollection()->pluck('supplier_name')->all());
        $this->assertSame(300.0, $year->viewData('summary')['amount']);
    }
}
