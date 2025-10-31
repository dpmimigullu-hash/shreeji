<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_GET['branch_id']) || !is_numeric($_GET['branch_id'])) {
    echo json_encode([]);
    exit;
}

$branch_id = (int)$_GET['branch_id'];

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'supervisor' AND branch_id = ? ORDER BY name");
    $stmt->execute([$branch_id]);
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($supervisors);
} catch (PDOException $e) {
    echo json_encode([]);
}

