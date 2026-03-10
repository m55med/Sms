<?php
// Quick test script for the webhook

$url = 'http://127.0.0.1:8001/api/webhook/sms';

// Test 1: Valid Vodafone Cash received message
$message1 = 'تم استلام مبلغ 200 جنيه من رقم 01007292593 المسجل بإسم Ammar Y Alhusseiny رصيدك الحالي 4520.14 تاريخ العملية 18:43 26-03-05 رقم العملية 018141653678. تابع كل مصروفاتك من تاريخ المعاملات على أبلكيشن أنا فودافون http://vf.eg/vfcash';

// Test 2: Fake message - same transaction ID, different amount
$message2 = 'تم استلام مبلغ 205 جنيه من رقم 01007292593 المسجل بإسم Ammar Y Alhusseiny رصيدك الحالي 4520.14 تاريخ العملية 18:43 26-03-05 رقم العملية 018141653678. تابع كل مصروفاتك من تاريخ المعاملات على أبلكيشن أنا فودافون http://vf.eg/vfcash';

function sendTest(string $url, string $message, string $label): void
{
    echo "\n========================================\n";
    echo "TEST: {$label}\n";
    echo "========================================\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['message' => $message]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Run tests
sendTest($url, $message1, 'رسالة حقيقية - استلام 200 جنيه');
sendTest($url, $message2, 'رسالة مزورة - نفس رقم العملية بمبلغ 205');
