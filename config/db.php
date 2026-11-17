<?php
/**
 * Database Configuration and Dual-Engine Connection Handler (MySQL / SQLite)
 * Car Workshop Appointment System
 */

// Driver preference: 'auto' (tries MySQL, falls back to SQLite), 'mysql', or 'sqlite'
define('DB_DRIVER_CHOICE', getenv('DB_DRIVER') ?: 'auto');

// MySQL credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'car_workshop_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// SQLite file path
define('SQLITE_FILE', __DIR__ . '/../database/car_workshop.sqlite');

// Business rules configuration
define('MAX_DAILY_SLOTS_PER_MECHANIC', 4);
define('TOTAL_MECHANICS_COUNT', 5);

/**
 * Returns the currently active driver ('mysql' or 'sqlite')
 */
function getDbDriver(): string {
    static $activeDriver = null;
    if ($activeDriver === null) {
        getDbConnection(); // initializes driver
    }
    return $GLOBALS['ACTIVE_DB_DRIVER'] ?? 'mysql';
}

/**
 * Returns a singleton PDO database connection instance.
 * Automatically tries MySQL, and falls back to SQLite if MySQL is unavailable or password-locked.
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $driverChoice = DB_DRIVER_CHOICE;

    // 1. Try MySQL if requested or auto
    if ($driverChoice === 'mysql' || $driverChoice === 'auto') {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 2, // fast timeout for auto-fallback
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $GLOBALS['ACTIVE_DB_DRIVER'] = 'mysql';
            return $pdo;

        } catch (Throwable $mysqlErr) {
            if ($driverChoice === 'mysql') {
                throw new Exception('MySQL connection failed: ' . $mysqlErr->getMessage());
            }
            error_log('MySQL connection unavailable, falling back to SQLite: ' . $mysqlErr->getMessage());
        }
    }

    // 2. Connect to SQLite (Zero-config instant database)
    try {
        $dbDir = dirname(SQLITE_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $dsn = 'sqlite:' . SQLITE_FILE;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

        $GLOBALS['ACTIVE_DB_DRIVER'] = 'sqlite';

        ensureSqliteSchemaInitialized($pdo);

        return $pdo;

    } catch (Throwable $e) {
        throw new Exception('Database initialization failed: ' . $e->getMessage());
    }
}

/**
 * Initializes SQLite tables and seeds mechanics and discount rules if not present
 */
function ensureSqliteSchemaInitialized(PDO $pdo): void {
    // 1. Create Mechanics Table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mechanics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            specialty TEXT NOT NULL DEFAULT 'General Auto Repair',
            max_daily_slots INTEGER NOT NULL DEFAULT 4,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Create Discount Rules Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS discount_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rule_date TEXT NOT NULL UNIQUE,
            summation_value INTEGER NOT NULL,
            discount_percentage REAL NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Create Appointments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            client_address TEXT NOT NULL,
            client_phone TEXT NOT NULL,
            car_license_no TEXT NOT NULL,
            car_engine_no TEXT NOT NULL,
            appointment_date TEXT NOT NULL,
            mechanic_id INTEGER NOT NULL,
            discount_percentage REAL NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            status TEXT NOT NULL DEFAULT 'confirmed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mechanic_id) REFERENCES mechanics (id) ON DELETE RESTRICT
        );
    ");

    // Ensure discount_percentage column exists in case appointments table was created earlier
    $cols = $pdo->query("PRAGMA table_info(appointments)")->fetchAll();
    $hasDiscountCol = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'discount_percentage') {
            $hasDiscountCol = true;
            break;
        }
    }
    if (!$hasDiscountCol) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN discount_percentage REAL NOT NULL DEFAULT 0.00;");
    }

    // 4. Create Indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mechanic_date ON appointments (mechanic_id, appointment_date, status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_phone_date ON appointments (client_phone, appointment_date, status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_license_date ON appointments (car_license_no, appointment_date, status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_discount_rule_date ON discount_rules (rule_date, is_active);");

    // 5. Seed mechanics if empty
    $mechCount = (int)$pdo->query("SELECT COUNT(*) FROM mechanics")->fetchColumn();
    if ($mechCount === 0) {
        $pdo->exec("
            INSERT INTO mechanics (id, name, specialty, max_daily_slots, status) VALUES
            (1, 'Marcus Vance', 'Master Diagnostics & Engine Specialist', 4, 'active'),
            (2, 'Sarah Jenkins', 'Transmission & Drivetrain Expert', 4, 'active'),
            (3, 'David Rodriguez', 'Brake Systems & Suspension Specialist', 4, 'active'),
            (4, 'Elena Rostova', 'Electrical & Hybrid Systems Technician', 4, 'active'),
            (5, 'Kenji Tanaka', 'Performance Tuning & General Maintenance', 4, 'active');
        ");
    }

    // 6. Seed initial discount rules if empty
    $ruleCount = (int)$pdo->query("SELECT COUNT(*) FROM discount_rules")->fetchColumn();
    if ($ruleCount === 0) {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $seedRuleStmt = $pdo->prepare("
            INSERT OR IGNORE INTO discount_rules (rule_date, summation_value, discount_percentage, is_active) VALUES
            (:today, 45, 5.00, 1),
            (:tomorrow, 25, 10.00, 1)
        ");
        $seedRuleStmt->execute([':today' => $today, ':tomorrow' => $tomorrow]);
    }
}

/**
 * Sends a structured JSON response and terminates script.
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
 * Sanitizes input string to prevent XSS.
 */
function sanitizeString($input): string {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}
