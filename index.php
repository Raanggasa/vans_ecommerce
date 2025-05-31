<?php
require_once './Data/db_connect.php';

// Validasi koneksi database
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Inisialisasi parameter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';

// Daftar kategori valid untuk preventasi SQL injection
$validCategories = [
    'all', 'Shoes', 'Clothing', 'Accessories', 
    'Backpacks', 'Skateboards', 'Limited Editions', 'Others'
];

// Validasi kategori
if (!in_array($category, $validCategories)) {
    die("Kategori tidak valid");
}

// Persiapan query dengan prepared statement
$sql = "SELECT * 
        FROM products 
        WHERE (name LIKE CONCAT('%', ?, '%') OR ? = '') 
          AND (category = ? OR ? = 'all')";

$stmt = $conn->prepare($sql);

// Bind parameter dengan tipe data yang sesuai
$stmt->bind_param("ssss", $search, $category, $category, $category);

// Eksekusi query
try {
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    die("Error eksekusi query: " . $e->getMessage());
}

// Tutup koneksi database setelah mendapatkan hasil
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vans Store</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
  <!-- Favicon -->
  <link rel="icon" href="./Assets/Vans-logo.svg" type="image/x-icon">
  <style>
    .navbar-nav {
      flex-direction: row;
      justify-content: center;
      flex-grow: 1;
    }
    .nav-item {
      margin: 0 10px;
    }
    .navbar-logo {
      width: auto;
      height: 30px;
      object-fit: contain;
    }
    .carousel-card {
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
      margin: 10px 5px; /* atas bawah 10px, kiri kanan 5px */
    }

    .carousel-card img {
      width: 100%;
      height: 400px; /* ditingkatkan agar lebih tinggi */
      object-fit: cover;
    }

    .carousel-item {
      position: relative;
      height: 400px; /* pastikan sesuai dengan tinggi gambar */
    }

    .carousel-caption-left {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 55%; /* Lebih lebar agar teks tidak terlalu mepet */
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 50px;
      text-align: left;
      color: white;
      background: linear-gradient(to right, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0));
      z-index: 10;
      border-radius: 0 20px 20px 0;
    }

    .carousel-caption-left h1 {
      font-size: 2.5rem; /* Ukuran besar untuk judul */
      font-weight: bold;
    }

    .carousel-caption-left p {
      font-size: 1.2rem; /* Ukuran sedang untuk deskripsi */
      margin-top: 10px;
    }
    body {
      padding-top: 70px; /* memberi ruang di atas agar isi tidak tertutup navbar */
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
      <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="#catalog">Catalog</a></li>
      <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
    </ul>
  </div>

  <div class="d-flex">
    <a href="login.php" class="btn btn-dark">Masuk</a>
  </div>
</nav>

<section id="home">
<div class="container">
  <div id="cardCarousel" class="carousel slide carousel-fade carousel-card" data-bs-ride="carousel" data-bs-interval="3000">
    <div class="carousel-inner">

      <!-- Slide 1 -->
      <div class="carousel-item active position-relative">
        <img src="./Assets/Login 1.jpg" class="d-block w-100" alt="Vans Marketing Strategy">
        <div class="carousel-caption-left">
          <h1 class="fw-bold">Welcome to Vans Official Store</h1>
          <p>Explore our latest footwear and apparel collections</p>
        </div>
      </div>

      <!-- Slide 2 -->
      <div class="carousel-item position-relative">
        <img src="./Assets/Login 2.jpg" class="d-block w-100" alt="Vans Products">
        <div class="carousel-caption-left">
          <h1 class="fw-bold">Step Up Your Style with Vans</h1>
          <p>Shop for trendy shoes, clothes, and accessories</p>
        </div>
      </div>

      <!-- Slide 3 -->
      <div class="carousel-item position-relative">
        <img src="./Assets/Login 3.jpg" class="d-block w-100" alt="Vans E-Commerce">
        <div class="carousel-caption-left">
          <h1 class="fw-bold">Join Our Community</h1>
          <p>Sign up today and enjoy exclusive deals and offers</p>
        </div>
      </div>

    </div>
  </div>
</div>
</section>

<div class="container my-4">
  <div class="row align-items-center">
    <!-- Form Pencarian -->
    <div class="col-md-8 mb-2">
      <form method="GET" action="">
        <div class="input-group">
          <input type="text" class="form-control" name="search" 
                 placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>">
          <button class="btn btn-dark" type="submit">
            <i class="bi bi-search"></i> Cari
          </button>
        </div>
      </form>
    </div>

    <!-- Dropdown Filter Kategori -->
    <div class="col-md-4 mb-2">
      <form method="GET" action="">
        <select class="form-select" name="category" onchange="this.form.submit()">
          <option value="all" <?= ($category == 'all' || empty($category)) ? 'selected' : '' ?>>View All</option>
          <option value="Shoes" <?= $category == 'Shoes' ? 'selected' : '' ?>>Shoes</option>
          <option value="Clothing" <?= $category == 'Clothing' ? 'selected' : '' ?>>Clothing</option>
          <option value="Accessories" <?= $category == 'Accessories' ? 'selected' : '' ?>>Accessories</option>
          <option value="Backpacks" <?= $category == 'Backpacks' ? 'selected' : '' ?>>Backpacks</option>
          <option value="Skateboards" <?= $category == 'Skateboards' ? 'selected' : '' ?>>Skateboards</option>
          <option value="Limited Editions" <?= $category == 'Limited Editions' ? 'selected' : '' ?>>Limited Editions</option>
          <option value="Others" <?= $category == 'Others' ? 'selected' : '' ?>>Others</option>
        </select>
        <?php if(!empty($search)): ?>
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<section id="catalog">
<div class="container my-5">
  <div class="row row-cols-1 row-cols-md-3 g-4">
    <?php if ($result->num_rows > 0): ?>
      <?php while($row = $result->fetch_assoc()): ?>
        <div class="col">
          <div class="card h-100 shadow-sm">
            <img src="<?= htmlspecialchars($row['image']) ?>" 
                 class="card-img-top" 
                 alt="<?= htmlspecialchars($row['name']) ?>">
            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($row['name']) ?></h5>
              <p class="card-text">Rp <?= number_format($row['price'], 0, ',', '.') ?></p>
              <p class="text-muted small">
                Stok: <?= $row['stock_quantity'] ?> |
                Kategori: <?= $row['category'] ?>
              </p>
              <a href="login.php" class="btn btn-dark w-100">Beli Sekarang</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12 text-center py-5">
        <h4>Produk tidak ditemukan</h4>
        <p>Coba kata kunci lain atau pilih kategori berbeda</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</section>

<section id="contact">
<footer class="bg-dark text-white pt-4 pb-3">
  <div class="container text-md-left">
    <div class="row text-md-left">

    <!-- Copyright -->
    <hr class="my-4">
    <div class="row align-items-center">
      <div class="col-md-7 col-lg-8">
        <p class="text-center text-md-start" style="font-size: 0.8rem; font-family: 'Roboto', sans-serif;">© 2025 <strong>VansStore</strong> - All rights reserved.</p>
      </div>
      <div class="col-md-5 col-lg-4">
        <p class="text-center text-md-end" style="font-size: 0.8rem; font-family: 'Roboto', sans-serif;">Designed by <strong>Pendekar Jahat</strong></p>
      </div>
    </div>
  </div>
</footer>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
