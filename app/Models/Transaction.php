<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'raw_message',
        'provider',
        'type',
        'amount',
        'sender_phone',
        'sender_name',
        'transaction_id',
        'balance_after',
        'transaction_date',
        'is_suspicious',
        'suspicion_reasons',
        'verified_at',
        'verified_phone',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'datetime',
            'is_suspicious' => 'boolean',
            'suspicion_reasons' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
