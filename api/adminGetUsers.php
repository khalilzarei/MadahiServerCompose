<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('GET');

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 30)));
$mobile = preg_replace('/\D+/', '', (string)($_GET['mobile'] ?? '')) ?? '';
if (strlen($mobile) === 12 && str_starts_with($mobile, '98')) {
    $mobile = '0' . substr($mobile, 2);
} elseif (strlen($mobile) === 10 && str_starts_with($mobile, '9')) {
    $mobile = '0' . $mobile;
}
$requestId = bin2hex(random_bytes(6));
$stage = 'admin_auth';
try {
    $admin = require_admin();
    error_log(sprintf(
        '[adminGetUsers:%s] request start admin_id=%d page=%d page_size=%d mobile_filter_length=%d',
        $requestId,
        (int)$admin['id'],
        $page,
        $pageSize,
        strlen($mobile)
    ));

    $db = new DB_Functions();
    $stage = 'count_all_users';
    $totalUsers = $db->countAdminUsers();
    $stage = 'count_filtered_users';
    $totalResults = $db->countAdminUsers($mobile);
    $totalPages = max(1, (int)ceil($totalResults / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;

    $stage = 'fetch_page';
    $users = $db->getAdminUsers($pageSize, $offset, $mobile);
    error_log(sprintf(
        '[adminGetUsers:%s] success admin_id=%d returned=%d total_users=%d total_results=%d page=%d/%d',
        $requestId,
        (int)$admin['id'],
        count($users),
        $totalUsers,
        $totalResults,
        $page,
        $totalPages
    ));

    json_response([
        'error' => false,
        'users' => $users,
        'total_users' => $totalUsers,
        'total_results' => $totalResults,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => $totalPages,
    ]);
} catch (Throwable $e) {
    error_log(sprintf(
        "[adminGetUsers:%s] FAILED stage=%s page=%d page_size=%d exception=%s message=%s file=%s line=%d\nTrace:\n%s",
        $requestId,
        $stage,
        $page,
        $pageSize,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    error_response('خطای داخلی هنگام دریافت کاربران. کد پیگیری: ' . $requestId, 500);
}
