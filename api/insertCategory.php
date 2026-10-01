<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_auth();

// برای سازگاری با نسخه فعلی اپ، هر دو نام فیلد پذیرفته می‌شود
$title       = trim((string)($_POST['title'] ?? ($_POST['msg'] ?? '')));
$description = trim((string)($_POST['description'] ?? ''));

if ($title === '') {
    error_response('عنوان را وارد کنید');
}
if (mb_strlen($title) > 200 || mb_strlen($description) > 200) {
    error_response('عنوان یا توضیحات خیلی طولانی است');
}

$db   = new DB_Functions();
$data = $db->insertCategory($title, $description, (int)$user['id']);

if (!empty($data['error'])) {
    error_response($data['error_msg']);
}

json_response($data);
