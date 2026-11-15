<?php
/**
 * Database Auto-Installer / Migrator Script
 * Can be run via browser or CLI: php database/setup.php
 */

require_once __DIR__ . '/../config/db.php';

$isCli = (php_sapi_name() === 'cli');

$statusMessage = '';
$statusType = 'info';
$details = [];

try {
    // 1. Connect to MySQL server without database selection first
    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
    $rootPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $details[] = "Connected to MySQL host at " . DB_HOST . ":" . DB_PORT;

    // 2. Read schema.sql
    $schemaPath = __DIR__ . '/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception("schema.sql file not found at: {$schemaPath}");
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false || empty(trim($sql))) {
        throw new Exception("schema.sql is empty or could not be read.");
    }

    // 3. Execute SQL statements
    // We execute the multi-query batch
    $rootPdo->exec($sql);
    $details[] = "Executed database schema and table definitions successfully.";

    // 4. Connect to car_workshop_db and verify mechanics table
    $dbPdo = getDbConnection();
    $stmt = $dbPdo->query("SELECT COUNT(*) AS count FROM mechanics");
    $mechanicCount = (int)$stmt->fetchColumn();

    $stmt2 = $dbPdo->query("SELECT COUNT(*) AS count FROM appointments");
    $appointmentCount = (int)$stmt2->fetchColumn();

    $details[] = "Verified mechanics count: {$mechanicCount} (System limit: 5)";
    $details[] = "Verified initial sample appointments: {$appointmentCount}";

    $statusType = 'success';
    $statusMessage = "Database setup completed successfully! The car workshop appointment system is ready to use.";

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
    <title>Database Setup - Car Workshop Appointment System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .setup-container {
            max-width: 680px;
            margin: 60px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 40px;
            border: 1px solid #e2e8f0;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .status-success { background: #dcfce7; color: #166534; }
        .status-danger { background: #fee2e2; color: #991b1b; }
        .log-box {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 10px;
            padding: 16px 20px;
            font-family: monospace;
            font-size: 0.9rem;
            margin: 24px 0;
            overflow-x: auto;
        }
        .log-item { margin: 6px 0; }
        .btn-group { display: flex; gap: 12px; margin-top: 24px; }
    </style>
</head>
<body class="bg-light">
    <div class="setup-container">
        <h2>Database Setup Wizard</h2>
        <p class="text-muted">Car Workshop Appointment Management System</p>
        <hr class="my-4">

        <span class="status-badge status-<?= $statusType ?>">
            <?= $statusType === 'success' ? 'Setup Complete' : 'Setup Failed' ?>
        </span>

        <p><strong><?= htmlspecialchars($statusMessage) ?></strong></p>

        <div class="log-box">
            <?php foreach ($details as $log): ?>
                <div class="log-item">&gt; <?= htmlspecialchars($log) ?></div>
            <?php endforeach; ?>
        </div>

        <?php if ($statusType === 'success'): ?>
            <div class="btn-group">
                <a href="../index.php" class="btn btn-primary">Go to Booking Portal</a>
                <a href="../admin.php" class="btn btn-secondary">Go to Admin Panel</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" style="background:#fffbeb;border:1px solid #fef3c7;padding:15px;border-radius:8px;">
                <strong>Configuration Note:</strong>
                <p style="margin:8px 0 0;font-size:0.9rem;">
                    Please check your database credentials in <code>config/db.php</code>. If MySQL has a custom root password or port, set them in <code>config/db.php</code> or via environment variables, then refresh this page.
                </p>
            </div>
            <div class="btn-group">
                <a href="setup.php" class="btn btn-primary">Retry Setup</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
