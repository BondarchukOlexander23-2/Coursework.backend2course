<?php

/**
 * Клас для роботи з базою даних
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [
        'host' => 'localhost',
        'dbname' => 'survey_platform',
        'username' => 'root',
        'password' => '', // Зазвичай пустий пароль для WAMP
        'charset' => 'utf8mb4'
    ];

    /**
     * Отримати підключення до бази даних (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['dbname'],
                    self::$config['charset']
                );

                self::$connection = new PDO(
                    $dsn,
                    self::$config['username'],
                    self::$config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die('Помилка підключення до бази даних: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    /**
     * Виконати запит SELECT та повернути всі результати
     */
    public static function select(string $query, array $params = []): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Виконати запит SELECT та повернути один результат
     */
    public static function selectOne(string $query, array $params = []): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Виконати запит INSERT та повернути ID нового запису
     */
    public static function insert(string $query, array $params = []): int
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Виконати запит UPDATE або DELETE та повернути кількість змінених рядків
     */
    public static function execute(string $query, array $params = []): int
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Перевірити підключення до бази даних
     */
    public static function testConnection(): bool
    {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}