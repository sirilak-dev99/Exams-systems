<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $configFile = dirname(__DIR__) . '/config/database.php';
        $cfg = file_exists($configFile) ? require $configFile : [];

        $host    = $cfg['host'] ?? '127.0.0.1';
        $port    = $cfg['port'] ?? 3307;
        $dbname  = $cfg['dbname'] ?? 'exam_system';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $user    = $cfg['username'] ?? 'root';
        $pass    = $cfg['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        self::$instance = new PDO($dsn, $user, $pass, $options);
        return self::$instance;
    }

    public static function connection(): PDO
    {
        return self::getConnection();
    }
}