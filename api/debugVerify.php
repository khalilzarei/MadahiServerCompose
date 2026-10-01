<?php
declare(strict_types=1);

/**
 * ⚠️ موقت — ابزار دیباگ کامل فلو زرین‌پال
 * ------------------------------------------------
 * 1) پاسخ خام API verify
 * 2) نتیجه parser واقعی ZarinpalClient (همین کدی که اپ استفاده می‌کند)
 * 3) تسویه اجباری فاکتور pending + وضعیت پرو کاربر
 *
 * بعد از اتمام دیباگ حتماً این فایل را حذف کنید!
 */
require_once __DIR__ . '/../include/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$userId = isset($_GET['u']) ? (int)$_GET['u'] : 1;

// ---------- ۱) پیدا کردن فاکتور ----------
$db      = new DB_Functions();
$invoice = $db->getPendingInvoice($userId);
$source  = '';

if ($invoice === null) {
    // اگر pending نداریم، آخرین فاکتور را نشان بده (فقط برای نمایش)
    $invoice = $db->dbLastInvoice();
    $source  = 'no pending — latest invoice (info only)';
}

if ($invoice === null) {
    echo "هیچ فاکتوری پیدا نشد.\n";
    exit;
}

echo "=== ۱) فاکتور ===\n";
echo 'id=' . $invoice['id'] . ' user=' . $invoice['user_id']
    . ' status=' . $invoice['status']
    . ' authority=' . $invoice['authority']
    . ' amount=' . $invoice['amount'] . "\n";
if ($source !== '') {
    echo "NOTE: $source\n";
}
echo "\n";

$authority = (string)$invoice['authority'];
$amount    = (int)$invoice['amount'];

// ---------- ۲) پاسخ خام API verify ----------
$body = json_encode([
    'merchant_id' => ZARINPAL_MERCHANT_ID,
    'amount'      => $amount,
    'authority'   => $authority,
]);

$ch = curl_init(ZARINPAL_API_BASE . '/pg/v4/payment/verify.json');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$raw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "=== ۲) پاسخ خام زرین‌پال (HTTP $httpCode) ===\n";
if ($err !== '') {
    echo "CURL ERROR: $err\n";
}
echo (string)$raw . "\n\n";

// ---------- ۳) parser واقعی ZarinpalClient ----------
$client = zarinpal_client();
$verified = $client->verify($authority, $amount);

echo "=== ۳) نتیجه parser واقعی ZarinpalClient ===\n";
echo 'success = ' . var_export($verified['success'], true) . "\n";
echo 'refId   = ' . var_export($verified['refId'], true) . "\n";
echo 'error   = ' . var_export($verified['error'], true) . "\n\n";

// ---------- ۴) تسویه اجباری (فقط اگر pending بود) ----------
if ($invoice['status'] === 'pending') {
    echo "=== ۴) تسویه اجباری فاکتور ===\n";
    try {
        $settled = zarinpal_settle_pending_invoice($invoice);
        echo 'settled = ' . var_export($settled, true) . "\n";
    } catch (Throwable $e) {
        echo 'SETTLE EXCEPTION: ' . $e->getMessage() . "\n";
    }
} else {
    echo "=== ۴) تسویه: فاکتور pending نیست (status={$invoice['status']}) ===\n";
}
echo "\n";

// ---------- ۵) وضعیت نهایی کاربر ----------
echo "=== ۵) وضعیت کاربر ===\n";
try {
    $info = $db->isPremium($userId);
    if ($info === null) {
        echo "کاربر id=$userId پیدا نشد\n";
    } else {
        echo 'is_premium     = ' . var_export($info['is_premium'], true) . "\n";
        echo 'premium_source = ' . var_export($info['premium_source'], true) . "\n";
        echo 'premium_at     = ' . var_export($info['premium_at'], true) . "\n";
    }
} catch (Throwable $e) {
    echo 'isPremium ERROR: ' . $e->getMessage() . "\n";
}
