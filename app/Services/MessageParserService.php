<?php

namespace App\Services;

use App\Enums\TransactionType;

class MessageParserService
{
    /**
     * Parse an SMS message and extract transaction details.
     */
    public function parse(string $message): array
    {
        // Try Vodafone Cash patterns
        $result = $this->parseVodafoneCashReceived($message)
            ?? $this->parseVodafoneCashSent($message)
            ?? $this->parseVodafoneCashBalance($message)
            ?? $this->parseGeneric($message);

        $result['raw_message'] = $message;

        return $result;
    }

    /**
     * Vodafone Cash - received money
     * Pattern: تم استلام مبلغ {amount} جنيه من رقم {phone} المسجل بإسم {name} رصيدك الحالي {balance} تاريخ العملية {time} {date} رقم العملية {transaction_id}.
     */
    private function parseVodafoneCashReceived(string $message): ?array
    {
        $pattern = '/تم استلام مبلغ\s+([\d.]+)\s+جنيه?\s+من رقم\s+([\d]+)\s+المسجل بإسم\s+(.+?)\s+رصيدك الحالي\s+([\d.]+)\s+تاريخ العملية\s+([\d:]+)\s+([\d-]+)\s+رقم العملية\s+([\d]+)/u';

        if (preg_match($pattern, $message, $matches)) {
            return [
                'provider' => 'vodafone_cash',
                'type' => TransactionType::Received->value,
                'amount' => (float) $matches[1],
                'sender_phone' => $matches[2],
                'sender_name' => trim($matches[3]),
                'balance_after' => (float) $matches[4],
                'transaction_date' => $this->parseDateTime($matches[5], $matches[6]),
                'transaction_id' => $matches[7],
            ];
        }

        return null;
    }

    /**
     * Vodafone Cash - sent money
     * Pattern: تم تحويل مبلغ {amount} جنيه الى رقم {phone} المسجل بإسم {name} رصيدك الحالي {balance} تاريخ العملية {time} {date} رقم العملية {transaction_id}.
     */
    private function parseVodafoneCashSent(string $message): ?array
    {
        $pattern = '/تم تحويل مبلغ\s+([\d.]+)\s+جنيه?\s+(?:الى|إلى|الي|إلي) رقم\s+([\d]+)\s+المسجل بإسم\s+(.+?)\s+رصيدك الحالي\s+([\d.]+)\s+تاريخ العملية\s+([\d:]+)\s+([\d-]+)\s+رقم العملية\s+([\d]+)/u';

        if (preg_match($pattern, $message, $matches)) {
            return [
                'provider' => 'vodafone_cash',
                'type' => TransactionType::Sent->value,
                'amount' => (float) $matches[1],
                'sender_phone' => $matches[2],
                'sender_name' => trim($matches[3]),
                'balance_after' => (float) $matches[4],
                'transaction_date' => $this->parseDateTime($matches[5], $matches[6]),
                'transaction_id' => $matches[7],
            ];
        }

        return null;
    }

    /**
     * Vodafone Cash - balance inquiry
     * Pattern: رصيد حسابك فى فودافون كاش الحالي{balance} جنيه؛ تاريخ العملية {time} {date} رقم العملية{transaction_id}.
     */
    private function parseVodafoneCashBalance(string $message): ?array
    {
        $pattern = '/رصيد حسابك ف[يى] فودافون كاش الحالي\s*([\d.]+)\s*جنيه?[؛;]\s*تاريخ العملية\s*([\d:]+)\s*([\d-]+)\s*رقم العملية\s*([\d]+)/u';

        if (preg_match($pattern, $message, $matches)) {
            return [
                'provider' => 'vodafone_cash',
                'type' => 'balance_inquiry',
                'amount' => null,
                'sender_phone' => null,
                'sender_name' => null,
                'balance_after' => (float) $matches[1],
                'transaction_date' => $this->parseDateTime($matches[2], $matches[3]),
                'transaction_id' => $matches[4],
            ];
        }

        return null;
    }

    /**
     * Generic fallback - try to extract whatever we can
     */
    private function parseGeneric(string $message): array
    {
        $amount = null;
        $transactionId = null;
        $phone = null;

        // Try to find amount
        if (preg_match('/([\d,]+\.?\d*)\s*جنيه?/u', $message, $m)) {
            $amount = (float) str_replace(',', '', $m[1]);
        }

        // Try to find transaction ID
        if (preg_match('/رقم العملية\s*([\d]+)/u', $message, $m)) {
            $transactionId = $m[1];
        }

        // Try to find phone number
        if (preg_match('/(01[0-9]{9})/u', $message, $m)) {
            $phone = $m[1];
        }

        return [
            'provider' => 'unknown',
            'type' => TransactionType::Unknown->value,
            'amount' => $amount,
            'sender_phone' => $phone,
            'sender_name' => null,
            'balance_after' => null,
            'transaction_date' => null,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Parse date/time from Vodafone Cash format: "18:43" "26-03-05"
     */
    private function parseDateTime(string $time, string $date): ?string
    {
        try {
            // Date format: YY-MM-DD, time is in Egypt timezone (Africa/Cairo)
            $parts = explode('-', $date);
            if (count($parts) === 3) {
                $year = '20' . $parts[0];
                $month = $parts[1];
                $day = $parts[2];
                $cairoDate = "{$year}-{$month}-{$day} {$time}:00";
                // Convert from Egypt time to UTC for storage
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $cairoDate, 'Africa/Cairo')
                    ->utc()
                    ->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }
}
