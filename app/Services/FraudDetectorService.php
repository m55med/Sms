<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class FraudDetectorService
{
    /**
     * Run all fraud checks on parsed message data.
     * Returns ['is_suspicious' => bool, 'reasons' => array]
     */
    public function check(array $parsed): array
    {
        $reasons = [];

        $this->checkDuplicateTransactionId($parsed, $reasons);
        $this->checkBalanceContinuity($parsed, $reasons);
        $this->checkDuplicateTimestamp($parsed, $reasons);
        $this->checkTransactionIdPattern($parsed, $reasons);
        $this->checkTransactionDateLogic($parsed, $reasons);

        return [
            'is_suspicious' => !empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Check 1: Same transaction ID with different amount = FRAUD
     */
    private function checkDuplicateTransactionId(array $parsed, array &$reasons): void
    {
        if (empty($parsed['transaction_id'])) {
            return;
        }

        $existing = Transaction::where('transaction_id', $parsed['transaction_id'])->first();

        if (!$existing) {
            return;
        }

        if ((float) $existing->amount !== (float) $parsed['amount']) {
            $reasons[] = [
                'code' => 'DUPLICATE_TX_DIFFERENT_AMOUNT',
                'severity' => 'critical',
                'message' => "رقم العملية {$parsed['transaction_id']} موجود قبل كده بمبلغ {$existing->amount} جنيه، والرسالة الجديدة بتقول {$parsed['amount']} جنيه",
            ];
        } else {
            $reasons[] = [
                'code' => 'DUPLICATE_TX_SAME_AMOUNT',
                'severity' => 'warning',
                'message' => "رقم العملية {$parsed['transaction_id']} مكرر (نفس المبلغ) - ممكن تكون رسالة مكررة",
            ];
        }
    }

    /**
     * Check 2: Balance continuity - previous balance + amount should = current balance
     */
    private function checkBalanceContinuity(array $parsed, array &$reasons): void
    {
        if (empty($parsed['balance_after']) || empty($parsed['amount'])) {
            return;
        }

        $lastTransaction = Transaction::where('provider', $parsed['provider'])
            ->whereNotNull('balance_after')
            ->where('is_suspicious', false)
            ->latest('id')
            ->first();

        if (!$lastTransaction) {
            return; // First transaction, nothing to compare
        }

        $expectedBalance = match ($parsed['type']) {
            'received' => (float) $lastTransaction->balance_after + (float) $parsed['amount'],
            'sent' => (float) $lastTransaction->balance_after - (float) $parsed['amount'],
            default => null,
        };

        if ($expectedBalance === null) {
            return;
        }

        $actualBalance = (float) $parsed['balance_after'];
        $diff = abs($expectedBalance - $actualBalance);

        // Allow small floating point tolerance
        if ($diff > 0.01) {
            $reasons[] = [
                'code' => 'BALANCE_MISMATCH',
                'severity' => 'high',
                'message' => "الرصيد مش منطقي! الرصيد السابق {$lastTransaction->balance_after} والمبلغ {$parsed['amount']} - المفروض الرصيد يكون {$expectedBalance} بس الرسالة بتقول {$actualBalance}",
            ];
        }
    }

    /**
     * Check 3: Same exact timestamp for different transactions
     */
    private function checkDuplicateTimestamp(array $parsed, array &$reasons): void
    {
        if (empty($parsed['transaction_date'])) {
            return;
        }

        $sameTime = Transaction::where('transaction_date', $parsed['transaction_date'])
            ->where(function ($q) use ($parsed) {
                if (!empty($parsed['transaction_id'])) {
                    $q->where('transaction_id', '!=', $parsed['transaction_id']);
                }
            })
            ->exists();

        if ($sameTime) {
            $reasons[] = [
                'code' => 'DUPLICATE_TIMESTAMP',
                'severity' => 'medium',
                'message' => "فيه معاملة تانية بنفس التوقيت بالظبط ({$parsed['transaction_date']})",
            ];
        }
    }

    /**
     * Check 4: Transaction ID format validation
     */
    private function checkTransactionIdPattern(array $parsed, array &$reasons): void
    {
        if (empty($parsed['transaction_id'])) {
            return;
        }

        // Vodafone Cash transaction IDs are typically 12-18 digits
        if (!preg_match('/^\d{12,18}$/', $parsed['transaction_id'])) {
            $reasons[] = [
                'code' => 'INVALID_TX_FORMAT',
                'severity' => 'medium',
                'message' => "رقم العملية ({$parsed['transaction_id']}) مش في الشكل المتوقع",
            ];
        }
    }

    /**
     * Check 5: Transaction date should be close to current time (not in the past or future)
     */
    private function checkTransactionDateLogic(array $parsed, array &$reasons): void
    {
        if (empty($parsed['transaction_date'])) {
            return;
        }

        try {
            $txDate = Carbon::parse($parsed['transaction_date'])->timezone('Africa/Cairo');
            $now = Carbon::now('Africa/Cairo');
            $diffMinutes = $now->diffInMinutes($txDate, false);

            // Transaction date is more than 10 minutes in the future
            if ($diffMinutes > 10) {
                $reasons[] = [
                    'code' => 'FUTURE_DATE',
                    'severity' => 'high',
                    'message' => "تاريخ العملية ({$parsed['transaction_date']}) في المستقبل! لسه ما جاش",
                ];
            }

            // Transaction date is more than 30 minutes in the past
            if ($diffMinutes < -30) {
                $reasons[] = [
                    'code' => 'OLD_DATE',
                    'severity' => 'high',
                    'message' => "تاريخ العملية ({$parsed['transaction_date']}) قديم - أكتر من 30 دقيقة فاتت",
                ];
            }
        } catch (\Throwable $e) {
            // ignore parse errors
        }
    }
}
