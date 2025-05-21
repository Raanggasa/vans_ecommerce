<?php
// Aktifkan pelaporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi
session_start();

// CEK AUTENTIKASI ADMIN
if (!isset($_SESSION['admin_id'])) {
    if (isset($_COOKIE['admin_id']) && isset($_COOKIE['username'])) {
        $_SESSION['admin_id'] = $_COOKIE['admin_id'];
        $_SESSION['username'] = $_COOKIE['username'];
    } else {
        header("Location: ../Admin/index.php");
        exit;
    }
}

// INCLUDE DEPENDENSI
include '../include/sidebar.php';  // Navigasi sidebar admin
include '../Data/db_connect.php';  // Koneksi database

// Validasi koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// INISIALISASI VARIABEL
$orders = [];
$error = null;
$total_products = 0;
$total_orders = 0;
$revenue = 0;
$total_active_users = 0;

try {
    // AMBIL PESANAN TERBARU (8 TERAKHIR) BESERTA PRODUK
    $order_query = $conn->query("
        SELECT o.order_id, 
               o.order_date, 
               o.total_amount, 
               o.status, 
               c.name AS customer_name,
               p.name AS product_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN products p ON o.product_id = p.product_id
        ORDER BY o.order_date DESC
        LIMIT 8
    ");

    if ($order_query) {
        while ($order = $order_query->fetch_assoc()) {
            $orders[] = $order;
        }
        $order_query->free();
    } else {
        throw new Exception("Gagal mengambil data pesanan: " . $conn->error);
    }

    // AMBIL TOTAL PRODUK
    $result = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    if ($result && $row = $result->fetch_assoc()) {
        $total_products = $row['total_products'];
    }

    // AMBIL TOTAL PESANAN (HANYA YANG ADA RELASI CUSTOMER & PRODUCT)
    $result = $conn->query("
        SELECT COUNT(*) AS total_orders 
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN products p ON o.product_id = p.product_id
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $total_orders = $row['total_orders'];
    }

    // AMBIL TOTAL PENDAPATAN (HANYA YANG ADA RELASI CUSTOMER & PRODUCT + STATUS VALID)
    $result = $conn->query("
        SELECT SUM(o.total_amount) AS revenue 
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN products p ON o.product_id = p.product_id
        WHERE o.status IN ('paid', 'shipped', 'completed')
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $revenue = $row['revenue'] ?? 0;
    }

    // AMBIL TOTAL PENGGUNA
    $result = $conn->query("SELECT COUNT(*) AS total_users FROM customers");
    if ($result && $row = $result->fetch_assoc()) {
        $total_active_users = $row['total_users'];
    }

} catch (Exception $e) {
    $error = htmlspecialchars($e->getMessage());
} finally {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Vans</title>
    
    <!-- LINK CSS EKSTERNAL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../Assets/Vans-logo.svg" type="image/x-icon">
    
    <!-- STYLE KUSTOM -->
    <style>
    :root {
        --primary-color: #121212;
        --secondary-color: #ffffff;
        --accent-color: #ef4444;

        /* Warna status */
        --status-completed-bg: #dcfce7;
        --status-completed-text: #166534;

        --status-pending-bg: #fef9c3;
        --status-pending-text: #854d0e;

        --status-paid-bg: #dbeafe;
        --status-paid-text: #1e40af;

        --status-shipped-bg: #e0f2fe;
        --status-shipped-text: #0369a1;

        --status-cancelled-bg: #fee2e2;
        --status-cancelled-text: #b91c1c;
    }

    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow-x: hidden;
        background-color: #f8f9fa;
        font-family: 'Segoe UI', sans-serif;
    }

    .main-content {
        margin-left: 210px;
        padding: 1.5rem 1.5rem 1.5rem 1.5rem;
        padding-top: 90px;
        transition: margin-left 0.3s;
        min-height: 100vh;
        width: calc(100% - 220px);
        box-sizing: border-box;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 80px 1rem 1rem;
        }
    }

    /* Card Dashboard */
    .dashboard-card {
        background: var(--secondary-color);
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .card-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: var(--accent-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.5rem;
        flex-shrink: 0;
        flex-grow: 0;
    }

    .card-title {
        font-size: 0.9rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .card-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-color);
        line-height: 1.2;
    }

    /* Chart Wrapper */
    .chart-container {
        background: var(--secondary-color);
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        height: 350px;
        position: relative;
    }

    /* TABEL PESANAN */
    .order-table {
        background: var(--secondary-color);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        width: 100%;
        margin-bottom: 2rem;
    }

    .order-table .table {
        margin-bottom: 0;
        min-width: 100%;
    }

    .order-table .table thead {
        background-color: #f1f5f9;
    }

    .order-table th,
    .order-table td {
        vertical-align: middle;
        white-space: nowrap;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }

    /* Status Badge */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        white-space: nowrap;
        display: inline-block;
        font-weight: 500;
    }

    /* Status Warna */
    .status-completed {
        background: var(--status-completed-bg);
        color: var(--status-completed-text);
    }

    .status-pending {
        background: var(--status-pending-bg);
        color: var(--status-pending-text);
    }

    .status-paid {
        background: var(--status-paid-bg);
        color: var(--status-paid-text);
    }

    .status-shipped {
        background: var(--status-shipped-bg);
        color: var(--status-shipped-text);
    }

    .status-cancelled {
        background: var(--status-cancelled-bg);
        color: var(--status-cancelled-text);
    }

    /* Kalau datanya kosong */
    .no-data {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        color: #6b7280;
    }

    .table-responsive {
        overflow-x: auto;
    }
    </style>

</head>
<body>
    <!-- KONTEN UTAMA -->
    <div class="main-content">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- SECTION STATISTIK -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">
            <!-- Card Total Produk -->
            <div class="col">
                <div class="card shadow-sm border-0 rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small">Total Produk</div>
                            <div class="h4"><?= number_format($total_products) ?></div>
                        </div>
                        <div style="width: 50px; height: 50px;" class="bg-primary text-white d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Total Pesanan -->
            <div class="col">
                <div class="card shadow-sm border-0 rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small">Total Pesanan</div>
                            <div class="h4"><?= number_format($total_orders) ?></div>
                        </div>
                        <div style="width: 50px; height: 50px;" class="bg-success text-white d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                            <i class="bi bi-cart4 fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Total Pendapatan -->
            <div class="col">
                <div class="card shadow-sm border-0 rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small">Total Pendapatan</div>
                            <div class="h4">Rp<?= number_format($revenue, 0, ',', '.') ?></div>
                        </div>
                        <div style="width: 50px; height: 50px;" class="bg-warning text-white d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                            <i class="bi bi-cash-coin fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Total Pengguna -->
            <div class="col">
                <div class="card shadow-sm border-0 rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small">Total Pengguna</div>
                            <div class="h4"><?= number_format($total_active_users) ?></div>
                        </div>
                        <div style="width: 50px; height: 50px;" class="bg-danger text-white d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

        <!-- SECTION TABEL PESANAN -->
        <div class="order-table">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">Pesanan Terakhir</h5>
            </div>
            <div class="table-responsive">
                <?php if (!empty($orders)): ?>
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <!-- ID Pesanan -->
                                    <td>#<?= htmlspecialchars($order['order_id']) ?></td>

                                    <!-- Nama Pelanggan -->
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>

                                    <!-- Produk -->
                                    <td><?= htmlspecialchars($order['product_name'] ?? 'Tidak ada produk') ?></td>

                                    <!-- Tanggal Pesanan -->
                                    <td><?= date('d/m/Y', strtotime($order['order_date'])) ?></td>

                                    <!-- Status Pesanan -->
                                    <td>
                                        <span class="status-badge <?= match (strtolower($order['status'])) {
                                            'completed' => 'status-completed',
                                            'pending' => 'status-pending',
                                            'paid' => 'status-paid',
                                            'shipped' => 'status-shipped',
                                            'cancelled' => 'status-cancelled',
                                            default => 'status-unknown'
                                        } ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>

                                    <!-- Total Pesanan -->
                                    <td>Rp<?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="bi bi-table fs-1"></i>
                        <p class="mt-2">Belum ada data pesanan</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SCRIPT BOOTSTRAP -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>