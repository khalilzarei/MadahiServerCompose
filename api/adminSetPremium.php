<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');
require_admin();

$userId = (int)($_POST['user_id'] ?? 0);
$isPremium = filter_var($_POST['is_premium'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($userId <= 0 || $isPremium === null) {
    error_response('شناسه کاربر یا وضعیت پرمیوم معتبر نیست');
}

$db = new DB_Functions();
if (!$db->setUserPremium($userId, $isPremium)) {
    error_response('کاربر یافت نشد');
}

json_response([
    'error' => false,
    'error_msg' => 'وضعیت پرمیوم به‌روزرسانی شد',
    'user_id' => $userId,
    'is_premium' => $isPremium,
]);
