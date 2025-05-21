<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../Data/db_connect.php';
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Handle Delete
        if ($action === 'delete' && isset($_POST['order_id'])) {
            $orderId = mysqli_real_escape_string($conn, $_POST['order_id']);
            $deleteQuery = "DELETE FROM orders WHERE order_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param('s', $orderId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            exit;
        }
        
        // Handle Edit
        if ($action === 'edit' && isset($_POST['order_id'], $_POST['status'])) {
            $orderId = mysqli_real_escape_string($conn, $_POST['order_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $updateQuery = "UPDATE orders SET status = ? WHERE order_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('ss', $status, $orderId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            exit;
        }
    }
}

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

require_once '../include/sidebar.php';
require_once '../Data/db_connect.php';

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Pagination dan Filter
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['completed', 'pending', 'paid', 'shipped', 'cancelled']) 
    ? mysqli_real_escape_string($conn, $_GET['status']) 
    : '';

$whereClauses = [];
$params = [];
$types = '';

if ($search !== '') {
    $whereClauses[] = "(o.order_id LIKE CONCAT('%', ?, '%') OR c.name LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if ($statusFilter !== '') {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$where = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Total Orders
$countQuery = "SELECT COUNT(DISTINCT o.order_id) AS total 
               FROM orders o 
               JOIN customers c ON o.customer_id = c.customer_id 
               JOIN products p ON o.product_id = p.product_id 
               $where";

$stmt = $conn->prepare($countQuery);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$totalOrders = $result->fetch_assoc()['total'];
$totalPages = ceil($totalOrders / $limit);

$page = min($page, $totalPages);

// Fetch Orders
$orderQuery = "SELECT o.order_id, o.order_date, o.total_amount, o.status, 
                      c.name AS customer_name, p.name AS product_name 
               FROM orders o 
               JOIN customers c ON o.customer_id = c.customer_id 
               JOIN products p ON o.product_id = p.product_id 
               $where 
               ORDER BY o.order_date DESC 
               LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

$stmt = $conn->prepare($orderQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #121212;
            --secondary-color: #ffffff;
            --accent-color: #ef4444;
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

        .main-content {
            margin-left: 210px;
            padding: 1.5rem;
            padding-top: 90px;
            transition: margin-left 0.3s;
            min-height: 100vh;
            width: calc(100% - 220px);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 80px 1rem 1rem;
            }
        }

        .order-table {
            background: var(--secondary-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
        }

        .order-table .table {
            margin-bottom: 0;
            min-width: 100%;
        }

        .order-table th, .order-table td {
            vertical-align: middle;
            white-space: nowrap;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-block;
            font-weight: 500;
        }

        .status-completed { background: var(--status-completed-bg); color: var(--status-completed-text); }
        .status-pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
        .status-paid { background: var(--status-paid-bg); color: var(--status-paid-text); }
        .status-shipped { background: var(--status-shipped-bg); color: var(--status-shipped-text); }
        .status-cancelled { background: var(--status-cancelled-bg); color: var(--status-cancelled-text); }
    </style>
</head>
<body>
<div class="main-content">
    <h4 class="mb-4">Kelola Pesanan</h4>

    <div class="order-table">
        <div class="row mb-4 mt-4 mx-1">
            <div class="col-md-4">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
                </form>
            </div>
        </div>

        <div class="table-responsive mx-1">
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
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['product_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($order['order_date'])) ?></td>
                            <td>
                                <span class="status-badge <?= strtolower($order['status']) === 'completed' ? 'status-completed' : 
                                            (strtolower($order['status']) === 'pending' ? 'status-pending' : 
                                            (strtolower($order['status']) === 'paid' ? 'status-paid' : 
                                            (strtolower($order['status']) === 'shipped' ? 'status-shipped' : 
                                            (strtolower($order['status']) === 'cancelled' ? 'status-cancelled' : 'status-unknown')))) ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td>Rp<?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-btn" 
                                        data-id="<?= $order['order_id'] ?>"
                                        data-status="<?= $order['status'] ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" 
                                        data-id="<?= $order['order_id'] ?>">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </td>
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

        <!-- Pagination -->
        <div class="d-flex justify-content-between border-top p-3">
            <p class="text-secondary mb-0">
                Showing <strong><?= min(($offset + 1), $totalOrders) ?></strong> to 
                <strong><?= min(($offset + $limit), $totalOrders) ?></strong> of 
                <strong><?= $totalOrders ?></strong> hasil
            </p>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $statusFilter ?>">
                            &laquo;
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $statusFilter ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $statusFilter ?>">
                            &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Status Pesanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="order_id" id="modalOrderId">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="modalStatus">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="shipped">Shipped</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Edit Button
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = new bootstrap.Modal('#editModal');
            document.getElementById('modalOrderId').value = btn.dataset.id;
            document.getElementById('modalStatus').value = btn.dataset.status;
            modal.show();
        });
    });

    // Delete Button
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            Swal.fire({
                title: 'Hapus Pesanan?',
                text: "Data tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('order_id', btn.dataset.id);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(() => {
                        Swal.fire('Berhasil!', 'Pesanan dihapus', 'success').then(() => location.reload());
                    });
                }
            });
        });
    });

    // Edit Form Submit
    document.getElementById('editForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(() => {
            Swal.fire('Berhasil!', 'Status diperbarui', 'success').then(() => location.reload());
        });
    });
});
</script>
</body>
</html>