<?php
declare(strict_types=1);

/**
 * لیست شعرهای یک دسته در کتابچه با صفحه‌بندی و جستجو
 * ورودی: category_id، q (جستجو، اختیاری)، page، limit
 * خروجی: { error, data: [...], total, page, pages }
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

require_auth();

$categoryId = (int)($_POST['category_id'] ?? 0);
$q          = trim((string)($_POST['q'] ?? ''));
$page       = max(1, (int)($_POST['page'] ?? 1));
$limit      = (int)($_POST['limit'] ?? 20);
if ($limit < 1)   { $limit = 20; }
if ($limit > 100) { $limit = 100; }

if ($categoryId <= 0) {
    error_response('دسته مشخص نشده است');
}

$db = new DB_Functions();

$items = $db->getLibraryContents($categoryId, $page, $limit, $q);
$total = $db->countLibraryContents($categoryId, $q);

json_response([
    'error'     => false,
    'error_msg' => 'اطلاعات یافت شد',
    'data'      => $items,
    'total'     => $total,
    'page'      => $page,
    'pages'     => max(1, (int)ceil($total / $limit)),
]);
