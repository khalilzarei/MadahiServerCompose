<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user      = require_auth();
$contentId = (int)($_POST['content_id'] ?? 0);

if ($contentId <= 0) {
    error_response('محتوا مشخص نشده است');
}

$db   = new DB_Functions();
$data = $db->insertFavorite($contentId, (int)$user['id']);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
