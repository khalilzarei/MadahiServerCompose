<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user        = require_auth();
$contentId   = (int)($_POST['content_id'] ?? 0);
$subject     = trim((string)($_POST['subject'] ?? ''));
$answer      = trim((string)($_POST['answer'] ?? ''));
$content     = trim((string)($_POST['content'] ?? ''));
$contentType = (int)($_POST['content_type'] ?? 0);

if ($contentId <= 0 || $subject === '' || $content === '') {
    error_response('اطلاعات ناقص است');
}

$db   = new DB_Functions();
$data = $db->updateContent((int)$user['id'], $contentId, $answer, $content, $subject, $contentType);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
