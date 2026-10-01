<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_auth();

$db   = new DB_Functions();
$data = $db->getMessages((int)$user['id']);

json_response([
    'error'     => false,
    'error_msg' => 'اطلاعات یافت شد',
    'data'      => $data,
]);
