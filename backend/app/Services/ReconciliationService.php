<?php

namespace App\Services;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\InventoryHold;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\PayoutBatch;
use App\Models\ReconciliationAlert;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationEntry;
use App\Models\RefundRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ReconciliationService
{
    /** @param array<int, array<string, mixed>> $mutations */
    public function reconcile(string $reference, string $date, array $mutations): ReconciliationBatch
    {
        return DB::transaction(function () use ($reference, $date, $mutations): ReconciliationBatch {
            $batch = ReconciliationBatch::create([
                'reference' => $reference,
                'date' => $date,
                'status' => 'processing',
                'source' => 'manual',
                'started_at' => now(),
            ]);

            foreach ($mutations as $mutation) {
                $gatewayReference = $mutation['reference'] ?? null;
                if (! is_string($gatewayReference) || $gatewayReference === '') {
                    continue;
                }

                $amount = (int) ($mutation['amount'] ?? 0);
                $attempt = PaymentAttempt::query()->where('provider_reference', $gatewayReference)->first();
                $journalEntryExists = $attempt !== null && $this->paymentJournalQuery($attempt)->exists();
                $discrepancies = [];

                if ($attempt === null) {
                    $status = 'not_found';
                    $notes = 'Payment attempt not found.';
                } else {
                    if ((int) $attempt->amount !== $amount) {
                        $discrepancies[] = 'amount';
                    }
                    if ($attempt->status !== 'succeeded') {
                        $discrepancies[] = 'status';
                    }
                    if (! $journalEntryExists) {
                        $discrepancies[] = 'ledger';
                    }

                    $status = $discrepancies === [] ? 'matched' : 'mismatched';
                    $notes = $status === 'matched'
                        ? 'Payment matched and recorded in ledger.'
                        : 'Payment differs from the internal payment or ledger record.';
                }

                ReconciliationEntry::create([
                    'batch_id' => $batch->id,
                    'payment_gateway_reference' => $gatewayReference,
                    'amount' => $amount,
                    'internal_payment_attempt_id' => $attempt?->id,
                    'status' => $status,
                    'provider_status' => isset($mutation['status']) ? (string) $mutation['status'] : null,
                    'internal_status' => $attempt?->status,
                    'provider_fee' => isset($mutation['fee']) ? (int) $mutation['fee'] : null,
                    'internal_fee' => 0,
                    'settlement_reference' => $mutation['settlement_reference'] ?? null,
                    'discrepancy_types' => $discrepancies,
                    'provider_payload' => Arr::only($mutation, ['status', 'fee', 'settlement_reference']),
                    'notes' => $notes,
                ]);
            }

            $this->completeBatch($batch);

            return $batch->fresh();
        }, 3);
    }

    /** @param array{reference:string,status:string,amount:int,currency:string,fee:int,settlement_reference:?string,payload:array<string,mixed>} $providerStatus */
    public function reconcilePaymentAttempt(PaymentAttempt $paymentAttempt, array $providerStatus): ReconciliationEntry
    {
        $this->validateProviderStatus($providerStatus);
        $providerState = strtolower($providerStatus['status']);
        $amountMatches = (int) $paymentAttempt->amount === (int) $providerStatus['amount'];
        $currencyMatches = $paymentAttempt->currency === strtoupper($providerStatus['currency']);
        $referenceMatches = $paymentAttempt->provider_reference === $providerStatus['reference'];

        if ($providerState === 'succeeded' && $amountMatches && $currencyMatches && $referenceMatches) {
            $this->recoverSuccessfulPayment($paymentAttempt, $providerStatus);
        } elseif ($providerState === 'failed' && $paymentAttempt->status === 'pending') {
            $paymentAttempt->update(['status' => 'failed']);
        }

        return DB::transaction(function () use ($paymentAttempt, $providerStatus, $providerState): ReconciliationEntry {
            $paymentAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($paymentAttempt->id);
            $batch = $this->automaticBatch($paymentAttempt->provider);
            $ledgerAmount = $this->paymentLedgerAmount($paymentAttempt);
            $internalFee = 0;
            $discrepancies = [];

            if ($paymentAttempt->provider_reference !== $providerStatus['reference']) {
                $discrepancies[] = 'reference';
            }
            if ((int) $paymentAttempt->amount !== (int) $providerStatus['amount']) {
                $discrepancies[] = 'amount';
            }
            if ($paymentAttempt->currency !== strtoupper($providerStatus['currency'])) {
                $discrepancies[] = 'currency';
            }
            if (! $this->statusesMatch($paymentAttempt->status, $providerState)) {
                $discrepancies[] = 'status';
            }
            if ((int) $providerStatus['fee'] !== $internalFee) {
                $discrepancies[] = 'fee';
            }
            if ($providerState === 'succeeded' && $ledgerAmount !== (int) $paymentAttempt->amount) {
                $discrepancies[] = 'ledger';
            }

            $discrepancies = array_values(array_unique($discrepancies));
            $entry = ReconciliationEntry::query()->updateOrCreate(
                ['batch_id' => $batch->id, 'payment_gateway_reference' => $paymentAttempt->provider_reference],
                [
                    'amount' => (int) $providerStatus['amount'],
                    'internal_payment_attempt_id' => $paymentAttempt->id,
                    'status' => $discrepancies === [] ? 'matched' : 'mismatched',
                    'provider_status' => $providerState,
                    'internal_status' => $paymentAttempt->status,
                    'provider_fee' => (int) $providerStatus['fee'],
                    'internal_fee' => $internalFee,
                    'settlement_reference' => $providerStatus['settlement_reference'],
                    'discrepancy_types' => $discrepancies,
                    'provider_payload' => $providerStatus['payload'],
                    'notes' => $discrepancies === []
                        ? 'Provider, payment, and ledger records match.'
                        : 'Discrepancy detected: '.implode(', ', $discrepancies).'.',
                ]
            );

            $attempts = (int) $paymentAttempt->reconciliation_attempts + 1;
            $terminalAndMatched = $discrepancies === [] && in_array($providerState, ['succeeded', 'failed'], true);
            $paymentAttempt->update([
                'reconciliation_attempts' => $attempts,
                'last_reconciled_at' => now(),
                'next_reconciliation_at' => $terminalAndMatched ? null : now()->addMinutes($this->backoffMinutes($attempts)),
                'reconciliation_error' => null,
            ]);

            $this->syncPaymentAlert($paymentAttempt, $entry);
            $this->completeBatch($batch);

            return $entry->fresh();
        }, 3);
    }

    public function generateDailyReport(string $date): ReconciliationBatch
    {
        $reportDate = CarbonImmutable::parse($date, 'Asia/Jakarta')->startOfDay();
        $batch = ReconciliationBatch::query()->firstOrCreate(
            ['reference' => 'daily-report-'.$reportDate->toDateString()],
            [
                'date' => $reportDate->toDateString(),
                'status' => 'processing',
                'source' => 'daily_report',
                'started_at' => now(),
            ]
        );

        Order::query()
            ->where('status', 'paid')
            ->whereHas('items', fn ($query) => $query->whereDoesntHave('voucher'))
            ->select(['id', 'partner_id', 'public_id'])
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $this->upsertAlert('paid_without_voucher', 'critical', 'order', $order->id, [
                        'partner_id' => $order->partner_id,
                        'order_id' => $order->id,
                        'details' => ['order_public_id' => $order->public_id],
                    ]);
                }
            });

        RefundRequest::query()
            ->whereIn('status', ['requested', 'approved', 'processing'])
            ->where('created_at', '<=', now()->subDay())
            ->with('order:id,partner_id,public_id')
            ->chunkById(100, function ($refunds): void {
                foreach ($refunds as $refund) {
                    $this->upsertAlert('stale_refund', 'high', 'refund', $refund->id, [
                        'partner_id' => $refund->order?->partner_id,
                        'order_id' => $refund->order_id,
                        'refund_request_id' => $refund->id,
                        'details' => ['status' => $refund->status, 'created_at' => $refund->created_at?->toIso8601String()],
                    ]);
                }
            });

        PayoutBatch::query()
            ->where(function ($query): void {
                $query->where(function ($processing): void {
                    $processing->where('status', 'processing')->where('updated_at', '<=', now()->subDay());
                })->orWhere('status', 'failed');
            })
            ->chunkById(100, function ($payouts): void {
                foreach ($payouts as $payout) {
                    $this->upsertAlert('uncertain_payout', 'high', 'payout', $payout->id, [
                        'payout_batch_id' => $payout->id,
                        'details' => ['batch_number' => $payout->batch_number, 'status' => $payout->status],
                    ]);
                }
            });

        InventoryHold::query()
            ->where('state', 'active')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($holds): void {
                foreach ($holds as $hold) {
                    $this->upsertAlert('unreleased_hold', 'high', 'hold', $hold->id, [
                        'inventory_hold_id' => $hold->id,
                        'details' => ['expires_at' => $hold->expires_at?->toIso8601String(), 'quantity' => $hold->quantity],
                    ]);
                }
            });

        $summary = [
            'entries' => [
                'matched' => ReconciliationEntry::query()->whereDate('created_at', $reportDate)->where('status', 'matched')->count(),
                'mismatched' => ReconciliationEntry::query()->whereDate('created_at', $reportDate)->where('status', 'mismatched')->count(),
                'not_found' => ReconciliationEntry::query()->whereDate('created_at', $reportDate)->where('status', 'not_found')->count(),
            ],
            'open_alerts' => ReconciliationAlert::query()
                ->where('status', 'open')
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->map(fn ($count): int => (int) $count)
                ->all(),
            'generated_at' => now()->toIso8601String(),
            'timezone' => 'Asia/Jakarta',
        ];

        $batch->update(['status' => 'completed', 'summary' => $summary, 'completed_at' => now()]);

        return $batch->fresh();
    }

    /** @param array<string, mixed> $providerStatus */
    private function recoverSuccessfulPayment(PaymentAttempt $paymentAttempt, array $providerStatus): void
    {
        $eventKey = 'reconciliation-'.hash('sha256', implode('|', [
            $paymentAttempt->provider,
            $providerStatus['reference'],
            $providerStatus['status'],
            $providerStatus['amount'],
            $providerStatus['currency'],
        ]));
        $payload = [
            'event_key' => $eventKey,
            'provider_reference' => $providerStatus['reference'],
            'status' => 'succeeded',
            'amount' => (int) $providerStatus['amount'],
            'currency' => strtoupper($providerStatus['currency']),
        ];

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider_event_key' => $eventKey],
            ['provider' => $paymentAttempt->provider, 'payment_attempt_id' => $paymentAttempt->id, 'payload' => $payload]
        );

        (new ProcessPaymentWebhook($event->id))->handle();
    }

    private function automaticBatch(string $provider): ReconciliationBatch
    {
        $date = now('Asia/Jakarta')->toDateString();

        return ReconciliationBatch::query()->firstOrCreate(
            ['reference' => "automatic-{$provider}-{$date}"],
            [
                'date' => $date,
                'status' => 'processing',
                'source' => 'automatic',
                'provider' => $provider,
                'started_at' => now(),
            ]
        );
    }

    private function completeBatch(ReconciliationBatch $batch): void
    {
        $counts = $batch->entries()->reorder()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $batch->update([
            'status' => 'completed',
            'summary' => [
                'total' => (int) $counts->sum(),
                'matched' => (int) ($counts['matched'] ?? 0),
                'mismatched' => (int) ($counts['mismatched'] ?? 0),
                'not_found' => (int) ($counts['not_found'] ?? 0),
            ],
            'completed_at' => now(),
        ]);
    }

    /** @return Builder<JournalEntry> */
    private function paymentJournalQuery(PaymentAttempt $paymentAttempt): Builder
    {
        return JournalEntry::query()
            ->where('order_id', $paymentAttempt->order_id)
            ->where(function ($query): void {
                $query->where('reference_type', 'payment')
                    ->orWhere(function ($legacy): void {
                        $legacy->where('reference_type', Order::class)->where('description', 'like', 'Payment received%');
                    });
            });
    }

    private function paymentLedgerAmount(PaymentAttempt $paymentAttempt): int
    {
        return (int) DB::table('journal_transactions')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_transactions.ledger_account_id')
            ->where('journal_entries.order_id', $paymentAttempt->order_id)
            ->where('journal_transactions.type', 'debit')
            ->where('ledger_accounts.code', 'asset_payment_gateway')
            ->sum('journal_transactions.amount');
    }

    private function statusesMatch(string $internalStatus, string $providerStatus): bool
    {
        return match ($providerStatus) {
            'succeeded' => $internalStatus === 'succeeded',
            'failed' => $internalStatus === 'failed',
            'pending', 'unknown' => in_array($internalStatus, ['created', 'pending'], true),
            default => false,
        };
    }

    private function backoffMinutes(int $attempts): int
    {
        return match (true) {
            $attempts <= 1 => 5,
            $attempts === 2 => 15,
            $attempts === 3 => 60,
            default => 360,
        };
    }

    private function syncPaymentAlert(PaymentAttempt $paymentAttempt, ReconciliationEntry $entry): void
    {
        $fingerprint = hash('sha256', 'payment-reconciliation:'.$paymentAttempt->id);
        if ($entry->status === 'matched') {
            ReconciliationAlert::query()->where('fingerprint', $fingerprint)->where('status', 'open')->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_notes' => 'Resolved automatically after provider verification.',
            ]);

            return;
        }

        $this->upsertAlert('payment_discrepancy', 'critical', 'payment-reconciliation', $paymentAttempt->id, [
            'partner_id' => $paymentAttempt->order?->partner_id,
            'order_id' => $paymentAttempt->order_id,
            'payment_attempt_id' => $paymentAttempt->id,
            'details' => [
                'provider_reference' => $paymentAttempt->provider_reference,
                'discrepancy_types' => $entry->discrepancy_types,
            ],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function upsertAlert(string $type, string $severity, string $subjectType, int $subjectId, array $attributes): ReconciliationAlert
    {
        $alert = ReconciliationAlert::query()->firstOrNew(['fingerprint' => hash('sha256', "{$subjectType}:{$subjectId}")]);
        $alert->fill(array_merge($attributes, [
            'type' => $type,
            'severity' => $severity,
            'detected_at' => $alert->detected_at ?? now(),
        ]));
        if (! $alert->exists) {
            $alert->status = 'open';
        }
        $alert->save();

        return $alert;
    }

    /** @param array<string, mixed> $providerStatus */
    private function validateProviderStatus(array $providerStatus): void
    {
        foreach (['reference', 'status', 'amount', 'currency', 'fee', 'payload'] as $key) {
            if (! array_key_exists($key, $providerStatus)) {
                throw new UnexpectedValueException("Provider status is missing {$key}.");
            }
        }

        if (! in_array(strtolower((string) $providerStatus['status']), ['pending', 'unknown', 'succeeded', 'failed'], true)) {
            throw new UnexpectedValueException('Provider returned an unsupported payment status.');
        }
        if (! is_array($providerStatus['payload'])) {
            throw new UnexpectedValueException('Provider payload must be an array.');
        }
        if ((int) $providerStatus['amount'] < 0 || (int) $providerStatus['fee'] < 0) {
            throw new UnexpectedValueException('Provider amounts must not be negative.');
        }
    }
}
