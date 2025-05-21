<?php
session_start();
require_once './Data/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$order_id = $_POST['order_id'] ?? '';
$status = $_POST['status'] ?? '';

$stmt = $conn->prepare("UPDATE orders SET status = ? 
                       WHERE order_id = ? AND customer_id = ?");
$stmt->bind_param("sss", $status, $order_id, $_SESSION['customer_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>