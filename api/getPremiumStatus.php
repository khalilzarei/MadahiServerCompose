<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_auth();

$db     = new DB_Functions();
$userId = (int)$user['id'];

$info = null;
try {
    $info = $db->isPremium($userId);

    // ---------- اگر هنوز پرو نیست ولی فاکتور در انتظار دارد،
    // پرداخت را در زرین‌پال re-verify کن (در صورتی که webhook
    // دیر رسیده باشد، کاربر با برگشتن به اپ بلافاصله فعال می‌بیند)
    if ($info === null || empty($info['is_premium'])) {
        $invoice = $db->getPendingInvoice($userId);
        if ($invoice !== null) {
            zarinpal_settle_pending_invoice($invoice);
            $info = $db->isPremium($userId);
        }
    }
} catch (Throwable $e) {
    // جدول/ستون‌های جدید هنوز ساخته نشده یا خطای موقت —
    // به‌جای 500، با «غیرپرو» پاسخ بده و خطا را لاگ کن
    error_log('getPremiumStatus error: ' . $e->getMessage());
    $info = null;
}

json_response([
    'error'          => false,
    'error_msg'      => 'اطلاعات یافت شد',
    'is_premium'     => ($info !== null && !empty($info['is_premium'])),
    'premium_source' => ($info !== null) ? ($info['premium_source'] ?? null) : null,
    'premium_at'     => ($info !== null) ? ($info['premium_at'] ?? null) : null,
]);
