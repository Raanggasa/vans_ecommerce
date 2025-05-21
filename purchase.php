<?php
session_start();
require_once './Data/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda belum login.']);
    exit();
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_POST['product_id'] ?? '';

if (empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak valid.']);
    exit();
}

$conn->begin_transaction();

try {
    // Lock produk dan cek stok
    $stmt = $conn->prepare("SELECT price, stock_quantity FROM products WHERE product_id = ? FOR UPDATE");
    $stmt->bind_param("s", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Produk tidak ditemukan.");
    }

    $product = $result->fetch_assoc();
    if ($product['stock_quantity'] < 1) {
        throw new Exception("Stok produk habis.");
    }

    // Insert order menggunakan UUID dari MySQL
    $stmt = $conn->prepare("INSERT INTO orders (order_id, customer_id, product_id, total_amount, status) 
                           VALUES (UUID(), ?, ?, ?, 'pending')");
    $stmt->bind_param("ssd", $customer_id, $product_id, $product['price']);
    $stmt->execute();

    // Update stok produk
    $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - 1 WHERE product_id = ?");
    $stmt->bind_param("s", $product_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembelian berhasil!']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}