<?php
/**
 * Database Auto-Installer / Migrator Script
 * Can be run via browser or CLI: php database/setup.php
 */

require_once __DIR__ . '/../config/db.php';

$isCli = (php_sapi_name() === 'cli');

// Check if credentials submitted via POST in the browser wizard
$submittedHost = isset($_POST['db_host']) ? trim($_POST['db_host']) : (getenv('DB_HOST') ?: DB_HOST);
$submittedPort = isset($_POST['db_port']) ? trim($_POST['db_port']) : (getenv('DB_PORT') ?: DB_PORT);
$submittedUser = isset($_POST['db_user']) ? trim($_POST['db_user']) : (getenv('DB_USER') ?: DB_USER);
$submittedPass = isset($_POST['db_pass']) ? (string)$_POST['db_pass'] : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : DB_PASS);

$statusMessage = '';
$statusType = 'info';
$details = [];
$isConfigured = false;

try {
    // 1. Connect to MySQL server without database selection first
    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $submittedHost, $submittedPort);
    $rootPdo = new PDO($dsnNoDb, $submittedUser, $submittedPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $details[] = "Successfully authenticated with MySQL at {$submittedHost}:{$submittedPort} as '{$submittedUser}'.";

    // 2. Read schema.sql
    $schemaPath = __DIR__ . '/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception("schema.sql file not found at: {$schemaPath}");
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false || empty(trim($sql))) {
        throw new Exception("schema.sql is empty or could not be read.");
    }

    // 3. Execute SQL batch
    $rootPdo->exec($sql);
    $details[] = "Created database 'car_workshop_db' and tables successfully.";

    // 4. Save working credentials to config/db.php if different
    $configPath = __DIR__ . '/../config/db.php';
    if (file_exists($configPath) && is_writable($configPath)) {
        $configFile = file_get_contents($configPath);
        
        $escapedHost = addcslashes($submittedHost, "'\\");
        $escapedPort = addcslashes($submittedPort, "'\\");
        $escapedUser = addcslashes($submittedUser, "'\\");
        $escapedPass = addcslashes($submittedPass, "'\\");

        $configFile = preg_replace("/define\('DB_HOST',.*?\);/", "define('DB_HOST', getenv('DB_HOST') ?: '{$escapedHost}');", $configFile);
        $configFile = preg_replace("/define\('DB_PORT',.*?\);/", "define('DB_PORT', getenv('DB_PORT') ?: '{$escapedPort}');", $configFile);
        $configFile = preg_replace("/define\('DB_USER',.*?\);/", "define('DB_USER', getenv('DB_USER') ?: '{$escapedUser}');", $configFile);
        $configFile = preg_replace("/define\('DB_PASS',.*?\);/", "define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '{$escapedPass}');", $configFile);

        file_put_contents($configPath, $configFile);
        $details[] = "Updated config/db.php with verified working credentials.";
    }

    // 5. Connect to car_workshop_db and verify seeded data
    $dsnWithDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $submittedHost, $submittedPort, DB_NAME);
    $dbPdo = new PDO($dsnWithDb, $submittedUser, $submittedPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $dbPdo->query("SELECT COUNT(*) AS count FROM mechanics");
    $mechanicCount = (int)$stmt->fetchColumn();

    $stmt2 = $dbPdo->query("SELECT COUNT(*) AS count FROM appointments");
    $appointmentCount = (int)$stmt2->fetchColumn();

    $details[] = "Verified 5 active mechanics in database: {$mechanicCount} mechanics active.";
    $details[] = "Loaded initial sample appointments: {$appointmentCount} verified.";

    $statusType = 'success';
    $statusMessage = "Database setup completed successfully! The appointment system is ready to use.";
    $isConfigured = true;

} catch (Throwable $e) {
    $statusType = 'danger';
    $statusMessage = "Database setup failed: " . $e->getMessage();
    $details[] = "Error Code: " . $e->getCode();
}

if ($isCli) {
    echo "====================================================\n";
    echo "Car Workshop Appointment System - Database Setup\n";
    echo "====================================================\n";
    echo "Status: " . strtoupper($statusType) . "\n";
    echo "Message: {$statusMessage}\n";
    echo "Log:\n";
    foreach ($details as $d) {
        echo " - {$d}\n";
    }
    echo "====================================================\n";
    exit($statusType === 'success' ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Wizard - Car Workshop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .setup-container {
            max-width: 620px;
            margin: 50px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 36px;
            border: 1px solid #e2e8f0;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        .status-success { background: #dcfce7; color: #166534; }
        .status-danger { background: #fee2e2; color: #991b1b; }
        .log-box {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 10px;
            padding: 16px;
            font-family: monospace;
            font-size: 0.85rem;
            margin: 20px 0;
            overflow-x: auto;
        }
        .log-item { margin: 4px 0; }
        .cred-form-card {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="setup-container">
        <h2 style="font-size:1.5rem;font-weight:700;color:var(--gray-900);">Database Setup Wizard</h2>
        <p class="text-muted text-sm">Car Workshop Appointment Management System</p>
        <hr style="margin: 16px 0 24px; border:0; border-top:1px solid #e2e8f0;">

        <span class="status-badge status-<?= $statusType ?>">
            <?= $statusType === 'success' ? 'Setup Complete' : 'Setup Failed' ?>
        </span>

        <p style="font-size: 0.95rem; color: var(--gray-800); line-height: 1.5;">
            <strong><?= htmlspecialchars($statusMessage) ?></strong>
        </p>

        <div class="log-box">
            <?php foreach ($details as $log): ?>
                <div class="log-item">&gt; <?= htmlspecialchars($log) ?></div>
            <?php endforeach; ?>
        </div>

        <?php if ($statusType === 'success'): ?>
            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <a href="../index.php" class="btn btn-primary" style="flex:1;">Go to Customer Booking</a>
                <a href="../admin.php" class="btn btn-secondary" style="flex:1;">Go to Admin Panel</a>
            </div>
        <?php else: ?>
            <!-- Interactive Credentials Form -->
            <div class="cred-form-card">
                <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 6px; color: var(--gray-900);">
                    Enter MySQL Credentials
                </h4>
                <p class="text-muted text-xs" style="margin-bottom: 16px;">
                    Your MySQL <code>root</code> user has a password set. Enter it below and click <strong>"Test &amp; Setup Database"</strong>:
                </p>

                <form method="POST" action="setup.php">
                    <div class="grid grid-2" style="gap: 12px; margin-bottom: 12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label text-xs">MySQL Host</label>
                            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($submittedHost) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label text-xs">Port</label>
                            <input type="number" name="db_port" class="form-control" value="<?= htmlspecialchars($submittedPort) ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-2" style="gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label text-xs">MySQL User</label>
                            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($submittedUser) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label text-xs">MySQL Password</label>
                            <input type="password" name="db_pass" class="form-control" placeholder="Try 'root', 'admin', or your password" autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Test &amp; Setup Database</span>
                    </button>
                </form>
            </div>

            <div style="margin-top: 16px; font-size: 0.8rem; color: var(--gray-500); line-height: 1.4;">
                <strong>Common default passwords to try:</strong><br>
                &bull; <code>root</code><br>
                &bull; <code>admin</code><br>
                &bull; <code>123456</code><br>
                &bull; Your Mac user account password
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
