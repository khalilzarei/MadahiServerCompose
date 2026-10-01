<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$exception      = trim((string)($_POST['exception'] ?? ''));
$androidVersion = trim((string)($_POST['android_version'] ?? ''));
$appVersion     = trim((string)($_POST['app_version'] ?? ''));

if ($exception === '') {
    error_response('اطلاعات خطا ارسال نشده است');
}

rate_limit('crash_' . clientIp(), 20, 3600);

$db   = new DB_Functions();
$data = $db->insertCrash($exception, $androidVersion, $appVersion);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
