<?php
/**
 * Database Configuration and Connection Handler
 * Car Workshop Appointment System
 */

// Database credentials - customize as needed or set via environment variables
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'car_workshop_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Business rules configuration
define('MAX_DAILY_SLOTS_PER_MECHANIC', 4);
define('TOTAL_MECHANICS_COUNT', 5);

/**
 * Returns a singleton PDO database connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Provide clean error message in API or web context
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please ensure MySQL is running and the database is configured. Error: ' . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Sends a structured JSON response and terminates script.
 *
 * @param array $data
 * @param int $statusCode
 * @return void
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sanitizes input string to prevent XSS and strip unwanted whitespace.
 *
 * @param mixed $input
 * @return string
 */
function sanitizeString($input): string {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}
