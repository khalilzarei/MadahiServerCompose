<?php
declare(strict_types=1);

/**
 * ورود پنل مدیریت (بدون رمز — سازگار با فراخوانی قدیمی loginByMobile)
 * فقط حساب‌های is_admin می‌توانند وارد شوند.
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$mobile = normalizeMobile((string)($_POST['mobile'] ?? ''));
if ($mobile === '') {
    error_response('شماره موبایل را وارد کنید');
}

rate_limit('admin_login_ip_' . clientIp(), 10, 900);
rate_limit('admin_login_mobile_' . $mobile, 5, 900);
$db = new DB_Functions();
$user = $db->getUserByMobile($mobile);
if ($user === null || empty($user['is_admin'])) {
    error_response('این حساب دسترسی مدیر ندارد', 403);
}

$data = $db->loginByMobile($mobile);
json_response($data);
