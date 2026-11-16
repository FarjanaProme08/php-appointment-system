<?php
/**
 * API: Book Appointment
 * Method: POST
 * 
 * Validates user input, checks capacity limits, prevents duplicate bookings,
 * and saves appointment into database using transactions.
 */

require_once __DIR__ . '/../config/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed. Please use POST.'
    ], 405);
}

// Support both form-urlencoded and JSON input
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? [];
} else {
    $inputData = $_POST;
}

// Extract and sanitize inputs
$clientName     = trim($inputData['client_name'] ?? '');
$clientAddress  = trim($inputData['client_address'] ?? '');
$clientPhone    = trim($inputData['client_phone'] ?? '');
$carLicenseNo   = trim($inputData['car_license_no'] ?? '');
$carEngineNo    = trim($inputData['car_engine_no'] ?? '');
$appointmentDate = trim($inputData['appointment_date'] ?? '');
$mechanicId     = isset($inputData['mechanic_id']) ? (int)$inputData['mechanic_id'] : 0;
$notes          = trim($inputData['notes'] ?? '');

$errors = [];

// 1. Prevent Empty Fields Validation
if (empty($clientName)) {
    $errors['client_name'] = 'Full Name is required.';
} elseif (mb_strlen($clientName) < 2 || mb_strlen($clientName) > 150) {
    $errors['client_name'] = 'Full Name must be between 2 and 150 characters.';
}

if (empty($clientAddress)) {
    $errors['client_address'] = 'Address is required.';
}

if (empty($clientPhone)) {
    $errors['client_phone'] = 'Phone number is required.';
} else {
    if (!ctype_digit($clientPhone)) {
        $errors['client_phone'] = 'Phone number must contain numbers only.';
    } elseif (strlen($clientPhone) < 7 || strlen($clientPhone) > 15) {
        $errors['client_phone'] = 'Phone number must be between 7 and 15 digits.';
    }
}

if (empty($carLicenseNo)) {
    $errors['car_license_no'] = 'Car License Number is required.';
} elseif (mb_strlen($carLicenseNo) < 2 || mb_strlen($carLicenseNo) > 50) {
    $errors['car_license_no'] = 'Car License Number must be between 2 and 50 characters.';
}

if (empty($carEngineNo)) {
    $errors['car_engine_no'] = 'Car Engine Number is required.';
} else {
    if (!ctype_digit($carEngineNo)) {
        $errors['car_engine_no'] = 'Car Engine Number must contain numbers only.';
    } elseif (strlen($carEngineNo) < 4 || strlen($carEngineNo) > 50) {
        $errors['car_engine_no'] = 'Car Engine Number must be between 4 and 50 digits.';
    }
}

// 2. Date Validation
if (empty($appointmentDate)) {
    $errors['appointment_date'] = 'Appointment Date is required.';
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
        $errors['appointment_date'] = 'Appointment Date must be in YYYY-MM-DD format.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        $today = new DateTime('today');
        
        if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
            $errors['appointment_date'] = 'Invalid appointment calendar date.';
        } elseif ($dateObj < $today) {
            $errors['appointment_date'] = 'Appointment date cannot be in the past.';
        }
    }
}

// 3. Mechanic Selection Validation
if ($mechanicId <= 0) {
    $errors['mechanic_id'] = 'Please select a valid mechanic.';
}

if (!empty($errors)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Please correct the validation errors in the form.',
        'errors'  => $errors
    ], 422);
}

try {
    $db = getDbConnection();
    $forUpdate = (getDbDriver() === 'mysql') ? 'FOR UPDATE' : '';

    // Start Transaction
    $db->beginTransaction();

    // Verify mechanic exists and is active
    $mechStmt = $db->prepare("SELECT id, name, max_daily_slots, status FROM mechanics WHERE id = :id {$forUpdate}");
    $mechStmt->execute([':id' => $mechanicId]);
    $mechanic = $mechStmt->fetch();

    if (!$mechanic || $mechanic['status'] !== 'active') {
        $db->rollBack();
        sendJsonResponse([
            'success' => false,
            'message' => 'Selected mechanic does not exist or is currently inactive.',
            'errors'  => ['mechanic_id' => 'Selected mechanic is unavailable.']
        ], 404);
    }

    $maxDailySlots = (int)$mechanic['max_daily_slots'];

    // 4. Rule: Prevent Duplicate Bookings
    // "Clients cannot book more than once on the same date."
    $dupCheckStmt = $db->prepare("
        SELECT id, client_name, client_phone, car_license_no 
        FROM appointments 
        WHERE appointment_date = :app_date 
          AND status != 'cancelled'
          AND (client_phone = :phone OR car_license_no = :license)
        LIMIT 1
        {$forUpdate}
    ");
    $dupCheckStmt->execute([
        ':app_date' => $appointmentDate,
        ':phone'    => $clientPhone,
        ':license'  => $carLicenseNo
    ]);
    $existingClientBooking = $dupCheckStmt->fetch();

    if ($existingClientBooking) {
        $db->rollBack();
        $reason = ($existingClientBooking['client_phone'] === $clientPhone)
            ? "phone number ({$clientPhone})"
            : "car license ({$carLicenseNo})";

        sendJsonResponse([
            'success' => false,
            'message' => "Duplicate booking error: You already have an appointment scheduled for {$appointmentDate} with this {$reason}. Clients are limited to one booking per day.",
            'errors'  => [
                'appointment_date' => 'A booking already exists for this client/vehicle on this date.'
            ]
        ], 409);
    }

    // 5. Rule: Enforce Capacity (Max 4 appointments per mechanic per day)
    $capacityStmt = $db->prepare("
        SELECT COUNT(*) AS booked_count 
        FROM appointments 
        WHERE mechanic_id = :mechanic_id 
          AND appointment_date = :app_date 
          AND status != 'cancelled'
        {$forUpdate}
    ");
    $capacityStmt->execute([
        ':mechanic_id' => $mechanicId,
        ':app_date'    => $appointmentDate
    ]);
    $bookedCount = (int)$capacityStmt->fetchColumn();

    if ($bookedCount >= $maxDailySlots) {
        $db->rollBack();
        sendJsonResponse([
            'success' => false,
            'message' => "Capacity reached: {$mechanic['name']} already has the maximum of {$maxDailySlots} appointments on {$appointmentDate}. Please choose another mechanic or date.",
            'errors'  => [
                'mechanic_id' => "This mechanic is fully booked on {$appointmentDate}."
            ]
        ], 409);
    }

    // 6. Insert Appointment
    $insertStmt = $db->prepare("
        INSERT INTO appointments 
        (client_name, client_address, client_phone, car_license_no, car_engine_no, appointment_date, mechanic_id, notes, status)
        VALUES 
        (:client_name, :client_address, :client_phone, :car_license_no, :car_engine_no, :appointment_date, :mechanic_id, :notes, 'confirmed')
    ");

    $insertStmt->execute([
        ':client_name'      => $clientName,
        ':client_address'   => $clientAddress,
        ':client_phone'     => $clientPhone,
        ':car_license_no'   => $carLicenseNo,
        ':car_engine_no'    => $carEngineNo,
        ':appointment_date' => $appointmentDate,
        ':mechanic_id'      => $mechanicId,
        ':notes'            => $notes ?: null
    ]);

    $newAppointmentId = (int)$db->lastInsertId();

    // Commit Transaction
    $db->commit();

    $remainingSlots = $maxDailySlots - ($bookedCount + 1);

    sendJsonResponse([
        'success' => true,
        'message' => "Appointment confirmed successfully! Your booking ID is #{$newAppointmentId}.",
        'appointment' => [
            'id'               => $newAppointmentId,
            'client_name'      => $clientName,
            'client_phone'     => $clientPhone,
            'car_license_no'   => $carLicenseNo,
            'appointment_date' => $appointmentDate,
            'mechanic_id'      => $mechanicId,
            'mechanic_name'    => $mechanic['name'],
            'remaining_slots'  => max(0, $remainingSlots)
        ]
    ], 201);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error booking appointment: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to process appointment booking: ' . $e->getMessage()
    ], 500);
}
