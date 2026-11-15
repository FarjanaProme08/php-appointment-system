<?php
/**
 * API: Update Appointment (Admin Edit Functionality)
 * Method: POST
 * 
 * Allows admin to change Appointment Date and assign a different available mechanic.
 * Enforces:
 *  - Mechanic daily capacity limit (Max 4 per date)
 *  - Duplicate booking check for the client on the new date
 *  - Date and mechanic validity
 */

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed. Please use POST.'
    ], 405);
}

// Support JSON and Form data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? [];
} else {
    $inputData = $_POST;
}

$appointmentId   = isset($inputData['id']) ? (int)$inputData['id'] : 0;
$appointmentDate = trim($inputData['appointment_date'] ?? '');
$mechanicId      = isset($inputData['mechanic_id']) ? (int)$inputData['mechanic_id'] : 0;
$status          = trim($inputData['status'] ?? '');
$notes           = isset($inputData['notes']) ? trim($inputData['notes']) : null;

$errors = [];

if ($appointmentId <= 0) {
    $errors['id'] = 'Valid appointment ID is required.';
}

if (empty($appointmentDate)) {
    $errors['appointment_date'] = 'Appointment Date is required.';
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
        $errors['appointment_date'] = 'Appointment Date must be in YYYY-MM-DD format.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
            $errors['appointment_date'] = 'Invalid appointment calendar date.';
        }
    }
}

if ($mechanicId <= 0) {
    $errors['mechanic_id'] = 'Please select a valid mechanic.';
}

if (!empty($status) && !in_array($status, ['confirmed', 'completed', 'cancelled'])) {
    $errors['status'] = 'Invalid status value.';
}

if (!empty($errors)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Validation failed for update request.',
        'errors'  => $errors
    ], 422);
}

try {
    $db = getDbConnection();
    $db->beginTransaction();

    // 1. Fetch current appointment record
    $appStmt = $db->prepare("SELECT * FROM appointments WHERE id = :id FOR UPDATE");
    $appStmt->execute([':id' => $appointmentId]);
    $currentApp = $appStmt->fetch();

    if (!$currentApp) {
        $db->rollBack();
        sendJsonResponse([
            'success' => false,
            'message' => 'Appointment not found.'
        ], 404);
    }

    // 2. Fetch target mechanic
    $mechStmt = $db->prepare("SELECT id, name, max_daily_slots, status FROM mechanics WHERE id = :id FOR UPDATE");
    $mechStmt->execute([':id' => $mechanicId]);
    $mechanic = $mechStmt->fetch();

    if (!$mechanic || $mechanic['status'] !== 'active') {
        $db->rollBack();
        sendJsonResponse([
            'success' => false,
            'message' => 'Target mechanic does not exist or is inactive.'
        ], 404);
    }

    $maxDailySlots = (int)$mechanic['max_daily_slots'];
    $finalStatus = !empty($status) ? $status : $currentApp['status'];

    // If final status is not cancelled, enforce duplicate booking & capacity constraints
    if ($finalStatus !== 'cancelled') {
        // 3. Rule: Check duplicate booking for the client on the new date
        // Must exclude the current appointment ID!
        $dupStmt = $db->prepare("
            SELECT id, client_name 
            FROM appointments 
            WHERE appointment_date = :app_date 
              AND status != 'cancelled'
              AND id != :current_id
              AND (client_phone = :phone OR car_license_no = :license)
            LIMIT 1
            FOR UPDATE
        ");
        $dupStmt->execute([
            ':app_date'   => $appointmentDate,
            ':current_id' => $appointmentId,
            ':phone'      => $currentApp['client_phone'],
            ':license'    => $currentApp['car_license_no']
        ]);
        $existingOtherBooking = $dupStmt->fetch();

        if ($existingOtherBooking) {
            $db->rollBack();
            sendJsonResponse([
                'success' => false,
                'message' => "Duplicate Booking: This client already has another active appointment (#{$existingOtherBooking['id']}) on {$appointmentDate}. Clients cannot have multiple bookings on the same date.",
                'errors'  => [
                    'appointment_date' => 'Client already has an appointment scheduled on this date.'
                ]
            ], 409);
        }

        // 4. Rule: Enforce Mechanic Capacity (Max 4 per mechanic per day)
        // Count active appointments for the target mechanic on that date, excluding current appointment
        $capStmt = $db->prepare("
            SELECT COUNT(*) AS booked_count 
            FROM appointments 
            WHERE mechanic_id = :mechanic_id 
              AND appointment_date = :app_date 
              AND status != 'cancelled'
              AND id != :current_id
            FOR UPDATE
        ");
        $capStmt->execute([
            ':mechanic_id' => $mechanicId,
            ':app_date'    => $appointmentDate,
            ':current_id'  => $appointmentId
        ]);
        $otherBookedCount = (int)$capStmt->fetchColumn();

        if ($otherBookedCount >= $maxDailySlots) {
            $db->rollBack();
            sendJsonResponse([
                'success' => false,
                'message' => "Capacity limit reached: {$mechanic['name']} already has {$otherBookedCount} appointments on {$appointmentDate} (Maximum is {$maxDailySlots}). Please choose another mechanic or date.",
                'errors'  => [
                    'mechanic_id' => "Mechanic has no available slots left on {$appointmentDate}."
                ]
            ], 409);
        }
    }

    // 5. Update Appointment
    $updateStmt = $db->prepare("
        UPDATE appointments 
        SET 
            appointment_date = :app_date,
            mechanic_id      = :mechanic_id,
            status           = :status,
            notes            = :notes,
            updated_at       = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':app_date'    => $appointmentDate,
        ':mechanic_id' => $mechanicId,
        ':status'      => $finalStatus,
        ':notes'       => ($notes !== null) ? $notes : $currentApp['notes'],
        ':id'          => $appointmentId
    ]);

    $db->commit();

    sendJsonResponse([
        'success' => true,
        'message' => "Appointment #{$appointmentId} updated successfully. Assigned to {$mechanic['name']} on {$appointmentDate}.",
        'appointment' => [
            'id'               => $appointmentId,
            'client_name'      => $currentApp['client_name'],
            'client_phone'     => $currentApp['client_phone'],
            'car_license_no'   => $currentApp['car_license_no'],
            'appointment_date' => $appointmentDate,
            'mechanic_id'      => $mechanicId,
            'mechanic_name'    => $mechanic['name'],
            'status'           => $finalStatus
        ]
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error updating appointment: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to update appointment: ' . $e->getMessage()
    ], 500);
}
