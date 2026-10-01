<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user        = require_auth();
$categoryId  = (int)($_POST['category_id'] ?? 0);
$title       = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if ($categoryId <= 0 || $title === '') {
    error_response('اطلاعات ناقص است');
}

$db   = new DB_Functions();
$data = $db->updateCategory((int)$user['id'], $categoryId, $title, $description);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
