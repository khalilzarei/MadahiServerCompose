<?php
// ============================================================
// config.php — تنظیمات سرور
// ------------------------------------------------------------
// ۱) این فایل را با نام config.php کپی کنید
// ۲) رمز واقعی دیتابیس را وارد کنید
// ۳) فایل را بیرون از پوشه عمومی بگذارید (یک پوشه بالاتر از public_html)
//    یا اگر نشد، در همین پوشه include/
// ۴) هرگز این فایل را در گیت کامیت نکنید
// ============================================================

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = '';
const DB_USER = '';
const DB_PASS = '';


// آدرس پایه اپ (بدون اسلش انتهایی)
const APP_BASE_URL = 'https://madahinote.ir';

// 'production' یا 'development' (نمایش خطاها فقط در development)
const APP_ENV = 'production';

// ============================================================
// خرید درون‌برنامه‌ای بازار (Developer API v2)
// ------------------------------------------------------------
// ۱) در https://pishkhan.cafebazaar.ir/settings/api یک Client بسازید
// ۲) client_id و client_secret را اینجا وارد کنید
// ۳) refresh_token: یک‌بار از طریق فلو authorize (مستندات BazaarClient.php)
// ۴) package name دقیقاً همان applicationId اپ (com.khz.madahi)
// ۵) productId = شناسه محصول «نسخه پرو» که در پنل پرداخت بازار تعریف می‌کنید
// ============================================================
const BAZAAR_CLIENT_ID       = '';
const BAZAAR_CLIENT_SECRET   = '';
const BAZAAR_REFRESH_TOKEN   = '';
const BAZAAR_PACKAGE_NAME    = '';
const BAZAAR_PRO_PRODUCT_ID  = '';  // SKU محصول نسخه پرو در پنل بازار


// ============================================================
// درگاه پرداخت زرین‌پال (API v4) — کانال دوم (کاربرهای خارج بازار)
// ------------------------------------------------------------
// ۱) merchant_id: از پنل زرین‌پال (36 کاراکتر)
// ۲) مقدار پرو به ریال — قیمت را فقط در سرور تعریف کنید،
//    کلاینت مبلغ نمی‌فرستد (جلوگیری از دستکاری)
// ۳) webhook: آدرس کامل premiumWebhookZarinpal.php روی دامنه شما
// ۴) تست با سنب‌باکس: ZARINPAL_API_BASE = 'https://sandbox.zarinpal.com'
//    (نکته: StartPay سنب‌باکس: https://sandbox.zarinpal.com/pg/StartPay/)
// ============================================================
// const ZARINPAL_API_BASE        = 'https://api.zarinpal.com';   // یا sandbox
const ZARINPAL_API_BASE  = ''; 
const ZARINPAL_MERCHANT_ID     = '';                            // ۳۶ کاراکتر
const ZARINPAL_PRO_PRICE_RIAL  = 500000;                       // ۵۰۰ هزار تومان
const ZARINPAL_WEBHOOK_URL     = '';
const ZARINPAL_REFERRER_URL    = '';        // کاربر بعد از پرداخت اینجا برمی‌گردد
