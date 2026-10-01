<?php
declare(strict_types=1);

/**
 * اتصال واحد PDO به دیتابیس (Singleton)
 * هر درخواست فقط یک اتصال می‌سازد.
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // Prepared Statement واقعی
                PDO::ATTR_PERSISTENT         => false,
            ]);
        }

        return self::$pdo;
    }
}
