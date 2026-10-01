<?php
declare(strict_types=1);

/**
 * لیست شعرهای دارای سبک (ویس) — 🎧 ویژه نسخه پرو
 * خروجی: هر آیتم شامل subject, answer, content, audio_url, ...
 */
require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

$user = require_premium();

$db   = new DB_Functions();
$data = $db->getPoemsWithAudio((int)$user['id']);

json_response([
    'error'     => false,
    'error_msg' => 'اطلاعات یافت شد',
    'data'      => $data,
]);
