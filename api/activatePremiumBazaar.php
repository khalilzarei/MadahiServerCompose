<?php
declare(strict_types=1);

/**
 * فعال‌سازی نسخه پرو از طریق خرید درون‌برنامه‌ای بازار
 * ------------------------------------------------------------
 * ورودی (POST form):
 *   purchase_token : توکن خرید (از INAPP_PURCHASE_DATA)
 *   product_id     : اختیاری — پیش‌فرض همان SKU پیکربندی‌شده (BAZAAR_PRO_PRODUCT_ID)
 *
 * فلو:
 *   ۱) احراز هویت کاربر (Bearer token)
 *   ۲) اعتبارسنجی خرید از API توسعه‌دهندگان بازار (server-to-server)
 *   ۳) در صورت purchaseState = 0 → فعال‌سازی idempotent
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_auth();

$purchaseToken = trim((string)($_POST['purchase_token'] ?? ''));
$productId     = trim((string)($_POST['product_id'] ?? ''));
if ($productId === '') {
    $productId = BAZAAR_PRO_PRODUCT_ID;
}

if ($purchaseToken === '' || strlen($purchaseToken) > 500 || $productId === '') {
    error_response('اطلاعات خرید ناقص است');
}

// جلوگیری از سوءاستفاده از endpoint اعتبارسنجی
rate_limit('premium_activate_' . clientIp(), 20, 3600);

// ---------- ۱) اعتبارسنجی خرید از بازار ----------
try {
    $bazaar   = new BazaarClient(BAZAAR_CLIENT_ID, BAZAAR_CLIENT_SECRET, BAZAAR_REFRESH_TOKEN);
    $purchase = $bazaar->getPurchaseStatus(BAZAAR_PACKAGE_NAME, $productId, $purchaseToken);
} catch (Throwable $e) {
    error_log('activatePremiumBazaar: ' . $e->getMessage());
    error_response('امکان اعتبارسنجی خرید وجود ندارد؛ کمی بعد دوباره تلاش کنید');
}

// purchaseState: 0 = خریداری‌شده | 1 = لغو‌شده | 2 = برگشت‌خورده
$state = (int)($purchase['purchaseState'] ?? -1);
if ($state !== 0) {
    error_log("activatePremiumBazaar: purchaseState invalid ($state) for user {$user['id']}");
    error_response('این خرید معتبر نیست');
}

// ---------- ۲) فعال‌سازی idempotent ----------
$db   = new DB_Functions();
$data = $db->activatePremium((int)$user['id'], 'bazaar', $purchaseToken);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response([
    'error'        => false,
    'error_msg'    => $data['error_msg'],
    'is_premium'   => true,
    'already'      => !empty($data['already']),
]);
