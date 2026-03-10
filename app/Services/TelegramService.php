<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    /**
     * Send a message to the configured Telegram chat.
     */
    public function sendMessage(string $text): bool
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a fraud alert for a suspicious transaction.
     */
    public function sendFraudAlert(array $parsed, array $fraudResult): bool
    {
        $reasons = collect($fraudResult['reasons'])->map(function ($r) {
            $icon = match ($r['severity']) {
                'critical' => "\xE2\x9B\x94",
                'high' => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'medium' => "\xE2\x9D\x93",
                default => "\xE2\x84\xB9\xEF\xB8\x8F",
            };
            return "{$icon} {$r['message']}";
        })->join("\n");

        $text = "\xF0\x9F\x9A\xA8 <b>رسالة مشبوهة!</b>\n\n"
            . "<b>المبلغ:</b> " . ($parsed['amount'] ?? 'غير معروف') . " جنيه\n"
            . "<b>من:</b> " . ($parsed['sender_name'] ?? 'غير معروف') . " ({$parsed['sender_phone']})\n"
            . "<b>رقم العملية:</b> " . ($parsed['transaction_id'] ?? 'غير معروف') . "\n\n"
            . "<b>أسباب الشك:</b>\n{$reasons}";

        return $this->sendMessage($text);
    }

    /**
     * Send confirmation for a valid transaction.
     */
    public function sendTransactionConfirmation(array $parsed): bool
    {
        $typeLabel = match ($parsed['type']) {
            'received' => "\xE2\xAC\x87\xEF\xB8\x8F استلام",
            'sent' => "\xE2\xAC\x86\xEF\xB8\x8F تحويل",
            default => "\xE2\x9D\x93 غير معروف",
        };

        $text = "\xE2\x9C\x85 <b>معاملة سليمة</b>\n\n"
            . "<b>النوع:</b> {$typeLabel}\n"
            . "<b>المبلغ:</b> " . ($parsed['amount'] ?? '?') . " جنيه\n"
            . "<b>من/إلى:</b> " . ($parsed['sender_name'] ?? '?') . "\n"
            . "<b>الرصيد:</b> " . ($parsed['balance_after'] ?? '?') . " جنيه\n"
            . "<b>رقم العملية:</b> " . ($parsed['transaction_id'] ?? '?');

        return $this->sendMessage($text);
    }

    /**
     * Send notification when a payment is successfully verified.
     */
    public function sendVerificationSuccess(Transaction $tx, string $phone): bool
    {
        $text = "\xE2\x9C\x85 <b>تم تأكيد دفع!</b>\n\n"
            . "<b>المبلغ:</b> {$tx->amount} جنيه\n"
            . "<b>من رقم:</b> {$phone}\n"
            . "<b>اسم المرسل:</b> " . ($tx->sender_name ?? '-') . "\n"
            . "<b>رقم العملية:</b> " . ($tx->transaction_id ?? '-');

        return $this->sendMessage($text);
    }

    /**
     * Send notification when a payment verification fails.
     */
    public function sendVerificationFailed(string $phone): bool
    {
        $text = "\xE2\x9D\x8C <b>محاولة تحقق فاشلة!</b>\n\n"
            . "<b>رقم الهاتف:</b> {$phone}\n"
            . "<b>السبب:</b> لم يتم العثور على معاملة مطابقة من هذا الرقم";

        return $this->sendMessage($text);
    }
}
