<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$mobile = normalizeMobile((string)($_POST['data'] ?? ''));
if ($mobile === '') {
    error_response('لطفا شماره موبایل را وارد کنید');
}

// محدودیت تعداد تلاش برای جلوگیری از حملات Brute-Force
rate_limit('login_mobile_' . $mobile, 10, 900);   // حداکثر ۱۰ تلاش در ۱۵ دقیقه برای هر شماره
rate_limit('login_ip_' . clientIp(), 30, 900);     // حداکثر ۳۰ تلاش در ۱۵ دقیقه برای هر IP

$db   = new DB_Functions();
$data = $db->loginByMobile($mobile);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
