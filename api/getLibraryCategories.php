<?php
declare(strict_types=1);

/**
 * لیست دسته‌های کتابچه (کتابخانه‌ی عمومی — همه کاربران)
 * ورودی: q (جستجو، اختیاری)، page، limit
 * خروجی: { error, data: [...], total, page, pages }
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

require_auth();

$q     = trim((string)($_POST['q'] ?? ''));
$page  = max(1, (int)($_POST['page'] ?? 1));
$limit = (int)($_POST['limit'] ?? 20);
if ($limit < 1)   { $limit = 20; }
if ($limit > 100) { $limit = 100; }

$db = new DB_Functions();

$items = $db->getLibraryCategories($q, $page, $limit);
$total = $db->countLibraryCategories($q);

json_response([
    'error'  => false,
    'error_msg' => 'اطلاعات یافت شد',
    'data'   => $items,
    'total'  => $total,
    'page'   => $page,
    'pages'  => max(1, (int)ceil($total / $limit)),
]);
