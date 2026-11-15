<?php
/**
 * API: Get Mechanics and Remaining Available Slots for a Given Date
 * 
 * Query parameters:
 *  - date (string, YYYY-MM-DD): The target appointment date
 *  - exclude_appointment_id (int, optional): Appointment ID to exclude from booked count (used when editing)
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDbConnection();

    $dateStr = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    $excludeId = isset($_GET['exclude_appointment_id']) ? (int)$_GET['exclude_appointment_id'] : 0;

    // Validate date format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid date format. Expected YYYY-MM-DD.'
        ], 400);
    }

    $d = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$d || $d->format('Y-m-d') !== $dateStr) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid calendar date provided.'
        ], 400);
    }

    // Query 5 mechanics and count active appointments on the given date
    // If exclude_appointment_id is passed, exclude that appointment from the count
    $sql = "
        SELECT 
            m.id,
            m.name,
            m.specialty,
            m.max_daily_slots,
            m.status,
            COALESCE(app_count.booked_slots, 0) AS booked_slots,
            GREATEST(0, m.max_daily_slots - COALESCE(app_count.booked_slots, 0)) AS available_slots
        FROM mechanics m
        LEFT JOIN (
            SELECT 
                mechanic_id, 
                COUNT(*) AS booked_slots
            FROM appointments
            WHERE appointment_date = :app_date
              AND status != 'cancelled'
              " . ($excludeId > 0 ? "AND id != :exclude_id" : "") . "
            GROUP BY mechanic_id
        ) app_count ON m.id = app_count.mechanic_id
        WHERE m.status = 'active'
        ORDER BY m.id ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':app_date', $dateStr, PDO::PARAM_STR);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $mechanics = $stmt->fetchAll();

    // Map each mechanic with helper flags
    $formattedMechanics = array_map(function($mech) {
        $max = (int)$mech['max_daily_slots'];
        $booked = (int)$mech['booked_slots'];
        $available = (int)$mech['available_slots'];

        return [
            'id'              => (int)$mech['id'],
            'name'            => $mech['name'],
            'specialty'       => $mech['specialty'],
            'max_daily_slots' => $max,
            'booked_slots'    => $booked,
            'available_slots' => $available,
            'is_full'         => ($available <= 0)
        ];
    }, $mechanics);

    sendJsonResponse([
        'success'   => true,
        'date'      => $dateStr,
        'mechanics' => $formattedMechanics
    ]);

} catch (Throwable $e) {
    error_log('Error in get_mechanics_availability: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Unable to fetch mechanics availability. ' . $e->getMessage()
    ], 500);
}
