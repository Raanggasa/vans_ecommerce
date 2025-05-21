<?php
// PASTIKAN INI ADA DI BARIS PALING ATAS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// CEK AUTENTIKASI ADMIN
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Admin/index.php");
    exit;
}

require_once '../include/sidebar.php';
require_once '../Data/db_connect.php';

// Fungsi generate UUID
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Proses Form Submit
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = generateUUID();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? null);
    $category = $_POST['category'] ?? '';
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0.00);
    $image = '';

    // Validasi input
    if (empty($name) || empty($category) || empty($_FILES['image']['name'])) {
        $error = 'Nama, kategori, dan gambar wajib diisi!';
    } else {
        // Handle file upload
        $uploadDir = '../uploads/product/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['image']['name']));
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExt)) {
            $newFileName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                // Path untuk disimpan di database (relatif ke root website)
                $image = "uploads/product/" . $newFileName;
                
                // Insert ke database
                $stmt = $conn->prepare("INSERT INTO products (product_id, name, description, category, stock_quantity, price, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssids", 
                    $product_id,
                    $name,
                    $description,
                    $category,
                    $stock_quantity,
                    $price,
                    $image
                );
                
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Gagal menyimpan data: " . $stmt->error;
                }
            } else {
                $error = "Gagal mengupload gambar";
            }
        } else {
            $error = "Format file tidak didukung. Hanya JPG, JPEG, PNG & GIF yang diizinkan";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .main-content {
            margin-left: 210px;
            padding: 1.5rem;
            padding-top: 90px;
            min-height: 100vh;
            width: calc(100% - 210px);
        }
        
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .form-icon {
            font-size: 1.2rem;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 80px 1rem 1rem;
            }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="fw-bold"><i class="bi me-2"></i>Tambah Produk Baru</h4>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card card-custom">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-tag form-icon"></i>Nama Produk</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-grid form-icon"></i>Kategori</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="Shoes">Sepatu</option>
                                        <option value="Clothing">Pakaian</option>
                                        <option value="Accessories">Aksesoris</option>
                                        <option value="Backpacks">Tas</option>
                                        <option value="Skateboards">Skateboard</option>
                                        <option value="Limited Editions">Edisi Terbatas</option>
                                        <option value="Others">Lainnya</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label"><i class="bi bi-card-text form-icon"></i>Deskripsi</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label"><i class="bi bi-box form-icon"></i>Stok</label>
                                    <input type="number" class="form-control" name="stock_quantity" min="0" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label"><i class="bi bi-currency-dollar form-icon"></i>Harga</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label"><i class="bi bi-image form-icon"></i>Gambar Produk</label>
                                    <input type="file" class="form-control" name="image" accept="image/*" required>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-2"></i>Simpan Produk
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mt-md-0 mt-3">
                <div class="card card-custom">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Petunjuk</h5>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="bi bi-check2-circle text-primary me-2"></i>
                                Pastikan semua field wajib diisi
                            </li>
                            <li class="mb-3">
                                <i class="bi bi-image text-success me-2"></i>
                                Ukuran gambar maksimal 2MB
                            </li>
                            <li class="mb-3">
                                <i class="bi bi-card-text text-warning me-2"></i>
                                Deskripsi produk minimal 20 karakter
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Produk berhasil ditambahkan',
        showConfirmButton: false,
        timer: 1500
    });
</script>
<?php elseif (!empty($error)): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: '<?= $error ?>',
        showConfirmButton: true
    });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>