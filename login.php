<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './Data/db_connect.php'; // Koneksi database MySQLi

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $error_message = '';
    
    // Sanitasi input
    $email = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validasi input
    if (empty($email) || empty($password)) {
        $error_message = "Email dan password harus diisi!";
    } else {
        // Query untuk mencari user berdasarkan email
        $query = "SELECT * FROM customers WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // Jika user ditemukan, periksa password-nya
        if ($user && password_verify($password, $user['password'])) {
            // Jika login sukses, simpan session
            $_SESSION['customer_id'] = $user['customer_id'];
            $_SESSION['email'] = $user['email'];
            header("Location: home.php");
            exit;
        } else {
            $error_message = "Email atau password salah!";
        }
        
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Bagian HTML tetap sama -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vans Ecommerce</title>
    <link rel="icon" href="./Assets/Vans-logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* CSS tetap sama */
        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }
        .login-wrapper {
            display: flex;
            align-items: stretch;
            max-width: 1000px;
            width: 90%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .login-card {
            background: #fff;
            padding: 2rem;
            flex: 0 0 400px;
            border-radius: 12px 0 0 12px;
        }

        .image-container {
            flex: 1;
            position: relative;
            aspect-ratio: 4/3;
            min-height: 400px;
            max-width: 600px;
            border-radius: 0 12px 12px 0;
            overflow: hidden;
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .image-container img.active {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 500px;
            }

            .login-card {
                border-radius: 12px !important;
                flex: 0 0 auto;
                width: 100%;
            }

            .image-container {
                display: none; /* Sembunyikan gambar di mobile */
            }
        }

        .logo img {
            max-height: 70px;
        }

        .btn-primary {
            background-color: #121212;
            border: none;
            padding: 12px;
        }
        .btn-primary:hover {
            background-color:rgb(0, 0, 0);
        }
        .form-control:focus {
            border-color: #121212;
            box-shadow: 0 0 8px rgba(18, 18, 18, 0.25);
        }

        .error-message {
            color: #e63946;
            font-size: 0.9rem;
            text-align: center;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Bagian HTML tetap sama -->
    <div class="login-wrapper">
        <!-- Login Card -->
        <div class="login-card">
            <div class="logo text-center mb-4">
                <img src="./Assets/Vans-logo.svg" alt="Vans Logo" class="img-fluid">
            </div>
            <p class="text-center text-muted mb-3">Welcome to Login Page</p>
            <hr class="mb-4">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan email" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                <?php if (!empty($error_message)): ?>
                    <p class="error-message"><?php echo $error_message; ?></p>
                <?php endif; ?>
            </form>
            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 0.9rem;">Don't have an account? <a href="register.php" class="register-link">Register</a></p>
            </div>
            <div class="text-center mt-4">
                <p class="text-muted" style="font-size: 0.85rem;">&copy; 2025 Vans Indonesia</p>
            </div>
        </div>

        <!-- Image Slideshow (hidden on mobile) -->
        <div class="image-container">
            <img src="./Assets/Login 1.jpg" class="active">
            <img src="./Assets/Login 2.jpg">
            <img src="./Assets/Login 3.jpg">
        </div>
    </div>

    <script>
        // Script tetap sama
        let images = document.querySelectorAll('.image-container img');
        let index = 0;

        function changeImage() {
            images[index].classList.remove('active');
            index = (index + 1) % images.length;
            images[index].classList.add('active');
        }

        setInterval(changeImage, 3000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>