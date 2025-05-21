<?php
// PASTIKAN INI ADA DI BARIS PALING ATAS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Aktifkan pelaporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Aktifkan output buffering di awal file
ob_start();

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
require_once '../include/sidebar.php';
require_once '../Data/db_connect.php';

// Validasi koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fungsi generate UUID v4
function generateUUIDv4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Pagination dan Limit Data
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$currentPage = $page;
$limit = 15;
$offset = ($page - 1) * $limit;

// Pencarian dan Filter Kategori
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Ambil daftar kategori dari database
$categories = [];
$categoryQuery = $conn->query("SELECT DISTINCT category FROM products ORDER BY category");
if ($categoryQuery) {
    while ($row = $categoryQuery->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Kondisi WHERE
$whereClauses = [];
$params = [];
$types = '';
if ($search !== '') {
    $whereClauses[] = "(p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}
if ($categoryFilter !== '') {
    $whereClauses[] = "p.category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}
$where = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Hitung Total Data untuk Pagination
$totalProducts = 0;
$totalPages = 1;
$query = "SELECT COUNT(*) AS total FROM products p $where";
$stmt = $conn->prepare($query);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $totalProducts = $result->fetch_assoc()['total'];
        $totalPages = ceil($totalProducts / $limit);
    }
    $stmt->close();
} else {
    die("Error: " . $conn->error);
}

// Validasi halaman
if ($page > $totalPages) {
    $page = $totalPages > 0 ? $totalPages : 1;
    $offset = ($page - 1) * $limit;
}

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tambah/Edit Produk
    if (isset($_POST['save_product'])) {
        $product_id = trim($_POST['product_id'] ?? '');
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        $stock = (int)$_POST['stock'];
        $price = (float)str_replace(['.', ','], ['', '.'], $_POST['price']);
        $image = $_FILES['image'] ?? null;
        $old_image = $_POST['old_image'] ?? '';

        // Generate UUID jika baru
        if (empty($product_id)) {
            $product_id = generateUUIDv4();
        }

        // Validasi Input
        $errors = [];
        if (empty($name)) $errors[] = "Nama produk harus diisi";
        if (empty($category)) $errors[] = "Kategori harus dipilih";
        if ($stock < 0) $errors[] = "Stok tidak valid";
        if ($price <= 0) $errors[] = "Harga tidak valid";

        // Handle Upload Gambar
        $image_name = $old_image;
        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($image['type'], $allowed_types)) {
                $errors[] = "Format file tidak didukung (Hanya JPG, PNG, GIF)";
            } elseif ($image['size'] > $max_size) {
                $errors[] = "Ukuran file terlalu besar (Maks 2MB)";
            } else {
                // Generate nama file unik dengan path
                $filename = uniqid('prod_') . '.' . pathinfo($image['name'], PATHINFO_EXTENSION);
                $image_name = 'uploads/product/' . $filename; // ◀◀ PATH BARU DI DATABASE
                
                $upload_dir = '../uploads/product/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Pindahkan file ke folder upload
                if (!move_uploaded_file($image['tmp_name'], $upload_dir . $filename)) { // ◀◀ GUNAKAN $filename
                    $errors[] = "Gagal mengupload gambar";
                    $image_name = $old_image;
                }
            }
        }

        if (empty($errors)) {
            if ($product_id !== '') {
                // Update Produk
                $stmt = $conn->prepare("UPDATE products SET 
                    name = ?, description = ?, category = ?, 
                    stock_quantity = ?, price = ?, image = ?
                    WHERE product_id = ?");
                $stmt->bind_param("ssssdss", $name, $description, $category, $stock, $price, $image_name, $product_id);
            } else {
                // Tambah Produk Baru
                $stmt = $conn->prepare("INSERT INTO products 
                    (product_id, name, description, category, stock_quantity, price, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssds", $product_id, $name, $description, $category, $stock, $price, $image_name);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Produk berhasil disimpan!";
            } else {
                $_SESSION['error'] = "Gagal menyimpan produk: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        
        header("Location: manage_products.php");
        ob_end_flush();
        exit;
    }

    // Hapus Produk
    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];
        
        // Hapus pesanan terkait
        $stmt_delete_orders = $conn->prepare("DELETE FROM orders WHERE product_id = ?");
        $stmt_delete_orders->bind_param("s", $product_id);
        if (!$stmt_delete_orders->execute()) {
            $_SESSION['error'] = "Gagal menghapus pesanan terkait: " . $conn->error;
            $stmt_delete_orders->close();
            header("Location: manage_products.php");
            ob_end_flush();
            exit;
        }
        $stmt_delete_orders->close();

        // Dapatkan nama gambar
        $stmt = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
        $stmt->bind_param("s", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $image = $result->fetch_assoc()['image'];
        $stmt->close();

        // Hapus dari database
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("s", $product_id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Hapus file gambar
            if ($image && file_exists("../uploads/product/$image")) {
                unlink("../uploads/product/$image");
            }
            $_SESSION['success'] = "Produk dan pesanan terkait berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus produk";
        }
        
        header("Location: manage_products.php");
        ob_end_flush();
        exit;
    }
}

// Ambil Data Produk
$products = [];
$query = "
    SELECT 
        p.product_id, 
        p.name, 
        p.description, 
        p.category, 
        p.stock_quantity, 
        p.price, 
        p.image, 
        p.created_at
    FROM products p
    $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    die("Error: " . $conn->error);
}

// Fetch categories
$categories = [];
$query = "SHOW COLUMNS FROM products LIKE 'category'";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && isset($row['Type'])) {
        preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
        if (isset($matches[1])) {
            $categories = str_getcsv($matches[1], ',', "'");
        }
    }
    $result->close();
}

// Tutup koneksi database
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 -->
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

        .product-table {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .product-table .table {
            margin-bottom: 0;
            width: 100%;
        }

        .product-table .table thead {
            background-color: #f1f5f9;
        }

        .product-table th, .product-table td {
            vertical-align: middle;
            white-space: nowrap;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .product-table th {
            font-weight: 600;
            color: #495057;
        }

        .product-table td {
            color: #6c757d;
        }

        .product-table img {
            border-radius: 6px;
            max-width: 60px;
            height: auto;
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
    </style>
    <script>
        // Handle success/error messages from PHP session
        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= addslashes($_SESSION['success']) ?>',
            didDestroy: () => {
                window.location.reload(); // Auto refresh setelah alert ditutup
            }
        });
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($_SESSION['error']) ?>',
            didDestroy: () => {
                window.location.reload(); // Auto refresh setelah alert ditutup
            }
        });
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</head>
<body>
<div class="main-content">
    <h4 class="mb-4">Kelola Produk</h4>

    <div class="product-table">

        <!-- Form Pencarian dan Filter -->
        <div class="row mb-4 mt-4 mx-1">
            <div class="col-md-4">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control" name="search" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
            <div class="col-md-3">
                <form method="GET" action="">
                    <!-- Pertahankan parameter pencarian -->
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <!-- Reset halaman ke 1 saat filter berubah -->
                    <input type="hidden" name="page" value="1">
                    
                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="" <?= $categoryFilter === '' ? 'selected' : '' ?>>Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Cari
                </button>
                </form>
            </div>
            <div class="col-md-2">
                <button class="btn btn-success w-100" onclick="window.location.href='add_product.php';">
                    <i class="bi bi-plus-circle"></i> Tambah Produk
                </button>
            </div>
        </div>

        <!-- Tabel Produk -->
        <div class="table-responsive mx-1">
            <?php if (!empty($products)): ?>
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Deskripsi</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Harga</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <img src="../<?= htmlspecialchars($product['image']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>" 
                                    width="60">
                            </td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['description']) ?></td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                            <td>Rp<?= number_format($product['price'], 0, ',', '.') ?></td>
                            <td>
                                <!-- Tombol Edit -->
                                <button class="btn btn-sm btn-warning edit-button" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editProductModal"
                                    data-id="<?= htmlspecialchars($product['product_id']) ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-description="<?= htmlspecialchars($product['description']) ?>"
                                    data-category="<?= htmlspecialchars($product['category']) ?>"
                                    data-stock="<?= htmlspecialchars($product['stock_quantity']) ?>"
                                    data-price="<?= htmlspecialchars($product['price']) ?>"
                                    data-image="<?= htmlspecialchars($product['image']) ?>">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>

                                <!-- Tombol Hapus -->
                                <form method="POST" action="manage_products.php" class="d-inline delete-form">
                                    <input type="hidden" name="delete_product" value="1">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
                                    <button type="button" class="btn btn-sm btn-danger delete-button">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="text-center py-4 text-secondary">
                <i class="bi bi-table fs-1"></i>
                <p class="mt-2">Belum ada data produk</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex align-items-center justify-content-between border-top border-light bg-white p-3">
        <!-- Mobile View -->
        <div class="d-flex w-100 d-sm-none justify-content-between">
            <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) . '&limit=' . $limit . '&search=' . urlencode($search) . '&category_id=' . $categoryFilter : '#' ?>" class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>">Previous</a>
            <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) . '&limit=' . $limit . '&search=' . urlencode($search) . '&category_id=' . $categoryFilter : '#' ?>" class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">Next</a>
        </div>

        <!-- Desktop View -->
        <div class="d-none d-sm-flex w-100 align-items-center justify-content-between">
            <!-- Results Info -->
            <div>
                <p class="text-secondary mb-0">
                    Showing <span class="fw-bold"><?= min(($offset + 1), $totalProducts) ?></span> to <span class="fw-bold"><?= min(($offset + $limit), $totalProducts) ?></span> of <span class="fw-bold"><?= $totalProducts ?></span> results
                </p>
            </div>
            <nav aria-label="Pagination">
                <ul class="pagination mb-0">
                    <!-- Tombol Previous -->
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link text-secondary" href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryFilter ?>" aria-label="Previous">
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

                    <!-- Tombol Nomor Halaman -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <li class="page-item active" aria-current="page">
                                <a class="page-link text-white bg-primary" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryFilter ?>"><?= $i ?></a>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <a class="page-link text-secondary" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryFilter ?>"><?= $i ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Tombol Next -->
                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link text-secondary" href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryFilter ?>" aria-label="Next">
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

<!-- Modal Tambah Produk -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_products.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Tambah Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Produk</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Kategori</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label">Stok</label>
                        <input type="number" class="form-control" id="stock" name="stock" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Harga</label>
                        <input type="text" class="form-control" id="price" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Gambar Produk</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="save_product" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Produk -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_products.php" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="old_image" id="edit_old_image">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Produk</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Kategori</label>
                        <select class="form-select" id="edit_category" name="category" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_stock" class="form-label">Stok</label>
                        <input type="number" class="form-control" id="edit_stock" name="stock" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Harga</label>
                        <input type="text" class="form-control" id="edit_price" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_image" class="form-label">Gambar Produk</label>
                        <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                        <div class="mt-2">
                            <img id="edit_current_image" src="" alt="Current Image" width="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="save_product" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle Delete Confirmation
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', function () {
            const form = this.closest('.delete-form');
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                    // Tambahkan auto-refresh setelah submit
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            });
        }); 
    });

    // Handle Edit Modal Population
    document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            const productDescription = this.dataset.description;
            const productCategory = this.dataset.category;
            const productStock = this.dataset.stock;
            const productPrice = this.dataset.price;
            const productImage = this.dataset.image;

            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_name').value = productName;
            document.getElementById('edit_description').value = productDescription;
            document.getElementById('edit_category').value = productCategory;
            document.getElementById('edit_stock').value = productStock;
            document.getElementById('edit_price').value = productPrice;
            document.getElementById('edit_old_image').value = productImage;

            const currentImage = document.getElementById('edit_current_image');
            if (productImage) {
                currentImage.src = `../uploads/product/${productImage}`;
                currentImage.style.display = 'block';
            } else {
                currentImage.style.display = 'none';
            }
        });
    });
});
</script>
<script>
// Handle success/error messages from PHP session
<?php if (isset($_SESSION['success'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= addslashes($_SESSION['success']) ?>'
}).then(() => {
    window.location.href = 'manage_products.php'; // Refresh to clear session
});
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: '<?= addslashes($_SESSION['error']) ?>'
}).then(() => {
    window.location.href = 'manage_products.php'; // Refresh to clear session
});
<?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>
</body>
</html>