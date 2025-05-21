<?php
// Pastikan tidak ada output sebelum header
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// CEK AUTENTIKASI ADMIN DENGAN BENAR
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once '../include/sidebar.php';
require_once '../Data/db_connect.php';

// Pastikan koneksi database valid
if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// HANDLE DELETE ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['customer_id'])) {
        try {
            $conn->begin_transaction();
            
            // Ambil data customer termasuk profile picture
            $stmt = $conn->prepare("SELECT profile_picture FROM customers WHERE customer_id = ?");
            $stmt->bind_param("s", $_POST['customer_id']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal mengambil data customer: " . $stmt->error);
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Customer tidak ditemukan");
            }
            $customerData = $result->fetch_assoc();
            $profilePicture = $customerData['profile_picture'];

            // Hapus orders
            $stmt = $conn->prepare("DELETE FROM orders WHERE customer_id = ?");
            $stmt->bind_param("s", $_POST['customer_id']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus order: " . $stmt->error);
            }

            // Hapus customer
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->bind_param("s", $_POST['customer_id']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus customer: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Customer tidak ditemukan");
            }

            $conn->commit();
            
            // Hapus file profile picture jika ada
            if (!empty($profilePicture) && file_exists("../uploads/profiles/" . $profilePicture)) {
                unlink("../uploads/profiles/" . $profilePicture);
            }

            header('Content-Type: application/json');
            ob_end_clean();
            die(json_encode(['success' => true, 'message' => 'Data berhasil dihapus']));
            
        } catch (Exception $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            http_response_code(500);
            ob_end_clean();
            die(json_encode([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage()
            ]));
        }
    }
}

// Dalam blok HANDLE GET DETAIL REQUEST
if (isset($_GET['action']) && $_GET['action'] === 'get_detail' && isset($_GET['customer_id'])) {
    // Bersihkan buffer dan set header JSON
    ob_end_clean();
    header('Content-Type: application/json');
    ini_set('display_errors', 0); // Nonaktifkan tampilan error
    
    try {
        $customer_id = $_GET['customer_id'];
        
        // Query detail customer
        $stmt = $conn->prepare("SELECT 
            customer_id,
            name,
            email,
            phone,
            address,
            profile_picture,
            DATE_FORMAT(created_at, '%d %M %Y %H:%i') AS formatted_date 
            FROM customers 
            WHERE customer_id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $customer_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit;
        }
        
        $customer = $result->fetch_assoc();
        
        // Perbaikan path gambar
        $customer['profile_picture'] = $customer['profile_picture']
            ? '../' . $customer['profile_picture'] // Tambahkan ../
            : '';
        
        echo json_encode([
            'success' => true,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? '-',
                'address' => $customer['address'] ?? '-',
                'profile_picture' => $customer['profile_picture'],
                'created_at' => $customer['formatted_date']
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// PAGINATION DAN FILTER
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($currentPage - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// BUILD QUERY
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(name LIKE CONCAT('%', ?, '%') OR address LIKE CONCAT('%', ?, '%') OR phone LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$search, $search, $search]);
    $types .= 'sss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// TOTAL DATA
$totalQuery = "SELECT COUNT(*) AS total FROM customers $whereClause";
$stmt = $conn->prepare($totalQuery);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalCustomers = $totalResult['total'];
$totalPages = ceil($totalCustomers / $limit);

// VALIDASI HALAMAN
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $limit;
}

// AMBIL DATA
$mainQuery = "SELECT customer_id, name, email, phone, address, profile_picture, 
              DATE_FORMAT(created_at, '%d %b %Y') AS created_date 
              FROM customers 
              $whereClause 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";

$stmt = $conn->prepare($mainQuery);
$types .= 'ii';
$params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Customer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
            padding: 1.5rem;
            padding-top: 90px;
            transition: margin-left 0.3s ease-in-out;
            min-height: 100vh;
            width: calc(100% - 210px);
            box-sizing: border-box;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 80px 1rem 1rem;
            }
        }

        .customer-table {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .customer-table .table {
            margin-bottom: 0;
            width: 100%;
        }

        .customer-table .table thead {
            background-color: #f1f5f9;
        }

        .customer-table th,
        .customer-table td {
            vertical-align: middle;
            white-space: nowrap;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .customer-table th {
            font-weight: 600;
            color: #495057;
        }

        .customer-table td {
            color: #6c757d;
        }

        .btn {
            font-size: 0.875rem;
        }

        .btn-warning {
            color: #ffffff;
            background-color: #ffc107;
            border-color: #ffc107;
        }

        .btn-danger {
            color: #ffffff;
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .pagination .page-link {
            color: #007bff;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: #f8f9fa;
            padding: 1.2rem 1.5rem;
        }

        .modal-title {
            font-weight: 500;
            font-size: 1.1rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
        }

        .border-light {
            border-color: #e9ecef !important;
        }

        .btn-light {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }

        .btn-light:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>
<div class="main-content">
    <h4 class="mb-4">Kelola Customer</h4>

    <!-- Search Form -->
    <div class="customer-table">
        <div class="row mb-4 mt-4 mx-1">
            <div class="col-md-4">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control" name="search" placeholder="Cari nama customer..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Cari
                </button>
                </form>
            </div>
        </div>

        <!-- Tabel Customer -->
        <div class="table-responsive mx-1">
            <?php if (!empty($customers)): ?>
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Nomor HP</th>
                        <th>Alamat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                            <td><?= htmlspecialchars($customer['email']) ?></td>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td><?= htmlspecialchars($customer['address']) ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-primary me-2 detail-button" 
                                        data-customer-id="<?= htmlspecialchars($customer['customer_id']) ?>">
                                    <i class="bi bi-eye"></i> Detail
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-sm btn-danger delete-button" 
                                        data-customer-id="<?= htmlspecialchars($customer['customer_id']) ?>">
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
                    <p class="mt-2">Belum ada data customer</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="d-flex align-items-center justify-content-between border-top border-light bg-white p-3">
                    <!-- Mobile View -->
        <div class="d-flex w-100 d-sm-none justify-content-between">
            <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) . '&limit=' . $limit . '&search=' . urlencode($search) : '#' ?>" class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>">Previous</a>
            <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) . '&limit=' . $limit . '&search=' . urlencode($search) : '#' ?>" class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">Next</a>
        </div>

        <!-- Desktop View -->
        <div class="d-none d-sm-flex w-100 align-items-center justify-content-between">
            <!-- Results Info -->
            <div>
                <p class="text-secondary mb-0">
                    Showing <span class="fw-bold"><?= min(($offset + 1), $totalCustomers) ?></span> to <span class="fw-bold"><?= min(($offset + $limit), $totalCustomers) ?></span> of <span class="fw-bold"><?= $totalCustomers ?></span> customers
                </p>
            </div>
            <nav aria-label="Pagination">
                <ul class="pagination mb-0">
                    <!-- Tombol Previous -->
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link text-secondary" href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                                <span class="visually-hidden">Previous</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link text-secondary" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                                <span class="visually-hidden">Previous</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Nomor Halaman -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $currentPage ? 'text-white bg-primary' : 'text-secondary' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Tombol Next -->
                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link text-secondary" href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                                <span class="visually-hidden">Next</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link text-secondary" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                                <span class="visually-hidden">Next</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-normal text-dark">Detail Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Foto Profil -->
                    <div class="col-md-4 text-center">
                        <div class="position-relative">
                            <img id="detailProfilePic" 
                                 class="img-fluid rounded-circle border border-3 border-light"
                                 style="width: 160px; height: 160px; object-fit: cover;"
                                 alt="Foto Profil">
                        </div>
                        <p class="text-muted mt-3 mb-0 small" id="detailMemberSince"></p>
                    </div>
                    
                    <!-- Detail -->
                    <div class="col-md-8">
                        <div class="mb-3">
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Nama Lengkap</label>
                                <p class="fs-6 mb-2 text-dark" id="detailName">-</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Email</label>
                                <p class="fs-6 mb-2 text-dark" id="detailEmail">-</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Nomor Telepon</label>
                                <p class="fs-6 mb-2 text-dark" id="detailPhone">-</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Alamat</label>
                                <p class="fs-6 mb-2 text-dark" id="detailAddress">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Detail Button
    document.querySelectorAll('.detail-button').forEach(button => {
        button.addEventListener('click', async function() {
            const customerId = this.dataset.customerId;
            
            try {
                // Tampilkan loading
                Swal.fire({
                    title: 'Memuat data...',
                    allowEscapeKey: false,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const response = await fetch(`customer.php?action=get_detail&customer_id=${customerId}`);
                const data = await response.json();
                
                Swal.close();
                
                if (!data.success) {
                    throw new Error(data.message || 'Gagal memuat data customer');
                }

                const customer = data.customer;
                
                // Update tampilan modal
                const profilePic = document.getElementById('detailProfilePic');
                profilePic.src = customer.profile_picture 
                    ? `${customer.profile_picture}?${Date.now()}` // Path sudah diperbaiki di PHP
                    : '../Assets/default-profile.png';

                // Fallback jika gambar error
                profilePic.onerror = () => {
                    profilePic.src = '../Assets/default-profile.png';
                };
                
                document.getElementById('detailName').textContent = customer.name;
                document.getElementById('detailEmail').textContent = customer.email;
                document.getElementById('detailPhone').textContent = customer.phone || '-';
                document.getElementById('detailAddress').textContent = customer.address || '-';
                document.getElementById('detailMemberSince').textContent = 
                    `Terdaftar sejak: ${customer.created_at}`;

                // Tampilkan modal
                const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                modal.show();
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    html: `Tidak dapat memuat detail:<br><small>${error.message}</small>`,
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // Handle Delete Button (Revisi)
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', async function() {
            const customerId = this.dataset.customerId;
            
            const { isConfirmed } = await Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            });

            if (!isConfirmed) return;

            try {
                const response = await fetch('customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&customer_id=${encodeURIComponent(customerId)}`
                });

                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Terjadi kesalahan server');
                }

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        showConfirmButton: true, // Tampilkan tombol OK
                        confirmButtonText: 'OK', // Teks tombol
                        allowOutsideClick: false // Memaksa klik tombol OK
                    });
                    location.reload(); // Reload setelah klik OK
                } else {
                    throw new Error(data.message);
                }
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    html: `Tidak dapat menghapus customer:<br><small>${error.message}</small>`,
                    confirmButtonText: 'OK' // Tambahkan tombol OK di error juga
                });
            }
        });
    });
});
</script>

</body>
</html>
