<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/bootstrap.php';
require_method('POST');

// 🎧 افزودن ویس/سبک یک قابلیت نسخه پرو است
$user      = require_premium();
$contentId = (int)($_POST['content_id'] ?? 0);

if ($contentId <= 0) {
    error_response('محتوا مشخص نشده است');
}

$db      = new DB_Functions();
$content = $db->getContentWithUserIdAndId((int)$user['id'], $contentId);

if ($content === null) {
    error_response('محتوا یافت نشد یا دسترسی ندارید');
}

if (!isset($_FILES['audio'])) {
    error_response('فایل صوتی ارسال نشده است');
}

// ترجمه خطاهای آپلود
$uploadErrors = [
    1 => 'حجم فایل از حد مجاز سرور (upload_max_filesize) بیشتر است — مقدار آن در تنظیمات هاست را زیاد کنید',
    2 => 'حجم فایل از حد مجاز سرور بیشتر است — مقدار upload_max_filesize را در هاست زیاد کنید',
    3 => 'فایل ناقص آپلود شد',
    4 => 'فایل صوتی ارسال نشده است',
    6 => 'پوشه موقت سرور در دسترس نیست',
    7 => 'امکان نوشتن فایل روی سرور نیست',
];

if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    error_response($uploadErrors[$_FILES['audio']['error']] ?? 'خطا در آپلود فایل');
}

$file    = $_FILES['audio'];
// فایلِ پیش‌از‌کات می‌تواند بزرگ باشد (مثلاً ۹ مگابایت) — سقف ۱۵ مگابایت
$maxInputSize = 15 * 1024 * 1024;

if ($file['size'] <= 0) {
    error_response('فایل خالی است');
}
if ($file['size'] > $maxInputSize) {
    error_response('حجم فایل ورودی نباید بیشتر از 15 مگابایت باشد');
}

// بررسی پسوند
$allowedExt = ['mp3', 'm4a', 'ogg', 'aac', 'wav', 'mp4'];
$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    error_response('فرمت فایل مجاز نیست (mp3, m4a, ogg, aac, wav)');
}

// بررسی MIME واقعی فایل (نه فقط پسوند)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$isAudio = str_starts_with($mime, 'audio/')
    || ($ext === 'ogg' && $mime === 'application/ogg')
    || in_array($ext, ['m4a', 'mp4', 'aac'], true);

if (!$isAudio) {
    error_response("فایل ارسالی یک فایل صوتی معتبر نیست (MIME: $mime)");
}

// ============================================================
// انتخاب پوشه‌ی آپلود
// ------------------------------------------------------------
// ۱) اولویت: public_html/uploads/songs/
//    → لینک: https://دامنه/uploads/songs/...
// ۲) اگر در دسترس نبود: ریشه‌ی اپ/uploads/songs/
//    → لینک با مسیر عمومی واقعی
// ============================================================
$appRoot = dirname(__DIR__);
$docRoot = dirname($appRoot);
$docUploads = $docRoot . '/uploads/songs';

$useDocRootUploads = false;
if (is_dir($docUploads) && is_writable($docUploads)) {
    $useDocRootUploads = true;
} else {
    @mkdir($docUploads, 0755, true);
    if (is_dir($docUploads) && is_writable($docUploads)) {
        $useDocRootUploads = true;
    }
}

$uploadDir = $useDocRootUploads ? $docUploads . '/' : $appRoot . '/uploads/songs/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    error_response('پوشه آپلود قابل نوشتن نیست');
}

// ============================================================
// دریافت محدوده‌ی کات (اختیاری — از اپ ارسال می‌شود)
// ============================================================
$startSec = isset($_POST['start_sec']) ? (float)$_POST['start_sec'] : -1.0;
$durSec   = isset($_POST['duration_sec']) ? (float)$_POST['duration_sec'] : -1.0;
$needTrim = ($startSec >= 0.0 && $durSec > 0.0);
if ($needTrim && $durSec > 90.0) {
    $durSec = 90.0; // حفاظت: حداکثر ۹۰ ثانیه
}

// ۱) فایل را موقتاً در پوشه‌ی آپلود نگه می‌داریم
$tmpIn = $uploadDir . 'tmp_in_' . bin2hex(random_bytes(6)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $tmpIn)) {
    error_response('خطا در ذخیره فایل');
}

$finalPath = $tmpIn;
$trimMethod = 'none';

if ($needTrim) {
    // ۲) کات: اول با ابزار ffmpeg (اگر روی هاست موجود باشد)،
    //    وگرنه با برش‌زننده‌ی MP3 خالص PHP
    $tmpOut = $uploadDir . 'tmp_out_' . bin2hex(random_bytes(6)) . '.' . $ext;

    $ffmpegPath = ffmpeg_location();
    if ($ffmpegPath !== '') {
        $trimMethod = 'ffmpeg';
        $ok = ffmpeg_trim($ffmpegPath, $tmpIn, $tmpOut, $startSec, $durSec);
    } elseif ($ext === 'mp3') {
        $trimMethod = 'php-mp3';
        $ok = mp3_trim_php($tmpIn, $tmpOut, $startSec, $durSec);
    } else {
        @unlink($tmpIn);
        error_response('برش این فرمت روی این هاست امکان‌پذیر نیست — فایل MP3 انتخاب کن');
    }

    if (!$ok || !is_file($tmpOut) || filesize($tmpOut) <= 0) {
        @unlink($tmpIn);
        if (is_file($tmpOut)) { @unlink($tmpOut); }
        error_response('برش فایل امکان‌پذیر نبود (روش: ' . $trimMethod . ')');
    }

    @unlink($tmpIn);
    $finalPath = $tmpOut;
}

// ۳) خروجی نهایی باید حداکثر ۱ مگابایت باشد
if (filesize($finalPath) > 1048576) {
    @unlink($finalPath);
    error_response('فایل کات‌شده بیشتر از 1 مگابایت است — محدوده‌ی کوتاه‌تری انتخاب کن');
}

// ۴) نام نهایی و غیرقابل حدس
$newName  = 'audio_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $newName;

if (!rename($finalPath, $destPath) && !copy($finalPath, $destPath)) {
    @unlink($finalPath);
    error_response('خطا در ذخیره فایل');
}
@unlink($finalPath);
@chmod($destPath, 0644);

$audioUrl = ($useDocRootUploads ? APP_BASE_URL : (APP_BASE_URL . public_web_root()))
    . '/uploads/songs/' . $newName;

// حذف فایل قبلی اگر سبک قبلی داشته
if (!empty($content['audio_url'])) {
    $db->deleteAudioFile($content['audio_url']);
}

$db->updateContentAudio($contentId, $audioUrl);
$updated = $db->getContentWithUserIdAndId((int)$user['id'], $contentId);

json_response([
    'error'     => false,
    'error_msg' => 'سبک با موفقیت ذخیره شد' . ($needTrim ? " (برش: $trimMethod)" : ''),
    'content'   => $updated,
]);

// ============================================================
// ابزارهای کمکی
// ============================================================

/** مسیر ابزار ffmpeg روی هاست (در صورت موجود بودن) */
function ffmpeg_location(): string
{
    $disabled = (string)ini_get('disable_functions');
    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', $disabled)), true)) {
        $p = @shell_exec('command -v ffmpeg 2>/dev/null || which ffmpeg 2>/dev/null');
        if (is_string($p)) {
            $p = trim($p);
            if ($p !== '' && is_file($p) && is_executable($p)) {
                return $p;
            }
        }
    }
    return '';
}

/** پیشوند عمومی ریشه‌ی اپ نسبت به دامنه (از SCRIPT_NAME استخراج می‌شود) */
function public_web_root(): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    // مثال: REQUEST_URI = /new_api/api/uploadAudio.php → پیشوند = /new_api
    $dir = dirname($scriptName);
    if ($dir === '' || $dir === '.') {
        return '';
    }
    // برداشتن بخش /api از انتها (اسکریپت داخل پوشه‌ی api است)
    if (substr($dir, -4) === '/api') {
        $dir = substr($dir, 0, -4);
    }
    return rtrim($dir, '/');
}

/** کات با ابزار ffmpeg (کپی جریان — سریع و بدون افت کیفیت) */
function ffmpeg_trim(string $ffmpeg, string $inPath, string $outPath, float $startSec, float $durSec): bool
{
    $cmd = escapeshellarg($ffmpeg)
        . ' -y -ss ' . sprintf('%.3f', $startSec)
        . ' -t ' . sprintf('%.3f', $durSec)
        . ' -c copy '
        . escapeshellarg($inPath) . ' ' . escapeshellarg($outPath)
        . ' 2>&1';
    @exec($cmd, $outLines, $code);
    return ($code === 0);
}

/**
 * برش‌زننده‌ی MP3 با PHP خالص (بدون هیچ ابزاری)
 * ------------------------------------------------------------
 * فریم‌های MP3 خودکفا هستند؛ هر فریم با sync word شروع می‌شود
 * و طولش از هدرش محاسبه می‌شود. از این رو:
 *  - فریم‌ها را اسکن می‌کنیم
 *  - تا زمان شروع، رد می‌شوند
 *  - فریم‌ها تا زمان پایان کپی می‌شوند
 * دقت: حدود یک فریم (≈۲۶ میلی‌ثانیه)
 */
function mp3_trim_php(string $inPath, string $outPath, float $startSec, float $durSec): bool
{
    $data = @file_get_contents($inPath);
    if ($data === false || strlen($data) < 8) {
        return false;
    }
    $len = strlen($data);
    $pos = 0;

    // رد کردن هدر ID3v2 (اگر وجود داشته باشد)
    if (substr($data, 0, 3) === 'ID3') {
        $sz = ((ord($data[6]) & 0x7f) << 21)
            | ((ord($data[7]) & 0x7f) << 14)
            | ((ord($data[8]) & 0x7f) << 7)
            | (ord($data[9]) & 0x7f);
        $pos = 10 + $sz;
    }

    // جداول بیت‌ریت و فرکانس نمونه‌گیری (لایه‌ی III)
    $brMpeg1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0];
    $brMpeg2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0];
    $srMpeg1 = [44100, 48000, 32000, 0];
    $srMpeg2 = [22050, 24000, 16000, 0];
    $srMpeg25= [11025, 12000, 8000, 0];

    $endSec = $startSec + $durSec;
    $out    = '';
    $t      = 0.0;
    $skippedFirstVbr = false;

    while ($pos + 4 <= $len) {
        if ((ord($data[$pos]) & 0xFF) !== 0xFF || (ord($data[$pos + 1]) & 0xE0) !== 0xE0) {
            $pos++;
            continue;
        }

        $h1 = ord($data[$pos + 1]);
        $h2 = ord($data[$pos + 2]);

        $version = ($h1 >> 3) & 0x03;   // 3=MPEG1, 2=MPEG2, 0=MPEG2.5
        $layer   = ($h1 >> 1) & 0x03;   // 1=Layer III
        if ($layer !== 1 || $version === 1) {
            $pos++;
            continue;
        }

        $brIdx = ($h2 >> 4) & 0x0F;
        $srIdx = ($h2 >> 2) & 0x03;
        $pad   = ($h2 >> 1) & 0x01;
        if ($brIdx === 0 || $brIdx === 15 || $srIdx === 3) {
            $pos++;
            continue;
        }

        $bitrate    = ($version === 3) ? $brMpeg1[$brIdx] : $brMpeg2[$brIdx];
        $samplerate = ($version === 3) ? $srMpeg1[$srIdx] : (($version === 2) ? $srMpeg2[$srIdx] : $srMpeg25[$srIdx]);
        if ($bitrate === 0 || $samplerate === 0) {
            $pos++;
            continue;
        }

        $frameLen = ($version === 3)
            ? ((int)floor($bitrate * 1000 * 144 / $samplerate) + $pad)
            : ((int)floor($bitrate * 1000 * 72 / $samplerate) + $pad);
        $frameSec = ($version === 3 ? 1152 : 576) / $samplerate;

        if ($frameLen < 8 || $pos + $frameLen > $len) {
            break;
        }

        // فریم اول در فایل‌های VBR ممکن است هدر Xing/Info باشد؛
        // آن را رد می‌کنیم (اطلاعاتش فریم صوتی واقعی نیست)
        if (!$skippedFirstVbr) {
            $skippedFirstVbr = true;
            $t += $frameSec;
            $pos += $frameLen;
            continue;
        }

        if ($t >= $endSec) {
            break;
        }
        if ($t >= $startSec) {
            $out .= substr($data, $pos, $frameLen);
        }

        $t += $frameSec;
        $pos += $frameLen;
    }

    if (strlen($out) < 100) {
        return false;
    }

    return @file_put_contents($outPath, $out) !== false;
}
