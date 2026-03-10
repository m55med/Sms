<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\FraudDetectorService;
use App\Services\MessageParserService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Poll Telegram Bot for new messages and process them';

    private int $offset = 0;

    public function handle(
        MessageParserService $parser,
        FraudDetectorService $fraudDetector,
        TelegramService $telegram,
    ): void {
        $botToken = config('services.telegram.bot_token');
        $this->info('SMS Guard Bot polling started... (Ctrl+C to stop)');

        while (true) {
            try {
                $response = Http::timeout(35)->get(
                    "https://api.telegram.org/bot{$botToken}/getUpdates",
                    [
                        'offset' => $this->offset,
                        'timeout' => 30, // long polling
                    ]
                );

                if (!$response->successful()) {
                    $this->error('API error: ' . $response->status());
                    sleep(5);
                    continue;
                }

                $updates = $response->json('result', []);

                foreach ($updates as $update) {
                    $this->offset = $update['update_id'] + 1;
                    $text = $update['message']['text'] ?? null;

                    if (empty($text)) {
                        continue;
                    }

                    $this->info("Message: {$text}");

                    // Handle commands
                    if (str_starts_with($text, '/')) {
                        $this->handleCommand($text, $telegram);
                        continue;
                    }

                    // Process as SMS
                    $parsed = $parser->parse($text);
                    $fraudResult = $fraudDetector->check($parsed);

                    Transaction::create([
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

                    if ($fraudResult['is_suspicious']) {
                        $telegram->sendFraudAlert($parsed, $fraudResult);
                        $this->warn('SUSPICIOUS!');
                    } else {
                        $telegram->sendTransactionConfirmation($parsed);
                        $this->info('OK - stored.');
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
                sleep(5);
            }
        }
    }

    private function handleCommand(string $command, TelegramService $telegram): void
    {
        $cmd = strtolower(trim(explode(' ', $command)[0]));

        match ($cmd) {
            '/start' => $telegram->sendMessage(
                "مرحباً! أنا SMS Guard Bot\n"
                . "ابعتلي أي رسالة SMS وهحللها وأقولك لو سليمة أو مشبوهة.\n\n"
                . "الأوامر:\n"
                . "/balance - رصيدك الحالي\n"
                . "/stats - إحصائيات معاملاتك"
            ),
            '/balance' => $this->sendBalance($telegram),
            '/stats' => $this->sendStats($telegram),
            default => $telegram->sendMessage("مش فاهم الأمر ده. جرب /start"),
        };
    }

    private function sendBalance(TelegramService $telegram): bool
    {
        $latest = Transaction::whereNotNull('balance_after')
            ->where('is_suspicious', false)
            ->latest('id')
            ->first();

        if (!$latest) {
            return $telegram->sendMessage("مفيش معاملات مسجلة لسه.");
        }

        return $telegram->sendMessage(
            "💰 <b>رصيدك الحالي:</b> {$latest->balance_after} جنيه\n"
            . "<b>آخر معاملة:</b> {$latest->transaction_date}"
        );
    }

    private function sendStats(TelegramService $telegram): bool
    {
        $totalReceived = Transaction::where('type', 'received')->where('is_suspicious', false)->sum('amount');
        $totalSent = Transaction::where('type', 'sent')->where('is_suspicious', false)->sum('amount');
        $total = Transaction::count();
        $suspicious = Transaction::where('is_suspicious', true)->count();

        $net = $totalReceived - $totalSent;
        $status = $net >= 0 ? "📈 ربح" : "📉 خسارة";

        return $telegram->sendMessage(
            "📊 <b>إحصائيات معاملاتك</b>\n\n"
            . "إجمالي المستلم: {$totalReceived} جنيه\n"
            . "إجمالي المرسل: {$totalSent} جنيه\n"
            . "الصافي: {$net} جنيه ({$status})\n\n"
            . "عدد المعاملات: {$total}\n"
            . "معاملات مشبوهة: {$suspicious}"
        );
    }
}
