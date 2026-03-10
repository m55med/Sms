<?php

$ch = curl_init('https://839f-154-181-169-11.ngrok-free.app/api/webhook/sms');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'message' => 'تم استلام مبلغ 100 جنيه من رقم 01007292593 المسجل بإسم Ammar Y Alhusseiny رصيدك الحالي 104.88 تاريخ العملية 13:00 26-03-10 رقم العملية 018262799000. تابع كل مصروفاتك'
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo json_encode(json_decode($resp), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
