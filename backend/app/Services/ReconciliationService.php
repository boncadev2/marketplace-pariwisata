<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\PaymentAttempt;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationEntry;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * @param string $reference
     * @param string $date
     * @param array $mutations Array of associative arrays with 'reference' and 'amount'
     */
    public function reconcile(string $reference, string $date, array $mutations): ReconciliationBatch
    {
        return DB::transaction(function () use ($reference, $date, $mutations) {
            $batch = ReconciliationBatch::create([
                'reference' => $reference,
                'date' => $date,
                'status' => 'processing',
            ]);

            foreach ($mutations as $mutation) {
                $gatewayReference = $mutation['reference'] ?? null;
                $amount = (int) ($mutation['amount'] ?? 0);

                if (!$gatewayReference) {
                    continue;
                }

                $attempt = PaymentAttempt::where('provider_reference', $gatewayReference)->first();

                $status = 'not_found';
                $notes = 'Payment attempt not found.';
                $internalAttemptId = null;

                if ($attempt) {
                    $internalAttemptId = $attempt->id;
                    $journalEntry = JournalEntry::where('order_id', $attempt->order_id)->first();

                    if ($attempt->amount == $amount && $attempt->status === 'succeeded') {
                        if ($journalEntry) {
                            $status = 'matched';
                            $notes = 'Payment matched and recorded in ledger.';
                        } else {
                            $status = 'mismatched';
                            $notes = 'Payment succeeded but no ledger entry found.';
                        }
                    } else {
                        $status = 'mismatched';
                        $notes = 'Amount mismatch or not succeeded. Expected amount: ' . $attempt->amount . ', Status: ' . $attempt->status;
                    }
                }

                ReconciliationEntry::create([
                    'batch_id' => $batch->id,
                    'payment_gateway_reference' => $gatewayReference,
                    'amount' => $amount,
                    'internal_payment_attempt_id' => $internalAttemptId,
                    'status' => $status,
                    'notes' => $notes,
                ]);
            }

            $batch->update(['status' => 'completed']);

            return $batch;
        });
    }
}
