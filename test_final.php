<?php

// Simulate what the iOS Shortcut does - GET request with message in URL
$message = urlencode('تم استلام مبلغ 50 جنيه من رقم 01007292593 المسجل بإسم Ammar Y Alhusseiny رصيدك الحالي 154.88 تاريخ العملية 13:30 26-03-10 رقم العملية 018262799500. تابع كل مصروفاتك');

$url = "https://839f-154-181-169-11.ngrok-free.app/api/webhook/sms?message={$message}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo json_encode(json_decode($resp), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
