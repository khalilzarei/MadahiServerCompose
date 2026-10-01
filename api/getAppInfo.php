<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('GET');

$db   = new DB_Functions();
$info = $db->getAppInfo();

if ($info === null) {
    error_response('اطلاعاتی برای نمایش وجود ندارد');
}

json_response([
    'error'     => false,
    'error_msg' => 'اطلاعات یافت شد',
    'info'      => $info,
]);
