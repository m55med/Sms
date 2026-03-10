<?php

namespace App\Enums;

enum TransactionType: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Unknown = 'unknown';
}
