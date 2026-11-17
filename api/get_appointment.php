<?php
/**
 * API: Get Single Appointment by ID
 * Method: GET
 * Query parameter: id (int)
 */

require_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid or missing appointment ID.'
    ], 400);
}

try {
    $db = getDbConnection();

    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.client_name,
            a.client_address,
            a.client_phone,
            a.car_license_no,
            a.car_engine_no,
            a.appointment_date,
            a.mechanic_id,
            COALESCE(a.discount_percentage, 0.00) AS discount_percentage,
            a.status,
            a.notes,
            a.created_at,
            m.name AS mechanic_name,
            m.specialty AS mechanic_specialty
        FROM appointments a
        JOIN mechanics m ON a.mechanic_id = m.id
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Appointment not found.'
        ], 404);
    }

    sendJsonResponse([
        'success'     => true,
        'appointment' => $appointment
    ]);

} catch (Throwable $e) {
    error_log('Error in get_appointment: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to fetch appointment: ' . $e->getMessage()
    ], 500);
}
