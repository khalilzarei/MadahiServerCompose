<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_premium();
$contentId = (int)($_POST['content_id'] ?? 0);
if ($contentId <= 0) {
    error_response('محتوا مشخص نشده است');
}

$db = new DB_Functions();
$result = $db->removeContentAudio((int)$user['id'], $contentId);
if (empty($result['success'])) {
    error_response($result['message']);
}

json_response(['error' => false, 'error_msg' => $result['message']]);
