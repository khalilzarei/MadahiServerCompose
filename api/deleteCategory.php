<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user       = require_auth();
$categoryId = (int)($_POST['category_id'] ?? 0);

if ($categoryId <= 0) {
    error_response('دسته بندی مشخص نشده است');
}

$db   = new DB_Functions();
$data = $db->deleteCategory((int)$user['id'], $categoryId);

if (!empty($data['success'])) {
    json_response(['success' => true, 'message' => $data['message']]);
}

error_response($data['message']);
