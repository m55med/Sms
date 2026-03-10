<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\FraudDetectorService;
use App\Services\MessageParserService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private MessageParserService $parser,
        private FraudDetectorService $fraudDetector,
        private TelegramService $telegram,
    ) {}

    /**
     * Receive SMS from iOS Shortcut and process it.
     * Also supports GET with ?message= for simple Shortcut integration.
     */
    public function receiveSms(Request $request): JsonResponse
    {
        $message = $request->input('message') ?? $request->getContent();

        if (empty($message)) {
            return response()->json(['error' => 'No message provided'], 400);
        }

        $result = $this->processMessage($message);

        return response()->json($result);
    }

    /**
     * Receive messages from Telegram Bot webhook.
     * When users forward SMS to the bot, it gets processed here.
     */
    public function receiveTelegram(Request $request): JsonResponse
    {
        $update = $request->all();

        Log::info('Telegram webhook received', ['payload' => $update]);

        // Extract text from Telegram message
        $message = $update['message']['text'] ?? null;

        if (empty($message)) {
            return response()->json(['ok' => true]);
        }

        // Ignore commands
        if (str_starts_with($message, '/')) {
            return $this->handleBotCommand($message);
        }

        $result = $this->processMessage($message);

        return response()->json(['ok' => true]);
    }

    /**
     * Process a message: parse, check fraud, store, notify.
     */
    private function processMessage(string $message): array
    {
        // 1. Parse the message
        $parsed = $this->parser->parse($message);

        // 2. Run fraud detection
        $fraudResult = $this->fraudDetector->check($parsed);

        // 3. Store the transaction
        $transaction = Transaction::create([
            'raw_message' => $parsed['raw_message'],
            'provider' => $parsed['provider'],
            'type' => $parsed['type'],
            'amount' => $parsed['amount'],
            'sender_phone' => $parsed['sender_phone'],
            'sender_name' => $parsed['sender_name'],
            'transaction_id' => $parsed['transaction_id'],
            'balance_after' => $parsed['balance_after'],
            'transaction_date' => $parsed['transaction_date'],
            'is_suspicious' => $fraudResult['is_suspicious'],
            'suspicion_reasons' => $fraudResult['reasons'],
        ]);

        // 4. Send Telegram notification
        if ($fraudResult['is_suspicious']) {
            $this->telegram->sendFraudAlert($parsed, $fraudResult);
        } else {
            $this->telegram->sendTransactionConfirmation($parsed);
        }

        return [
            'status' => $fraudResult['is_suspicious'] ? 'suspicious' : 'ok',
            'transaction_id' => $transaction->id,
            'parsed' => $parsed,
            'fraud_check' => $fraudResult,
        ];
    }

    /**
     * Handle bot commands like /start, /balance, /stats
     */
    private function handleBotCommand(string $command): JsonResponse
    {
        $cmd = strtolower(trim(explode(' ', $command)[0]));

        match ($cmd) {
            '/start' => $this->telegram->sendMessage("مرحباً! أنا SMS Guard Bot\nابعتلي أي رسالة SMS وهحللها وأقولك لو سليمة أو مشبوهة.\n\nالأوامر:\n/balance - رصيدك الحالي\n/stats - إحصائيات معاملاتك"),
            '/balance' => $this->sendBalanceInfo(),
            '/stats' => $this->sendStatsInfo(),
            default => $this->telegram->sendMessage("مش فاهم الأمر ده. جرب /start"),
        };

        return response()->json(['ok' => true]);
    }

    private function sendBalanceInfo(): bool
    {
        $latest = Transaction::whereNotNull('balance_after')
            ->where('is_suspicious', false)
            ->latest('id')
            ->first();

        if (!$latest) {
            return $this->telegram->sendMessage("مفيش معاملات مسجلة لسه.");
        }

        return $this->telegram->sendMessage(
            "💰 <b>رصيدك الحالي:</b> {$latest->balance_after} جنيه\n"
            . "<b>آخر معاملة:</b> {$latest->transaction_date}"
        );
    }

    private function sendStatsInfo(): bool
    {
        $totalReceived = Transaction::where('type', 'received')->where('is_suspicious', false)->sum('amount');
        $totalSent = Transaction::where('type', 'sent')->where('is_suspicious', false)->sum('amount');
        $total = Transaction::count();
        $suspicious = Transaction::where('is_suspicious', true)->count();

        $net = $totalReceived - $totalSent;
        $status = $net >= 0 ? "📈 ربح" : "📉 خسارة";

        return $this->telegram->sendMessage(
            "📊 <b>إحصائيات معاملاتك</b>\n\n"
            . "إجمالي المستلم: {$totalReceived} جنيه\n"
            . "إجمالي المرسل: {$totalSent} جنيه\n"
            . "الصافي: {$net} جنيه ({$status})\n\n"
            . "عدد المعاملات: {$total}\n"
            . "معاملات مشبوهة: {$suspicious}"
        );
    }
}
