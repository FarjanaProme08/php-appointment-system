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
            // If explicit MySQL mode was forced, throw error
            if ($driverChoice === 'mysql') {
                throw new Exception('MySQL connection failed: ' . $mysqlErr->getMessage());
            }
            // In auto mode, record notice and fall back to SQLite below
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

        // Enable foreign key enforcement and WAL mode for performance & concurrency
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

        $GLOBALS['ACTIVE_DB_DRIVER'] = 'sqlite';

        // Auto-initialize schema and 5 mechanics if not present
        ensureSqliteSchemaInitialized($pdo);

        return $pdo;

    } catch (Throwable $e) {
        throw new Exception('Database initialization failed: ' . $e->getMessage());
    }
}

/**
 * Initializes SQLite tables and seeds the 5 mechanics if tables do not exist
 */
function ensureSqliteSchemaInitialized(PDO $pdo): void {
    // Check if mechanics table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='mechanics'");
    $exists = $stmt->fetchColumn();

    if ($exists) {
        return;
    }

    // 1. Create Mechanics Table
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

    // 2. Create Appointments Table
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
            notes TEXT NULL,
            status TEXT NOT NULL DEFAULT 'confirmed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mechanic_id) REFERENCES mechanics (id) ON DELETE RESTRICT
        );
    ");

    // 3. Create Indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mechanic_date ON appointments (mechanic_id, appointment_date, status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_phone_date ON appointments (client_phone, appointment_date, status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_license_date ON appointments (car_license_no, appointment_date, status);");

    // 4. Seed the 5 Master Mechanics
    $pdo->exec("
        INSERT INTO mechanics (id, name, specialty, max_daily_slots, status) VALUES
        (1, 'Marcus Vance', 'Master Diagnostics & Engine Specialist', 4, 'active'),
        (2, 'Sarah Jenkins', 'Transmission & Drivetrain Expert', 4, 'active'),
        (3, 'David Rodriguez', 'Brake Systems & Suspension Specialist', 4, 'active'),
        (4, 'Elena Rostova', 'Electrical & Hybrid Systems Technician', 4, 'active'),
        (5, 'Kenji Tanaka', 'Performance Tuning & General Maintenance', 4, 'active');
    ");

    // 5. Seed initial sample appointments
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $seedStmt = $pdo->prepare("
        INSERT INTO appointments 
        (client_name, client_address, client_phone, car_license_no, car_engine_no, appointment_date, mechanic_id, notes, status)
        VALUES 
        ('Arthur Pendelton', '742 Evergreen Terrace, Springfield', '5550192834', 'ABC-1234', '98451203', :today, 1, 'Periodic 10k mile maintenance', 'confirmed'),
        ('Beatrice Miller', '12 Oak Ridge Blvd, Maplewood', '5550183921', 'XYZ-9876', '45812930', :today, 1, 'Engine check light diagnostic', 'confirmed'),
        ('Charles Sterling', '88 Riverbend Way, Austin', '5550174829', 'TEX-4421', '12093847', :today, 2, 'Transmission fluid flush', 'confirmed'),
        ('Diana Prince', '350 Fifth Ave, Metropolis', '5550165738', 'NY-7800', '88392019', :today, 3, 'Ceramic brake pad replacement', 'confirmed'),
        ('Ethan Hunt', '100 Mission St, San Francisco', '5550156647', 'CA-0077', '73629104', :tomorrow, 1, 'Suspension noise diagnosis', 'confirmed'),
        ('Fiona Gallagher', '211 North Side Lane, Chicago', '5550147556', 'IL-5542', '66201948', :tomorrow, 2, 'Clutch adjustment and inspection', 'confirmed');
    ");

    $seedStmt->execute([':today' => $today, ':tomorrow' => $tomorrow]);
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
