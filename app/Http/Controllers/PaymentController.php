<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
    ) {}

    /**
     * Show the payment gateway page.
     */
    public function showGateway()
    {
        $paymentPhone = config('services.payment.phone', '01XXXXXXXXX');

        return view('payment.verify', compact('paymentPhone'));
    }

    /**
     * Verify a payment: match customer input against stored SMS transactions.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^01[012]\d{8}$/'],
        ], [
            'phone.regex' => 'رقم الهاتف لازم يبدأ بـ 010 أو 011 أو 012 ويكون 11 رقم',
        ]);

        $phone = $this->normalizePhone($request->input('phone'));

        // Find a matching unverified transaction from this phone
        $transaction = Transaction::where('type', 'received')
            ->where('sender_phone', $phone)
            ->where('is_suspicious', false)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($transaction) {
            // Mark as verified
            $transaction->update([
                'verified_at' => now(),
                'verified_phone' => $phone,
            ]);

            // Notify owner via Telegram
            $this->telegram->sendVerificationSuccess($transaction, $phone);

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق بنجاح! تم تأكيد الدفع.',
            ]);
        }

        // Notify owner of failed attempt
        $this->telegram->sendVerificationFailed($phone);

        return response()->json([
            'success' => false,
            'message' => 'لم يتم العثور على عملية تحويل مطابقة. تأكد من المبلغ ورقم الهاتف.',
        ]);
    }

    /**
     * Normalize Egyptian phone number to 01XXXXXXXXX format.
     */
    private function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, plus sign
        $phone = preg_replace('/[\s\-\+]/', '', $phone);

        // Convert 201XXXXXXXXX to 01XXXXXXXXX
        if (str_starts_with($phone, '20') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }
}
