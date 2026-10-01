<?php
declare(strict_types=1);

// ============================================================
// bootstrap.php — نقطه ورود مشترک همه اندپوینت‌ها
// شامل: بارگذاری کانفیگ، ابزارهای پاسخ JSON، احراز هویت،
// محدودیت درخواست و مدیریت خطا
// ============================================================

date_default_timezone_set('Asia/Tehran');

// ---------- بارگذاری تنظیمات (خارج از پوشه عمومی، یا include/) ----------
$configCandidates = [
    dirname(__DIR__, 2) . '/config.php',  // ⭐ پیشنهادی: یک پوشه بالاتر از public_html
    __DIR__ . '/config.php',              // حالت توسعه محلی
];

$configLoaded = false;
foreach ($configCandidates as $configPath) {
    if (is_file($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => true, 'error_msg' => 'سرور به درستی پیکربندی نشده است'], JSON_UNESCAPED_UNICODE);
    exit;
}

// پیش‌فرض‌های امن
defined('DB_PORT')      || define('DB_PORT', 3306);
defined('APP_ENV')      || define('APP_ENV', 'production');
defined('APP_BASE_URL') || define('APP_BASE_URL', 'https://madahinote.ir');

// ---------- نمایش خطا فقط در محیط توسعه ----------
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDirectory = __DIR__ . '/../logs';
    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0750, true);
    }
    if (is_dir($logDirectory) && is_writable($logDirectory)) {
        ini_set('error_log', $logDirectory . '/php-error.log');
    }
}

// ---------- خطاهای مدیریت‌نشده به JSON تبدیل شوند ----------
set_exception_handler(function (Throwable $e) {
    error_log(sprintf(
        "API UNCAUGHT EXCEPTION: %s: %s in %s:%d\nStack trace:\n%s",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'error_msg' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/DB_Functions.php';
require_once __DIR__ . '/CategoryNormalizer.php';
require_once __DIR__ . '/BazaarClient.php';
require_once __DIR__ . '/ZarinpalClient.php';

// ============================================================
// زرین‌پال — تسویه فاکتور (مشارکند webhook و چک وضعیت)
// ============================================================

function zarinpal_client(): ZarinpalClient
{
    return new ZarinpalClient(ZARINPAL_API_BASE, ZARINPAL_MERCHANT_ID);
}

/**
 * اعتبارسنجی یک فاکتور در انتظار در زرین‌پال؛ اگر پرداخت شده،
 * پرو را فعال می‌کند (idempotent). true = الان پرو فعال شد/فعال است
 */
function zarinpal_settle_pending_invoice(array $invoice): bool
{
    $result = zarinpal_client()->verify(
        (string)$invoice['authority'],
        (int)$invoice['amount']
    );

    if (!empty($result['success'])) {
        (new DB_Functions())->settleZarinpalInvoice(
            (int)$invoice['id'],
            (string)($result['refId'] ?? '')
        );
        return true;
    }

    return false;
}

// polyfill برای سرورهای nginx که getallheaders ندارند
if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
            }
        }
        return $headers;
    }
}

// ============================================================
// ابزارهای پاسخ JSON
// ============================================================

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response(string $message, int $code = 200): void
{
    // توجه: برای سازگاری با نسخه فعلی اپ (که کد وضعیت HTTP را نمی‌خواند)
    // پیش‌فرض 200 است؛ بعد از آپدیت اپ می‌توانید کدهای واقعی (401, 429, ...) بدهید.
    json_response(['error' => true, 'error_msg' => $message], $code);
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        error_response('روش درخواست باید ' . strtoupper($method) . ' باشد');
    }
}

// ============================================================
// احراز هویت (Token)
// ============================================================

function getBearerToken(): ?string
{
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+([A-Za-z0-9]+)/i', $auth, $m)) {
        return $m[1];
    }
    return null;
}

function require_auth(): array
{
    $token = getBearerToken();
    if ($token === null) {
        error_response('احراز هویت لازم است');
    }

    $db   = new DB_Functions();
    $user = $db->getUserByToken($token);

    if ($user === null) {
        error_response('نشست شما معتبر نیست؛ دوباره وارد شوید');
    }

    // تمدید خودکار نشست (۳۰ روز از آخرین استفاده)
    $db->refreshTokenExpiry((int)$user['id']);
    return $user;
}

// ============================================================
// محدودیت درخواست (ضد حملات Brute-Force)
// ============================================================

function rate_limit(string $key, int $maxAttempts, int $windowSeconds): void
{
    $db = new DB_Functions();
    if ($db->countRequests($key, $windowSeconds) >= $maxAttempts) {
        error_response('تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید');
    }
    $db->logRequest($key);
}

// ============================================================
// نسخه پرو — برای endpointهای پولی
// ============================================================

/**
 * احراز هویت + بررسی نسخه پرو را در یک قدم انجام می‌دهد.
 * برای هر endpoint پولی فقط همین یک خط کافی است:
 *   $user = require_premium();
 */
function require_premium(): array
{
    $user = require_auth();
    $db   = new DB_Functions();
    $info = $db->isPremium((int)$user['id']);

    if ($info === null || empty($info['is_premium'])) {
        error_response('این قابلیت در نسخه پرو در دسترس است');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (empty($user['is_admin'])) {
        error_response('دسترسی مدیر لازم است', 403);
    }
    return $user;
}

// ============================================================
// ابزارهای کمکی
// ============================================================

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// نرمال‌سازی شماره موبایل: 0912… / +98912… / 912… → 0912…
function normalizeMobile(string $mobile): string
{
    $mobile = preg_replace('/\D+/', '', $mobile) ?? '';
    if (str_starts_with($mobile, '98')) {
        $mobile = '0' . substr($mobile, 2);
    } elseif (str_starts_with($mobile, '9') && strlen($mobile) === 10) {
        $mobile = '0' . $mobile;
    }
    return $mobile;
}
