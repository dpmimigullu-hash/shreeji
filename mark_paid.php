<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || getUserById($_SESSION['user_id'])['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: billing.php');
    exit;
}

$bill_id = (int)$_GET['id'];

$conn = getDBConnection();

// Update bill status to paid
$stmt = $conn->prepare("UPDATE billing SET payment_status = 'paid', payment_date = NOW() WHERE id = ?");
$stmt->execute([$bill_id]);

// Redirect back to billing page with success message
header('Location: billing.php?message=Bill marked as paid successfully');
exit;

