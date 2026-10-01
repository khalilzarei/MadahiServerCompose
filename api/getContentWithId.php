<?php
declare(strict_types=1);

/**
 * جزئیات یک شعر/محتوا با شناسه (برای صفحه جزئیات کتابچه)
 * ورودی: content_id
 * خروجی: { error, content: {...} }
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

require_auth();

$contentId = (int)($_POST['content_id'] ?? 0);
if ($contentId <= 0) {
    error_response('شناسه محتوا مشخص نشده است');
}

$db = new DB_Functions();
$content = $db->getContentById($contentId);

if ($content === null) {
    error_response('محتوا یافت نشد');
}

json_response([
    'error'     => false,
    'error_msg' => 'اطلاعات یافت شد',
    'content'   => $content,
]);
