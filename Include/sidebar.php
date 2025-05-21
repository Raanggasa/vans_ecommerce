<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Dashboard - Vans Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="../Assets/Vans-logo.svg" type="image/x-icon">
    <style>
        body {
            background-color:rgb(255, 255, 255);
            color: white;
            display: flex;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 220px; /* Diperkecil */
            height: 100vh;
            background-color: #121212;
            padding: 15px;
            position: fixed;
            left: 0;
            top: 0;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: white;
            padding: 10px; /* Diperkecil */
            border-radius: 6px;
            transition: background 0.3s;
            margin-bottom: 8px;
            font-size: 14px; /* Diperkecil */
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #333;
        }
        .sidebar a i {
            margin-right: 10px;
            font-size: 16px; /* Diperkecil */
        }

        .sidebar .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar .logo-container img {
            width: 120px; /* Sesuaikan ukuran logo */
            filter: brightness(0) invert(1); /* Mengubah warna gambar menjadi putih */
        }

        .team-section {
            margin-top: 15px;
            font-size: 12px;
            color: #94A3B8;
            padding-left: 8px;
        }

        .team-item {
            display: flex;
            align-items: center;
            padding: 8px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .team-item:hover, .team-item.active {
            background-color: #333;
        }

        .team-icon {
            width: 28px; /* Diperkecil */
            height: 28px;
            background-color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 8px;
            font-size: 13px;
            font-weight: bold;
            color: white;
        }

        .logout {
            position: absolute;
            bottom: 15px;
            width: calc(100% - 30px);
        }

        .logout a {
            padding: 8px;
            font-size: 14px;
        }

        .logout a.active {
            background-color: #E11D48; /* Warna merah */
            color: white;
            font-weight: bold;
        }

        /* Tombol Hamburger & X */
        .sidebar-toggle, .sidebar-close {
            position: fixed;
            top: 12px;
            font-size: 22px;
            cursor: pointer;
            z-index: 1002;
            color: white;
            transition: transform 0.3s;
        }

        .sidebar-toggle {
            left: 12px;
            display: none; /* Default: tersembunyi di desktop */
        }

        .sidebar-close {
            left: 240px;
            display: none; /* Hanya muncul saat sidebar terbuka */
        }

        /* Efek Modal Gelap */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
        }
        
        /* Header Styling */
        .header {
            width: calc(100% - 220px);
            background: #ffffff;
            color: black;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 220px;
            right: 0;
            z-index: 1003;
            transition: left 0.3s ease-in-out, width 0.3s ease-in-out;
            border-bottom: 1px solid #ddd;
        }

        /* Search Box Styling */
        .search-box {
            flex-grow: 1;
            margin: 0 20px;
            position: relative;
        }

        .search-box input {
            width: 50%;
            padding: 8px 12px;
            border-radius: 20px;
            border: 1px solid #ccc;
            outline: none;
        }

        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-menu i {
            font-size: 20px;
            margin-right: 15px;
            cursor: pointer;
            color: black;
        }

        .user-profile {
            display: flex;
            align-items: center;
        }

        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .user-name {
            font-size: 16px;
            color: black;
        }

        /* Responsif: Sidebar hanya tersembunyi di Mobile */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-toggle {
                display: block; /* Tampilkan hamburger di mobile */
            }
            .overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>

    <!-- Tombol Hamburger -->
    <i class="bi bi-list sidebar-toggle" id="sidebarToggle"></i>

    <!-- Tombol X (akan muncul saat sidebar terbuka) -->
    <i class="bi bi-x sidebar-close" id="sidebarClose"></i>

    <!-- Efek Modal Gelap -->
    <div class="overlay" id="overlay"></div>

    <!-- Header -->
    <div class="header">
        <div class="search-box">
            <input type="text" class="form-control" placeholder="Cari...">
        </div>
        <div class="user-menu">
            <i class="bi bi-bell"></i>
            <div class="user-profile">
                <img src="../Assets/Avatar.svg" alt="Admin">
                <span class="user-name">Admin</span>
            </div>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../Assets/Vans-logo.svg" alt="Vans Logo">
        </div>
        
        <a href="../Admin/dashboard.php" class="menu-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="../Admin/order.php" class="menu-item"><i class="bi bi-cart"></i> Order</a>
        <a href="../Admin/manage_products.php" class="menu-item"><i class="bi bi-box-seam"></i> Product</a>
        <a href="../Admin/customer.php" class="menu-item"><i class="bi bi-person"></i> Customer</a>
        
        <div class="team-section">Secret Menu</div>
        
        <a href="../Admin/report.php" class="menu-item"><i class="bi bi-graph-up"></i> Reports</a>
        <a href="../Admin/document.php" class="menu-item"><i class="bi bi-file-earmark"></i> Documents</a>

        <div class="logout">
            <a href="../Include/logout.php" class="menu-item" id="logoutBtn"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const toggleButton = document.getElementById("sidebarToggle");
        const closeButton = document.getElementById("sidebarClose");
        const overlay = document.getElementById("overlay");
        const menuItems = document.querySelectorAll(".menu-item, .team-item");
        const logoutBtn = document.getElementById("logoutBtn");

        function openSidebar() {
            sidebar.classList.add("show");
            overlay.classList.add("show");
            toggleButton.style.display = "none"; // Hilangkan hamburger
            closeButton.style.display = "block"; // Tampilkan tombol X
        }

        function closeSidebar() {
            sidebar.classList.remove("show");
            overlay.classList.remove("show");
            toggleButton.style.display = "block"; // Tampilkan kembali hamburger
            closeButton.style.display = "none"; // Hilangkan tombol X
        }

        toggleButton.addEventListener("click", openSidebar);
        closeButton.addEventListener("click", closeSidebar);
        overlay.addEventListener("click", closeSidebar);

        // Tambahkan class 'active' saat tombol ditekan
        menuItems.forEach(item => {
            item.addEventListener("click", function() {
                menuItems.forEach(menu => menu.classList.remove("active")); // Hapus semua active
                this.classList.add("active"); // Tambahkan active ke yang diklik
            });
        });

        // Efek active khusus untuk logout
        logoutBtn.addEventListener("click", function() {
            menuItems.forEach(menu => menu.classList.remove("active"));
            this.classList.add("active");
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>