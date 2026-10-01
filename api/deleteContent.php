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
$data = $db->deleteContent((int)$user['id'], $contentId);

if (!empty($data['success'])) {
    json_response(['success' => true, 'message' => $data['message']]);
}

error_response($data['message']);
