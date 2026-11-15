<?php
/**
 * API: Delete / Cancel Appointment
 * Method: POST
 */

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? [];
} else {
    $inputData = $_POST;
}

$id = isset($inputData['id']) ? (int)$inputData['id'] : 0;

if ($id <= 0) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Valid appointment ID is required.'
    ], 400);
}

try {
    $db = getDbConnection();
    
    // We can delete the record or mark as cancelled. Here we provide a direct delete
    $stmt = $db->prepare("DELETE FROM appointments WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Appointment not found or already deleted.'
        ], 404);
    }

    sendJsonResponse([
        'success' => true,
        'message' => "Appointment #{$id} has been removed successfully."
    ]);

} catch (Throwable $e) {
    error_log('Error deleting appointment: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to delete appointment: ' . $e->getMessage()
    ], 500);
}
