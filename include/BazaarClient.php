<?php
declare(strict_types=1);

/**
 * ============================================================
 * BazaarClient — اتصال به API توسعه‌دهندگان بازار (v2)
 * ------------------------------------------------------------
 * - احراز هویت OAuth2 با refresh_token (access_token خودکار
 *   در هر درخواست تازه می‌شود)
 * - اعتبارسنجی خرید درون‌برنامه‌ای از سمت سرور
 *
 * پیش‌نیاز: اکستنشن cURL (در اکثر هاست‌ها فعال است)
 *
 * تنظیمات را در include/config.php وارد کنید:
 *   BAZAAR_CLIENT_ID, BAZAAR_CLIENT_SECRET, BAZAAR_REFRESH_TOKEN
 *
 * نحوه دریافت:
 *   ۱) در پنل: https://pishkhan.cafebazaar.ir/settings/api
 *      یک Client بسازید (یک Redirect URI دلخواه بدهید)
 *   ۲) آدرس authorize را در مرورگر باز کنید، Allow بزنید،
 *      کد code را بگیرید
 *   ۳) یک‌بار POST به auth/token/ برای دریافت refresh_token
 * ============================================================
 */
class BazaarClient
{
    private const API_BASE       = 'https://pardakht.cafebazaar.ir/devapi/v2';
    private const TOKEN_ENDPOINT = self::API_BASE . '/auth/token/';

    /** @var string */
    private $clientId;
    /** @var string */
    private $clientSecret;
    /** @var string */
    private $refreshToken;
    /** @var string|null */
    private $cachedToken = null;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
    }

    /**
     * یک access_token معتبر برمی‌گرداند (در هر درخواست فقط یک‌بار refresh می‌شود)
     */
    public function accessToken(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $resp = $this->postForm(self::TOKEN_ENDPOINT, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ]);

        if (isset($resp['access_token'])) {
            return $this->cachedToken = $resp['access_token'];
        }

        throw new RuntimeException(
            'Bazaar: دریافت access_token ناموفق: ' . json_encode($resp, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * وضعیت یک خرید درون‌برنامه‌ای
     *
     * @return array جزئیات خرید؛ مهم‌ترین فیلد:
     *   purchaseState → 0 = خریداری‌شده | 1 = لغو‌شده | 2 = برگشت‌خورده
     */
    public function getPurchaseStatus(
        string $packageName,
        string $productId,
        string $purchaseToken
    ): array {
        $url = self::API_BASE . '/api/validate/'
            . rawurlencode($packageName)
            . '/inapp/' . rawurlencode($productId)
            . '/purchases/' . rawurlencode($purchaseToken) . '/'
            . '?access_token=' . rawurlencode($this->accessToken());

        $resp = $this->getJson($url);

        if (isset($resp['error'])) {
            throw new RuntimeException(
                'Bazaar API error: ' . ($resp['error_description'] ?? $resp['error'])
            );
        }

        return $resp;
    }

    // ================== ابزارهای HTTP ==================

    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Bazaar: خطای ارتباط: ' . $err);
        }

        return json_decode((string)$body, true) ?: [];
    }

    private function getJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Bazaar: خطای ارتباط: ' . $err);
        }

        return json_decode((string)$body, true) ?: [];
    }
}
