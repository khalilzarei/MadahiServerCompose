<?php
declare(strict_types=1);

/**
 * ============================================================
 * ZarinpalClient — درگاه پرداخت زرین‌پال (API v4)
 * ------------------------------------------------------------
 * - requestPayment: ساخت درخواست پرداخت → authority
 * - verify: اعتبارسنجی پرداخت (فقط این پاسخ معتبر است؛
 *           بدنه callback را هرگز مستقیم اعتماد نکنید)
 *
 * مستندات: https://www.zarinpal.com/docs/paymentGateway/connectToGateway
 * Sandbox: ZARINPAL_API_BASE = 'https://sandbox.zarinpal.com'
 * ============================================================
 */
class ZarinpalClient
{
    private const PAYMENT_REQUEST_PATH = '/pg/v4/payment/request.json';
    private const VERIFY_PATH          = '/pg/v4/payment/verify.json';

    /** @var string */
    private $apiBase;     // https://api.zarinpal.com | https://sandbox.zarinpal.com
    /** @var string */
    private $merchantId;  // شناسه ۳۶ کاراکتری درگاه

    public function __construct(string $apiBase, string $merchantId)
    {
        $this->apiBase = $apiBase;
        $this->merchantId = $merchantId;
    }

    /**
     * ساخت درخواست پرداخت
     * @return array ['authority' => string, 'pay_url' => string] یا ['error' => string]
     */
    public function requestPayment(int $amount, string $description, string $callbackUrl): array
    {
        $body = [
            'merchant_id'  => $this->merchantId,
            'amount'       => $amount,
            'description'  => $description,
            'callback_url' => $callbackUrl,
        ];

        $raw = $this->postJsonRaw($this->apiBase . self::PAYMENT_REQUEST_PATH, $body);

        if ($raw === null) {
            return ['error' => 'ارتباط با زرین‌پال برقرار نشد'];
        }

        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return ['error' => 'پاسخ نامعتبر از زرین‌پال: ' . mb_substr($raw, 0, 200)];
        }

        error_log('Zarinpal requestPayment raw: ' . $raw);

        // ---------- سازگار با فرمت‌های مختلف پاسخ ----------
        // ۱) v4: {"code":1000, "message":"...", "data":{"authority":"..."}}
        // ۲) سطحی: {"Authority":"..."} یا {"authority":"..."}
        $code = (int)($resp['code'] ?? 0);

        $authority = (string)(
            $resp['data']['authority']
            ?? $resp['authority']
            ?? $resp['Authority']
            ?? ''
        );

        // در فرمت v4 موفقیت = code 1000؛ در فرمت‌های دیگر وجود authority کافی است
        $ok = ($code === 1000) || ($code === 0 && $authority !== '');

        if (!$ok || $authority === '') {
            $message = (string)(
                $resp['message']
                ?? $resp['error']
                ?? $resp['error_description']
                ?? ''
            );
            $detail = $message !== ''
                ? $message
                : mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE), 0, 300);
            return ['error' => 'خطای زرین‌پال (کد ' . $code . '): ' . $detail];
        }

        return [
            'authority' => $authority,
            'pay_url'   => $this->startPayUrl() . $authority,
        ];
    }

    /**
     * آدرس StartPay متناسب با محیط (production/sandbox)
     */
    private function startPayUrl(): string
    {
        $host = parse_url($this->apiBase, PHP_URL_HOST) ?: 'api.zarinpal.com';
        $startHost = ($host === 'api.zarinpal.com') ? 'www.zarinpal.com' : $host;
        return "https://$startHost/pg/StartPay/";
    }

    /**
     * اعتبارسنجی پرداخت (server-to-server)
     * @return array ['success' => bool, 'refId' => ?string, 'error' => ?string]
     */
    public function verify(string $authority, int $amount): array
    {
        $body = [
            'merchant_id' => $this->merchantId,
            'amount'      => $amount,
            'authority'   => $authority,
        ];

        $raw = $this->postJsonRaw($this->apiBase . self::VERIFY_PATH, $body);

        if ($raw === null) {
            return ['success' => false, 'refId' => null, 'error' => 'ارتباط با زرین‌پال برقرار نشد'];
        }

        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return [
                'success' => false,
                'refId'   => null,
                'error'   => 'پاسخ نامعتبر: ' . mb_substr($raw, 0, 200),
            ];
        }

        error_log('Zarinpal verify raw: ' . $raw);

        // ---------- نرمال‌سازی کلیدها (case-insensitive + flatten data.*) ----------
        $flat = array_change_key_case($resp, CASE_LOWER);
        if (isset($flat['data']) && is_array($flat['data'])) {
            foreach ($flat['data'] as $k => $v) {
                $key = strtolower((string)$k);
                if (!array_key_exists($key, $flat)) {
                    $flat[$key] = $v;
                }
            }
        }

        // سازگار با فرمت‌های v3/v4 (سنب‌باکس):
        // code 100 = "Paid" | code 101 = "Verified" | code 1000 = v4 | status 100 = v3
        $code   = (int)($flat['code'] ?? 0);
        $status = (int)($flat['status'] ?? 0);
        $ok     = ($code === 1000) || ($code === 100) || ($code === 101) || ($status === 100);

        if (!$ok) {
            $message = (string)($flat['message'] ?? $flat['errordescription'] ?? '');
            return [
                'success' => false,
                'refId'   => null,
                'error'   => 'پرداخت تأیید نشد (کد ' . max($code, $status) . '): '
                    . ($message !== '' ? $message : mb_substr($raw, 0, 300)),
            ];
        }

        $refId = (string)($flat['refid'] ?? $flat['ref_id'] ?? '');

        return [
            'success' => true,
            'refId'   => $refId !== '' ? $refId : null,
            'error'   => null,
        ];
    }

    // ================== HTTP ==================

    /**
     * POST JSON و برگشتن پاسخ خام (برای debug + سازگاری با فرمت‌های مختلف)
     */
    private function postJsonRaw(string $url, array $body): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            error_log("ZarinpalClient: cURL error: $err");
            return null;
        }

        error_log("ZarinpalClient: POST $url -> HTTP $httpCode");
        return (string)$raw;
    }
}
