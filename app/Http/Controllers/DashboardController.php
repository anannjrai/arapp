<?php

namespace App\Http\Controllers;

use App\Models\CountryReasonCode;
use App\Models\PaymentBatch;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = PaymentTransaction::query()->with('batch.uploadedBy');
        $this->applyFilters($query, $request);

        $summaryQuery = clone $query;

        return view('dashboard', [
            'summary' => [
                'batches' => (clone $summaryQuery)->distinct('payment_batch_id')->count('payment_batch_id'),
                'transactions' => (clone $summaryQuery)->count(),
                'amount' => (clone $summaryQuery)->sum('amount'),
                'invalid' => (clone $summaryQuery)->where('status', 'invalid')->count(),
                'exported_batches' => PaymentBatch::query()->where('status', 'exported')->count(),
            ],
            'transactions' => (clone $query)->latest()->paginate(25)->withQueryString(),
            'recentBatches' => PaymentBatch::query()->with('uploadedBy')->latest()->limit(8)->get(),
            'reasonCodes' => CountryReasonCode::query()->where('is_active', true)->orderBy('country_code')->orderBy('reason_code')->get(),
            'currencies' => PaymentTransaction::query()->distinct()->orderBy('currency')->pluck('currency')->filter(),
            'filters' => $request->all(),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('supplier_name'), fn (Builder $q) => $q->where('supplier_name', 'like', '%'.$request->string('supplier_name')->toString().'%'))
            ->when($request->filled('payment_reference'), fn (Builder $q) => $q->where('payment_reference', 'like', '%'.$request->string('payment_reference')->toString().'%'))
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', 'like', '%'.$request->string('country_code')->toString().'%'))
            ->when($request->filled('reason_code'), fn (Builder $q) => $q->where('reason_code', $request->string('reason_code')->upper()->toString()))
            ->when($request->filled('currency'), fn (Builder $q) => $q->where('currency', $request->string('currency')->upper()->toString()))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('amount_min'), fn (Builder $q) => $q->where('amount', '>=', $request->input('amount_min')))
            ->when($request->filled('amount_max'), fn (Builder $q) => $q->where('amount', '<=', $request->input('amount_max')))
            ->when($request->filled('batch_reference'), function (Builder $q) use ($request) {
                $q->whereHas('batch', fn (Builder $batch) => $batch->where('batch_reference', 'like', '%'.$request->string('batch_reference')->toString().'%'));
            })
            ->when($request->filled('batch_status'), function (Builder $q) use ($request) {
                $q->whereHas('batch', fn (Builder $batch) => $batch->where('status', $request->string('batch_status')->toString()));
            });
    }
}
