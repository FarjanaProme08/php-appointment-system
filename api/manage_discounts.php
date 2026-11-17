<?php
/**
 * API: Manage Discount Rules (Admin Discount Management)
 * 
 * Supports:
 *  - GET: List all rules or fetch promo rule for a specific date
 *  - POST (action=save): Add or update a discount rule for a specific date
 *  - POST (action=delete): Remove a discount rule by ID
 */

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDbConnection();

    // 1. GET Request: List all rules or fetch for a date
    if ($method === 'GET') {
        $date = isset($_GET['date']) ? trim($_GET['date']) : null;

        if ($date) {
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                sendJsonResponse(['success' => false, 'message' => 'Invalid date format.'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM discount_rules WHERE rule_date = :rule_date AND is_active = 1 LIMIT 1");
            $stmt->execute([':rule_date' => $date]);
            $rule = $stmt->fetch();

            sendJsonResponse([
                'success' => true,
                'has_promo' => (bool)$rule,
                'rule' => $rule ?: null
            ]);
        }

        // List all rules
        $stmt = $db->query("SELECT * FROM discount_rules ORDER BY rule_date DESC");
        $rules = $stmt->fetchAll();

        sendJsonResponse([
            'success' => true,
            'count'   => count($rules),
            'rules'   => $rules
        ]);
    }

    // 2. POST Request: Save or Delete
    if ($method === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $action = trim($input['action'] ?? 'save');

        // Delete action
        if ($action === 'delete') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid rule ID is required.'], 400);
            }

            $stmt = $db->prepare("DELETE FROM discount_rules WHERE id = :id");
            $stmt->execute([':id' => $id]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Discount rule removed successfully.'
            ]);
        }

        // Save action
        $ruleDate           = trim($input['rule_date'] ?? '');
        $summationValue     = isset($input['summation_value']) ? (int)$input['summation_value'] : -1;
        $discountPercentage = isset($input['discount_percentage']) ? (float)$input['discount_percentage'] : 0.0;
        $isActive           = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $errors = [];
        if (empty($ruleDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ruleDate)) {
            $errors['rule_date'] = 'Valid rule date (YYYY-MM-DD) is required.';
        }

        if ($summationValue < 0 || $summationValue > 45) {
            $errors['summation_value'] = 'Summation value must be between 0 and 45 (sum of 5 digits).';
        }

        if ($discountPercentage <= 0 || $discountPercentage > 100) {
            $errors['discount_percentage'] = 'Discount percentage must be between 1% and 100%.';
        }

        if (!empty($errors)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Validation error while saving discount rule.',
                'errors'  => $errors
            ], 422);
        }

        // Insert or Update rule for this date
        // First check if a rule already exists for this date
        $checkStmt = $db->prepare("SELECT id FROM discount_rules WHERE rule_date = :rule_date");
        $checkStmt->execute([':rule_date' => $ruleDate]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $updateStmt = $db->prepare("
                UPDATE discount_rules 
                SET summation_value = :summation, discount_percentage = :discount, is_active = :active, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':summation' => $summationValue,
                ':discount'  => $discountPercentage,
                ':active'    => $isActive,
                ':id'        => $existingId
            ]);
            $savedId = (int)$existingId;
            $msg = "Discount rule for {$ruleDate} updated successfully.";
        } else {
            $insertStmt = $db->prepare("
                INSERT INTO discount_rules (rule_date, summation_value, discount_percentage, is_active)
                VALUES (:rule_date, :summation, :discount, :active)
            ");
            $insertStmt->execute([
                ':rule_date' => $ruleDate,
                ':summation' => $summationValue,
                ':discount'  => $discountPercentage,
                ':active'    => $isActive
            ]);
            $savedId = (int)$db->lastInsertId();
            $msg = "New discount rule for {$ruleDate} created successfully.";
        }

        sendJsonResponse([
            'success' => true,
            'message' => $msg,
            'rule'    => [
                'id'                  => $savedId,
                'rule_date'           => $ruleDate,
                'summation_value'     => $summationValue,
                'discount_percentage' => $discountPercentage,
                'is_active'           => $isActive
            ]
        ]);
    }

    sendJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);

} catch (Throwable $e) {
    error_log('Error in manage_discounts: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Error handling discount rule: ' . $e->getMessage()
    ], 500);
}
