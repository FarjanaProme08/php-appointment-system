<?php
/**
 * API: Get All Appointments (for Admin Panel)
 * 
 * Supports filtering by:
 *  - date (YYYY-MM-DD)
 *  - mechanic_id (int)
 *  - status (confirmed, completed, cancelled)
 *  - search (keyword for client name, phone, or car license)
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDbConnection();

    $dateFilter     = isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']) : null;
    $mechanicFilter = isset($_GET['mechanic_id']) && (int)$_GET['mechanic_id'] > 0 ? (int)$_GET['mechanic_id'] : null;
    $statusFilter   = isset($_GET['status']) && !empty($_GET['status']) ? trim($_GET['status']) : null;
    $search         = isset($_GET['search']) && !empty(trim($_GET['search'])) ? trim($_GET['search']) : null;

    $whereClauses = [];
    $params = [];

    if ($dateFilter) {
        $whereClauses[] = "a.appointment_date = :date_filter";
        $params[':date_filter'] = $dateFilter;
    }

    if ($mechanicFilter) {
        $whereClauses[] = "a.mechanic_id = :mechanic_filter";
        $params[':mechanic_filter'] = $mechanicFilter;
    }

    if ($statusFilter && in_array($statusFilter, ['confirmed', 'completed', 'cancelled'])) {
        $whereClauses[] = "a.status = :status_filter";
        $params[':status_filter'] = $statusFilter;
    }

    if ($search) {
        $whereClauses[] = "(a.client_name LIKE :search OR a.client_phone LIKE :search OR a.car_license_no LIKE :search OR a.car_engine_no LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $sql = "
        SELECT 
            a.id,
            a.client_name,
            a.client_address,
            a.client_phone,
            a.car_license_no,
            a.car_engine_no,
            a.appointment_date,
            a.mechanic_id,
            a.status,
            a.notes,
            a.created_at,
            a.updated_at,
            m.name AS mechanic_name,
            m.specialty AS mechanic_specialty
        FROM appointments a
        JOIN mechanics m ON a.mechanic_id = m.id
        {$whereSql}
        ORDER BY a.appointment_date DESC, a.id DESC
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $appointments = $stmt->fetchAll();

    // Summary statistics for dashboard cards using parameterized today's date
    $todayStr = date('Y-m-d');
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN appointment_date = :today AND status != 'cancelled' THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN appointment_date > :today AND status != 'cancelled' THEN 1 ELSE 0 END) AS upcoming_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM appointments
    ");
    $statsStmt->execute([':today' => $todayStr]);
    $stats = $statsStmt->fetch() ?: [
        'total_count' => 0,
        'today_count' => 0,
        'upcoming_count' => 0,
        'completed_count' => 0
    ];

    sendJsonResponse([
        'success'      => true,
        'driver'       => getDbDriver(),
        'count'        => count($appointments),
        'stats'        => [
            'total'     => (int)$stats['total_count'],
            'today'     => (int)$stats['today_count'],
            'upcoming'  => (int)$stats['upcoming_count'],
            'completed' => (int)$stats['completed_count']
        ],
        'appointments' => $appointments
    ]);

} catch (Throwable $e) {
    error_log('Error in get_appointments: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Unable to fetch appointments: ' . $e->getMessage()
    ], 500);
}
