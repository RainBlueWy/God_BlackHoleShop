<?php
/**
 * สร้าง QR PromptPay แบบ EMV (แอปธนาคารสแกนแล้วเจอ)
 * ใช้ API สร้าง payload จริง แทน promptpay.io ที่อาจเป็นแค่ลิงก์
 */
header('Content-Type: image/png');
header('Cache-Control: no-store');

$phone = isset($_GET['phone']) ? preg_replace('/\D/', '', $_GET['phone']) : '';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if (strlen($phone) !== 10 || $amount <= 0) {
    // ส่ง 1x1 PNG โปร่งใสแทนถ้าข้อมูลไม่ครบ
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    exit;
}

$payload = [
    'target' => $phone,
    'amount' => round($amount, 2),
    'size'   => 300,
    'format' => 'base64'
];

$ch = curl_init('https://api.lorwongam.com/api/promptpay-qr-generator/');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200 && $res) {
    $data = json_decode($res, true);
    if (!empty($data['success']) && !empty($data['data']['qr_code'])) {
        $b64 = $data['data']['qr_code'];
        if (preg_match('/^data:image\/png;base64,(.+)$/', $b64, $m)) {
            echo base64_decode($m[1]);
            exit;
        }
    }
}

// Fallback: ใช้ promptpay.io ถ้า API ข้างบนใช้ไม่ได้
$url = 'https://promptpay.io/' . $phone . '/' . (round($amount, 2)) . '.png';
$img = @file_get_contents($url);
if ($img !== false) {
    echo $img;
    exit;
}

// สุดท้ายส่งรูปว่าง 1x1
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
