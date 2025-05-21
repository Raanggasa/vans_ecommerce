<?php
session_start();
require_once './Data/db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data customer
$customer_id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        
        $stmt = $conn->prepare("UPDATE customers SET name=?, email=?, address=?, phone=? WHERE customer_id=?");
        $stmt->bind_param("sssss", $name, $email, $address, $phone, $customer_id);
        $stmt->execute();
        $stmt->close();
        header("Location: profile.php");
        exit();
    }
    
    // Handle photo upload
    if (isset($_FILES['profile_picture'])) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Validasi file upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if(in_array($file_type, $allowed_types)) {
            $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $stmt = $conn->prepare("UPDATE customers SET profile_picture=? WHERE customer_id=?");
                $stmt->bind_param("ss", $target_file, $customer_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("Location: profile.php");
        exit();
    }
}

// Ambil riwayat transaksi
$stmt = $conn->prepare("SELECT o.*, p.name as product_name FROM orders o 
                       JOIN products p ON o.product_id = p.product_id 
                       WHERE o.customer_id = ? ORDER BY o.order_date DESC");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vans Store - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="./Assets/Vans-logo.svg" type="image/x-icon">
    <style>
    /* Variabel Warna */
    :root {
        /* Warna Status */
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
        
        /* Warna Tambahan */
        --secondary-color: #ffffff;
    }

    /* Tabel Pesanan */
    .order-table {
        background: var(--secondary-color);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        width: 100%;
        margin-bottom: 2rem;
        border: 1px solid #e5e7eb;
    }

    .order-table .table {
        margin-bottom: 0;
        min-width: 100%;
        border-collapse: collapse;
    }

    .order-table thead {
        background-color: #f1f5f9;
        border-bottom: 2px solid #e5e7eb;
    }

    .order-table th {
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .order-table th, 
    .order-table td {
        vertical-align: middle;
        white-space: nowrap;
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
        border-color: #e5e7eb;
    }

    .order-table tbody tr:not(:last-child) {
        border-bottom: 1px solid #e5e7eb;
    }

    /* Status Badge */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        white-space: nowrap;
        display: inline-block;
        font-weight: 500;
        text-transform: capitalize;
    }

    /* Variasi Status */
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

    /* Tampilan Kosong */
    .no-data {
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        font-size: 0.875rem;
        background: #f9fafb;
    }

    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
    }
    /* Badge Aksi Hijau tanpa border */
    .status-action {
        background: var(--status-completed-bg);
        color: var(--status-completed-text);
        border: none; /* Menghapus border */
    }

    .status-action:hover {
        background: var(--status-completed-text);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Tambahkan efek bayangan saat hover */
    }

    /* Efek transisi */
    .clickable {
        transition: all 0.2s ease-in-out;
    }
    body {
      padding-top: 70px;
    }
    .navbar-logo {
      height: 30px;
    }
    .profile-img-container {
        position: relative;
        max-width: 200px;
        margin: 0 auto;
    }
    .profile-img-edit {
        position: absolute;
        bottom: 10px;
        right: 10px;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top px-4 shadow">
    <a class="navbar-brand" href="#">
        <img src="./Assets/Vans-logo.svg" alt="Logo" class="navbar-logo">
    </a>

    <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Catalog</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
        </ul>
    </div>
    <!-- Di bagian navbar -->
    <div class="d-flex">
        <div class="dropdown">
            <?php
            // Split nama pengguna untuk ambil kata pertama
            $shortName = explode(' ', $customer['name'])[0];
            ?>
            <button class="btn btn-dark dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown">
                Hi, <?= htmlspecialchars($shortName) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <!-- Section Profil -->
    <div class="row mb-5">
        <div class="col-md-4 text-center">
            <div class="profile-img-container">
                <div class="position-relative" style="
                    width: 200px; 
                    height: 200px; 
                    border: 4px solid white;
                    border-radius: 50%;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    margin: 0 auto;
                    background: #f8f9fa;">
                    
                    <?php if(!empty($customer['profile_picture'])): ?>
                        <!-- Tampilkan foto jika ada -->
                        <img src="<?= htmlspecialchars($customer['profile_picture']) ?>" 
                            class="img-fluid"
                            style="
                                width: 100%;
                                height: 100%;
                                object-fit: cover;
                                border-radius: 50%;">
                    <?php else: ?>
                        <!-- Tampilkan icon jika tidak ada foto -->
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center" 
                            style="border-radius: 50%;">
                            <i class="bi bi-person" style="font-size: 5rem; color: #6c757d;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <label class="btn btn-primary btn-sm rounded-circle profile-img-edit"
                            style="
                                position: absolute;
                                bottom: 10px;
                                right: 10px;
                                width: 36px;
                                height: 36px;
                                display: flex;
                                align-items: center;
                                justify-content: center;">
                            <i class="bi bi-pencil"></i>
                            <input type="file" name="profile_picture" class="d-none" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <h3 class="mb-4">Biodata Pengguna</h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: bold;">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" 
                        value="<?= htmlspecialchars($customer['name']) ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: bold;">Email</label>
                        <input type="email" name="email" class="form-control" 
                            value="<?= htmlspecialchars($customer['email']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: bold;">Telepon</label>
                        <input type="tel" name="phone" class="form-control" 
                            value="<?= htmlspecialchars($customer['phone']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: bold;">Alamat</label>
                    <textarea name="address" class="form-control" rows="3"><?= 
                        htmlspecialchars($customer['address']) ?></textarea>
                </div>
                <button type="submit" name="update_profile" class="btn btn-dark">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <hr class="my-5">

    <!-- Riwayat Transaksi -->
    <h4 class="mb-4">Riwayat Transaksi</h4>
    <div class="table-responsive">
        <div class="order-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($orders)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="no-data">
                                    <i class="bi bi-file-text me-2"></i>Tidak ada transaksi
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= date('d M Y H:i', strtotime($order['order_date'])) ?></td>
                            <td><?= htmlspecialchars($order['product_name']) ?></td>
                            <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                            <td>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order['status'] == 'pending'): ?>
                                <div class="status-badge status-action clickable" 
                                    onclick="payOrder('<?= $order['order_id'] ?>')"
                                    role="button">
                                    Bayar
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function payOrder(orderId) {
    Swal.fire({
        title: 'Konfirmasi Pembayaran',
        text: "Anda yakin ingin melanjutkan pembayaran?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Bayar!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('update_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId + '&status=paid'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sukses!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', data.message, 'error');
                }
            });
        }
    })
}
</script>
</body>
</html>