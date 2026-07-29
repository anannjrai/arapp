<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $query = Supplier::query();
        $this->applyFilters($query, $request);

        $suppliers = (clone $query)
            ->withCount(['transactions' => fn (Builder $transaction) => $this->applyTransactionFilters($transaction, $request)])
            ->withSum(['transactions' => fn (Builder $transaction) => $this->applyTransactionFilters($transaction, $request)], 'amount')
            ->orderBy('supplier_name')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => $request->all(),
            'filterOptions' => $this->filterOptions(),
            'summary' => $this->summary(clone $query, $request),
        ]);
    }

    public function show(Supplier $supplier): View
    {
        $supplier->loadCount('transactions');

        return view('suppliers.show', [
            'supplier' => $supplier,
            'transactions' => $supplier->transactions()
                ->with('batch')
                ->latest()
                ->paginate(30),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('supplier_name'), fn (Builder $q) => $q->where('supplier_name', 'like', '%'.$request->string('supplier_name')->toString().'%'))
            ->when($request->filled('bank_country'), fn (Builder $q) => $q->where('beneficiary_bank_country', 'like', '%'.$request->string('bank_country')->toString().'%'))
            ->when($request->filled('purpose_code'), fn (Builder $q) => $q->where('purpose_code', $request->string('purpose_code')->upper()->toString()))
            ->when($request->filled('account'), fn (Builder $q) => $q->where('beneficiary_bank_account', 'like', '%'.$request->string('account')->toString().'%'))
            ->when($this->hasTransactionFilters($request), function (Builder $q) use ($request) {
                $q->whereHas('transactions', fn (Builder $transaction) => $this->applyTransactionFilters($transaction, $request));
            });
    }

    private function applyTransactionFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('payment_reference'), function (Builder $q) use ($request) {
            $reference = $request->string('payment_reference')->toString();

            $q->where(function (Builder $match) use ($reference) {
                $match
                    ->where('payment_reference', 'like', '%'.$reference.'%')
                    ->orWhere('payment_no', 'like', '%'.$reference.'%');
            });
        });

        match ($this->dateMode($request)) {
            'date' => $query->when($request->filled('date'), fn (Builder $q) => $q->whereDate('created_at', $request->date('date'))),
            'period' => $query
                ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
                ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to'))),
            'month' => $query->when($this->monthParts($request) !== null, function (Builder $q) use ($request) {
                [$year, $month] = $this->monthParts($request);

                $q->whereYear('created_at', $year)->whereMonth('created_at', $month);
            }),
            'year' => $query->when($request->filled('year'), fn (Builder $q) => $q->whereYear('created_at', (int) $request->input('year'))),
            default => null,
        };
    }

    /**
     * @return array<string, float|int>
     */
    private function summary(Builder $supplierQuery, Request $request): array
    {
        $transactionQuery = PaymentTransaction::query()
            ->whereIn('supplier_id', (clone $supplierQuery)->select('suppliers.id'));
        $this->applyTransactionFilters($transactionQuery, $request);

        return [
            'suppliers' => (clone $supplierQuery)->count(),
            'payments' => (clone $transactionQuery)->count(),
            'amount' => (float) (clone $transactionQuery)->sum('amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'supplierNames' => $this->distinctSupplierValues('supplier_name'),
            'bankCountries' => $this->distinctSupplierValues('beneficiary_bank_country'),
            'purposeCodes' => $this->distinctSupplierValues('purpose_code'),
            'accounts' => $this->distinctSupplierValues('beneficiary_bank_account'),
            'paymentReferences' => PaymentTransaction::query()
                ->where(fn (Builder $query) => $query->whereNotNull('payment_reference')->orWhereNotNull('payment_no'))
                ->get(['payment_reference', 'payment_no'])
                ->flatMap(fn (PaymentTransaction $transaction) => [$transaction->payment_reference, $transaction->payment_no])
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'years' => PaymentTransaction::query()
                ->latest('created_at')
                ->get(['created_at'])
                ->map(fn (PaymentTransaction $transaction) => $transaction->created_at?->format('Y'))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),
        ];
    }

    private function distinctSupplierValues(string $column): mixed
    {
        return Supplier::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    private function hasTransactionFilters(Request $request): bool
    {
        return $request->filled('payment_reference') || $this->dateMode($request) !== '';
    }

    private function dateMode(Request $request): string
    {
        $mode = $request->string('date_mode')->toString();
        if (in_array($mode, ['date', 'period', 'month', 'year'], true)) {
            return $mode;
        }

        return match (true) {
            $request->filled('date') => 'date',
            $request->filled('date_from') || $request->filled('date_to') => 'period',
            $request->filled('month') => 'month',
            $request->filled('year') => 'year',
            default => '',
        };
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function monthParts(Request $request): ?array
    {
        $month = $request->string('month')->toString();

        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
