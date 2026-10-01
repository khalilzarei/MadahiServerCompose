<?php
declare(strict_types=1);

/**
 * Webhook پرداخت زرین‌پال
 * ------------------------------------------------------------
 * پس از پرداخت، زرین‌پال به این آدرس POST می‌زند.
 * ⚠️ بدنه callback هرگز مستقیم اعتماد نمی‌شود — پرداخت
 *    حتماً با API verify از سمت سرور تأیید می‌شود.
 *
 * این endpoint عمومی است (بدون require_auth) — شناسه کاربر
 * از طریق authority فاکتور پیدا می‌شود.
 */
require_once __DIR__ . '/../include/bootstrap.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: [];

// ---------- لاگ کامل ورودی (debug) ----------
error_log('premiumWebhook raw input: ' . $raw);
error_log('premiumWebhook POST: ' . json_encode($_POST, JSON_UNESCAPED_UNICODE));

// authority را از همه نام‌های محتمل بگیر (v3/v4، فرم/JSON، کوچک/بزرگ)
$authority = (string)(
    $payload['authority']
    ?? $payload['Authority']
    ?? $payload['data']['authority']
    ?? $payload['data']['Authority']
    ?? $_POST['authority']
    ?? $_POST['Authority']
    ?? ''
);

error_log('premiumWebhook extracted authority: [' . $authority . ']');

if ($authority === '') {
    error_log('premiumWebhook: authority خالی — ورودی کامل: ' . $raw);
    json_response(['error' => true, 'error_msg' => 'authority یافت نشد'], 400);
}

$db = new DB_Functions();

// در صورت نداشتن جدول/تابع‌های جدید، 500 نده (وگرنه زرین‌پال مدام retry می‌زند)
try {
    $invoice = $db->findInvoiceByAuthority($authority);
} catch (Throwable $e) {
    error_log('premiumWebhookZarinpal: ' . $e->getMessage());
    json_response(['error' => false, 'error_msg' => 'دریافت شد'], 200);
}

if ($invoice === null) {
    // فاکتوری با این authority در سیستم نیست — گزارش و توقف
    error_log("premiumWebhook: فاکتور پیدا نشد برای authority=[" . $authority . "]");
    json_response(['error' => true, 'error_msg' => 'فاکتور یافت نشد'], 200);
}

error_log('premiumWebhook: فاکتور پیدا شد id=' . $invoice['id'] . ' user=' . $invoice['user_id']);

// فقط فاکتورهای در انتظار تسویه می‌شوند (idempotent)
if ($invoice['status'] !== 'pending') {
    json_response(['error' => false, 'error_msg' => 'فاکتور قبلاً تسویه شده است']);
}

try {
    $settled = zarinpal_settle_pending_invoice($invoice);
} catch (Throwable $e) {
    error_log('premiumWebhook settle error: ' . $e->getMessage());
    json_response(['error' => false, 'error_msg' => 'دریافت شد'], 200);
}

if ($settled) {
    json_response(['error' => false, 'error_msg' => 'پرداخت تأیید و پرو فعال شد']);
}

json_response(['error' => true, 'error_msg' => 'پرداخت هنوز تأیید نشده است'], 200);
