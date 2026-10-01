<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$mobile   = normalizeMobile((string)($_POST['data'] ?? ''));
$fullName = trim((string)($_POST['full_name'] ?? ''));

if ($mobile === '' || $fullName === '') {
    error_response('لطفا اطلاعات کامل را وارد کنید');
}
if (!preg_match('/^09\d{9}$/', $mobile)) {
    error_response('شماره موبایل معتبر نیست');
}
if (mb_strlen($fullName) > 100) {
    error_response('نام و نام خانوادگی خیلی طولانی است');
}

rate_limit('register_mobile_' . $mobile, 5, 3600);  // حداکثر ۵ ثبت‌نام در ساعت برای هر شماره
rate_limit('register_ip_' . clientIp(), 20, 3600);

$db   = new DB_Functions();
$data = $db->register($mobile, $fullName);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
