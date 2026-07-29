<?php

namespace App\Http\Controllers;

use App\Models\BankFileFormat;
use App\Models\PaymentBatch;
use App\Models\PaymentTransaction;
use App\Services\AuditLogger;
use App\Services\BankPaymentExportService;
use App\Services\CsvPaymentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PaymentBatchController extends Controller
{
    public function index(Request $request): View
    {
        $batches = PaymentBatch::query()
            ->with('uploadedBy')
            ->withCount('transactions')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('batch_reference'), fn ($q) => $q->where('batch_reference', 'like', '%'.$request->string('batch_reference')->toString().'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('payment-batches.index', [
            'batches' => $batches,
            'filters' => $request->all(),
        ]);
    }

    public function create(CsvPaymentImportService $importService): View
    {
        return view('payment-batches.create', [
            'headers' => $importService->templateHeaders(),
        ]);
    }

    public function store(Request $request, CsvPaymentImportService $importService): RedirectResponse
    {
        $validated = $request->validate([
            'payment_file' => ['required', 'file', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $batch = $importService->import($validated['payment_file'], Auth::user(), $validated['notes'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['payment_file' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('payment-batches.show', $batch)
            ->with('status', 'Payment batch imported.');
    }

    public function show(PaymentBatch $paymentBatch, BankPaymentExportService $exportService): View
    {
        $paymentBatch->load(['uploadedBy', 'reviewedBy', 'exportedBy']);
        $transactions = $paymentBatch->transactions()->orderBy('id')->get();
        $formats = BankFileFormat::query()->where('is_active', true)->orderBy('name')->get();
        $previewFormat = $formats->first();
        $previewColumns = collect();
        $previewRows = collect();

        if ($previewFormat !== null) {
            $previewColumns = $exportService->previewColumns($previewFormat);
            $previewRows = $transactions->mapWithKeys(fn (PaymentTransaction $transaction) => [
                $transaction->id => $exportService->previewRow($transaction, $previewFormat),
            ]);
        }

        return view('payment-batches.show', [
            'batch' => $paymentBatch,
            'transactions' => $transactions,
            'formats' => $formats,
            'previewFormat' => $previewFormat,
            'previewColumns' => $previewColumns,
            'previewRows' => $previewRows,
        ]);
    }

    public function review(PaymentBatch $paymentBatch, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $paymentBatch->isReviewable()) {
            return back()->withErrors(['batch' => 'This batch cannot be verified while invalid rows remain or after it has already moved forward.']);
        }

        $paymentBatch->transactions()->where('status', 'pending')->update(['status' => 'verified']);
        $paymentBatch->update([
            'status' => 'verified',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $auditLogger->record('verify', $paymentBatch, [
            'rows' => $paymentBatch->row_count,
            'total_amount' => $paymentBatch->total_amount,
        ]);

        return back()->with('status', 'Payment batch verified.');
    }

    public function exportInstructions(PaymentBatch $paymentBatch): RedirectResponse
    {
        return redirect()
            ->route('payment-batches.show', $paymentBatch)
            ->withErrors(['batch' => 'Use the Export Bank File button on the batch page to generate the export.']);
    }

    public function export(Request $request, PaymentBatch $paymentBatch, BankPaymentExportService $exportService): mixed
    {
        $validated = $request->validate([
            'bank_file_format_id' => ['required', Rule::exists('bank_file_formats', 'id')->where('is_active', true)],
        ]);

        if (! $paymentBatch->isExportable()) {
            return back()->withErrors(['batch' => 'Only verified batches can be exported.']);
        }

        try {
            $path = $exportService->export(
                $paymentBatch,
                BankFileFormat::findOrFail($validated['bank_file_format_id']),
                Auth::user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch' => $exception->getMessage()]);
        }

        return response()->download($path);
    }

    public function editTransaction(PaymentBatch $paymentBatch, PaymentTransaction $paymentTransaction, CsvPaymentImportService $importService): View
    {
        $this->ensureTransactionBelongsToBatch($paymentBatch, $paymentTransaction);

        if ($paymentTransaction->status === 'exported') {
            abort(403, 'Exported transactions cannot be changed.');
        }

        if (! in_array($paymentBatch->status, ['draft', 'needs_review'], true)) {
            abort(403, 'Only draft or review batches can be corrected.');
        }

        return view('payment-batches.transactions.edit', [
            'batch' => $paymentBatch,
            'transaction' => $paymentTransaction,
            'fields' => $importService->editableFields(),
            'values' => $importService->valuesFromTransaction($paymentTransaction),
        ]);
    }

    public function paymentForm(PaymentBatch $paymentBatch, PaymentTransaction $paymentTransaction, CsvPaymentImportService $importService): View
    {
        $this->ensureTransactionBelongsToBatch($paymentBatch, $paymentTransaction);

        $paymentBatch->load(['uploadedBy', 'reviewedBy', 'exportedBy']);
        $orderedIds = $paymentBatch->transactions()->orderBy('id')->pluck('id')->values();
        $currentIndex = $orderedIds->search($paymentTransaction->id);
        $previousId = $currentIndex > 0 ? $orderedIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $orderedIds->count() - 1 ? $orderedIds[$currentIndex + 1] : null;

        return view('payment-batches.transactions.form', [
            'batch' => $paymentBatch,
            'transaction' => $paymentTransaction,
            'fields' => $importService->editableFields(),
            'values' => $importService->valuesFromTransaction($paymentTransaction),
            'position' => $currentIndex === false ? 1 : $currentIndex + 1,
            'total' => $orderedIds->count(),
            'previousTransaction' => $previousId ? PaymentTransaction::find($previousId) : null,
            'nextTransaction' => $nextId ? PaymentTransaction::find($nextId) : null,
        ]);
    }

    public function updateTransaction(Request $request, PaymentBatch $paymentBatch, PaymentTransaction $paymentTransaction, CsvPaymentImportService $importService): RedirectResponse
    {
        $this->ensureTransactionBelongsToBatch($paymentBatch, $paymentTransaction);

        if ($paymentTransaction->status === 'exported') {
            return back()->withErrors(['transaction' => 'Exported transactions cannot be changed.']);
        }

        if (! in_array($paymentBatch->status, ['draft', 'needs_review'], true)) {
            return back()->withErrors(['transaction' => 'Only draft or review batches can be corrected.']);
        }

        $fields = $request->input('fields', []);
        if (! is_array($fields)) {
            $fields = [];
        }

        try {
            $paymentTransaction = $importService->applyManualCorrection($paymentTransaction, array_intersect_key($fields, array_flip($importService->transactionFields())));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transaction' => $exception->getMessage()])->withInput();
        }

        $message = $paymentTransaction->status === 'pending'
            ? 'Transaction corrected. The batch can be verified when all rows are valid.'
            : 'Transaction saved, but validation issues remain.';

        return redirect()
            ->route('payment-batches.show', $paymentBatch)
            ->with('status', $message);
    }

    public function destroy(PaymentBatch $paymentBatch, AuditLogger $auditLogger): RedirectResponse
    {
        $exportPaths = $paymentBatch->exports()
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        $auditLogger->record('delete_batch', $paymentBatch, [
            'batch_reference' => $paymentBatch->batch_reference,
            'rows' => $paymentBatch->row_count,
            'invalid_rows' => $paymentBatch->invalid_count,
            'total_amount' => $paymentBatch->total_amount,
            'currency' => $paymentBatch->currency,
            'source_file_name' => $paymentBatch->source_file_name,
            'export_files' => array_values(array_map('basename', $exportPaths)),
        ]);

        $paymentBatch->delete();

        foreach ($exportPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return redirect()
            ->route('payment-batches.index')
            ->with('status', 'Payment batch and its transactions were deleted.');
    }

    public function destroyTransaction(
        PaymentBatch $paymentBatch,
        PaymentTransaction $paymentTransaction,
        CsvPaymentImportService $importService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $this->ensureTransactionBelongsToBatch($paymentBatch, $paymentTransaction);

        $auditLogger->record('delete_transaction', $paymentTransaction, [
            'batch_id' => $paymentBatch->id,
            'batch_reference' => $paymentBatch->batch_reference,
            'payment_no' => $paymentTransaction->payment_no,
            'payment_reference' => $paymentTransaction->payment_reference,
            'supplier_name' => $paymentTransaction->supplier_name,
            'amount' => $paymentTransaction->amount,
            'currency' => $paymentTransaction->currency,
            'status' => $paymentTransaction->status,
        ]);

        $paymentTransaction->delete();
        $importService->refreshBatchSummary($paymentBatch);

        return redirect()
            ->route('payment-batches.show', $paymentBatch)
            ->with('status', 'Payment transaction was deleted and the batch total was recalculated.');
    }

    public function template(CsvPaymentImportService $importService): mixed
    {
        return response()->streamDownload(function () use ($importService) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $importService->templateHeaders());
            fclose($output);
        }, 'supplier-payment-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function ensureTransactionBelongsToBatch(PaymentBatch $paymentBatch, PaymentTransaction $paymentTransaction): void
    {
        abort_unless($paymentTransaction->payment_batch_id === $paymentBatch->id, 404);
    }
}
