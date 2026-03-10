<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * List transactions with optional filters.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = Transaction::query()->orderByDesc('id');

        if ($request->has('suspicious')) {
            $query->where('is_suspicious', $request->boolean('suspicious'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        if ($request->has('from')) {
            $query->where('transaction_date', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('transaction_date', '<=', $request->input('to'));
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Dashboard summary - totals, P2P tracking, suspicious count.
     */
    public function summary(): JsonResponse
    {
        $totalReceived = Transaction::where('type', 'received')
            ->where('is_suspicious', false)
            ->sum('amount');

        $totalSent = Transaction::where('type', 'sent')
            ->where('is_suspicious', false)
            ->sum('amount');

        $suspiciousCount = Transaction::where('is_suspicious', true)->count();
        $totalCount = Transaction::count();

        return response()->json([
            'total_received' => (float) $totalReceived,
            'total_sent' => (float) $totalSent,
            'net' => (float) ($totalReceived - $totalSent),
            'profit_or_loss' => $totalReceived >= $totalSent ? 'profit' : 'loss',
            'total_transactions' => $totalCount,
            'suspicious_transactions' => $suspiciousCount,
            'latest_balance' => (float) (Transaction::whereNotNull('balance_after')
                ->where('is_suspicious', false)
                ->latest('id')
                ->value('balance_after') ?? 0),
        ]);
    }
}
