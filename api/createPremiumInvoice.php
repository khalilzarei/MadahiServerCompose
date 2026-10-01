<?php
declare(strict_types=1);

/**
 * ساخت فاکتور پرداخت «نسخه پرو» در زرین‌پال
 * ------------------------------------------------------------
 * خروجی: pay_url — کاربر با آن به صفحه پرداخت هدایت می‌شود
 * قیمت از config سرور خوانده می‌شود (کلاینت مبلغ نمی‌فرستد)
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_auth();

$db = new DB_Functions();

// ---------- اگر کاربر قبلاً پرو فعال کرده، فاکتور جدید نده ----------
try {
    $currentInfo = $db->isPremium((int)$user['id']);
    if ($currentInfo !== null && !empty($currentInfo['is_premium'])) {
        json_response([
            'error'           => true,
            'error_msg'       => 'شما قبلاً نسخه پرو را فعال کرده‌اید',
            'already_premium' => true,
        ]);
    }
} catch (Throwable $e) {
    error_log('createPremiumInvoice isPremium check: ' . $e->getMessage());
    // اگر چک نشد (ساختار DB ناقص)، ادامه بده
}

rate_limit('premium_invoice_' . clientIp(), 10, 3600);

$amount = (int)ZARINPAL_PRO_PRICE_RIAL;
if ($amount <= 0) {
    error_response('قیمت نسخه پرو پیکربندی نشده است');
}

// ---------- ساخت درخواست پرداخت در زرین‌پال ----------
$result = zarinpal_client()->requestPayment(
    $amount,
    'فعال‌سازی نسخه پرو - دفتر مداحی',
    ZARINPAL_WEBHOOK_URL
);

if (isset($result['error'])) {
    error_log('createPremiumInvoice: ' . $result['error']);
    error_response($result['error']);
}

// ---------- ذخیره فاکتور در انتظار ----------
try {
    $db->createPremiumInvoice((int)$user['id'], $amount, $result['authority']);
    error_log('createPremiumInvoice: ثبت شد user=' . $user['id'] . ' authority=' . $result['authority'] . ' amount=' . $amount);
} catch (Throwable $e) {
    error_log('createPremiumInvoice save error: ' . $e->getMessage());
    error_response('خطا در ثبت فاکتور');
}

json_response([
    'error'     => false,
    'error_msg' => 'فاکتور با موفقیت ساخته شد',
    'authority' => $result['authority'],
    'amount'    => $amount,
    'pay_url'   => $result['pay_url'],
    'referrer'  => ZARINPAL_REFERRER_URL,
]);
