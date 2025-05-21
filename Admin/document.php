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

// CEK STATUS PIN
$pinVerified = $_SESSION['pin_verified_dashboard'] ?? false;

// INCLUDE DEPENDENSI
include '../include/sidebar.php';
include '../Data/db_connect.php';

// AMBIL DATA DARI DATABASE
$customers_result = mysqli_query($conn, "SELECT customer_id, name, email, phone, created_at FROM customers");
$products_result = mysqli_query($conn, "SELECT product_id, name, category, price, stock_quantity FROM products");
$orders_query = "SELECT o.order_id, c.name AS customer_name, p.name AS product_name, 
                o.order_date, o.total_amount, o.status 
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN products p ON o.product_id = p.product_id";
$orders_result = mysqli_query($conn, $orders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
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

        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .main-content {
            margin-left: 210px;
            padding: 2rem;
            padding-top: 90px;
            min-height: 100vh;
            width: calc(100% - 210px);
            box-sizing: border-box;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            padding: 0 1.5rem 1.5rem;
        }

        .full-width-table {
            width: 100%;
            min-width: 100%;
            border-collapse: collapse;
        }

        .full-width-table th,
        .full-width-table td {
            padding: 1rem;
            white-space: nowrap;
            border-bottom: 1px solid #e2e8f0;
        }

        .full-width-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #64748b;
            text-align: left;
        }

        .full-width-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .export-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.2s;
        }

        .export-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge i {
            font-size: 0.7em;
        }

        .status-completed { background: var(--status-completed-bg); color: var(--status-completed-text); }
        .status-pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
        .status-shipped { background: var(--status-shipped-bg); color: var(--status-shipped-text); }
        .status-cancelled { background: var(--status-cancelled-bg); color: var(--status-cancelled-text); }
        .status-paid { background: var(--status-paid-bg); color: var(--status-paid-text); }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
                padding-top: 80px;
            }
            
            .table-container {
                padding: 0 1rem 1rem;
            }
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .pin-overlay {
            position: fixed;
            top: 0;
            left: 210px;
            width: calc(100% - 210px);
            height: 100%;
            backdrop-filter: blur(8px);
            background: rgba(255,255,255,0.7);
            z-index: 999;
            display: <?= $pinVerified ? 'none' : 'block' ?>;
        }
        @media (max-width: 768px) {
            .pin-overlay {
                left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="pin-overlay" style="display: <?= $pinVerified ? 'none' : 'block' ?>">
    <div class="modal fade" id="pinModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verifikasi PIN Admin</h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="pinInput" class="form-label">Masukkan 6 Digit PIN</label>
                    <input type="password" class="form-control form-control-lg" id="pinInput" 
                           placeholder="••••••" maxlength="6" inputmode="numeric" pattern="\d{6}">
                </div>
                <div id="pinError" class="text-danger d-none">PIN salah! Silakan coba lagi.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="verifyPin()">Verifikasi</button>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <!-- Customers Table -->
    <div class="data-card">
        <div class="card-header">
            <h5 class="card-title">Pelanggan</h5>
            <button class="export-btn" onclick="downloadPDF('customersTable', 'Customers')">
                <i class="fas fa-file-pdf"></i>
                Ekspor PDF
            </button>
        </div>
        <div class="table-container">
            <table class="full-width-table" id="customersTable">
                <thead>
                    <tr>
                        <th>ID Pelanggan</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Telepon</th>
                        <th>Tanggal Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($customer = mysqli_fetch_assoc($customers_result)): ?>
                    <tr>
                        <td><?= substr($customer['customer_id'], 0, 8) ?></td>
                        <td><?= $customer['name'] ?></td>
                        <td><?= $customer['email'] ?></td>
                        <td><?= $customer['phone'] ?></td>
                        <td><?= date('d M Y', strtotime($customer['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Products Table -->
    <div class="data-card">
        <div class="card-header">
            <h5 class="card-title">Produk</h5>
            <button class="export-btn" onclick="downloadPDF('productsTable', 'Products')">
                <i class="fas fa-file-pdf"></i>
                Ekspor PDF
            </button>
        </div>
        <div class="table-container">
            <table class="full-width-table" id="productsTable">
                <thead>
                    <tr>
                        <th>ID Produk</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($product = mysqli_fetch_assoc($products_result)): ?>
                    <tr>
                        <td><?= substr($product['product_id'], 0, 8) ?></td>
                        <td><?= $product['name'] ?></td>
                        <td><?= ucfirst($product['category']) ?></td>
                        <td>Rp<?= number_format($product['price'], 0, ',', '.') ?></td>
                        <td><?= $product['stock_quantity'] ?> pcs</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="data-card">
        <div class="card-header">
            <h5 class="card-title">Pesanan</h5>
            <button class="export-btn" onclick="downloadPDF('ordersTable', 'Orders')">
                <i class="fas fa-file-pdf"></i>
                Ekspor PDF
            </button>
        </div>
        <div class="table-container">
            <table class="full-width-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Produk</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = mysqli_fetch_assoc($orders_result)): ?>
                    <tr>
                        <td><?= substr($order['order_id'], 0, 8) ?></td>
                        <td><?= $order['customer_name'] ?></td>
                        <td><?= $order['product_name'] ?></td>
                        <td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                        <td>Rp<?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                <?= $order['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
const correctPin = '123456'; // Ganti dengan PIN yang sebenarnya dari database

function verifyPin() {
    const enteredPin = document.getElementById('pinInput').value;
    const errorElement = document.getElementById('pinError');
    
    if(enteredPin === correctPin) {
        fetch('verify_pin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pin: enteredPin, page: 'dashboard' }) // Parameter page
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                window.location.reload();
            } else {
                errorElement.classList.remove('d-none');
                document.getElementById('pinInput').classList.add('is-invalid');
            }
        });
    } else {
        errorElement.classList.remove('d-none');
        document.getElementById('pinInput').classList.add('is-invalid');
    }
}

// Inisialisasi modal
const pinModal = new bootstrap.Modal(document.getElementById('pinModal'), {
    backdrop: false,
    keyboard: false
});

<?php if(!$pinVerified): ?>
document.addEventListener('DOMContentLoaded', function() {
    pinModal.show();
});
<?php endif; ?>
</script>

<script>
window.jsPDF = window.jspdf.jsPDF;

function downloadPDF(tableId, title) {
    const doc = new jsPDF('l', 'pt');
    const table = document.getElementById(tableId);
    
    // Ambil header
    const headers = [];
    table.querySelectorAll('th').forEach(th => {
        headers.push(th.innerText);
    });
    
    // Ambil data rows
    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Handle khusus untuk status badge
            if(td.querySelector('.status-badge')) {
                row.push(td.querySelector('.status-badge').innerText);
            } else {
                row.push(td.innerText);
            }
        });
        rows.push(row);
    });
    
    // Generate PDF
    doc.autoTable({
        head: [headers],
        body: rows,
        theme: 'grid',
        styles: { 
            fontSize: 8,
            cellPadding: 3,
            valign: 'middle'
        },
        headerStyles: {
            fillColor: [99, 102, 241],
            textColor: 255
        },
        margin: { top: 30 },
        didDrawPage: function (data) {
            doc.setFontSize(14);
            doc.text(title + ' Report', 40, 25);
        }
    });
    
    doc.save(`${title}_Report_${new Date().toISOString().slice(0,10)}.pdf`);
}
</script>
</body>
</html>