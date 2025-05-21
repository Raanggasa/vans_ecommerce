<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . './Data/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $error_message = '';
    
    // Sanitasi input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validasi input
    if (empty($name) || empty($email) || empty($password) || empty($address) || empty($phone)) {
        $error_message = "Semua field harus diisi!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password dan konfirmasi password tidak cocok!";
    } else {
        // Cek email terdaftar
        $check_query = "SELECT email FROM customers WHERE email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error_message = "Email sudah terdaftar!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert data
            $insert_query = "INSERT INTO customers (customer_id, name, email, password, address, phone) 
                            VALUES (UUID(), ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hashed_password, $address, $phone);
            $result = mysqli_stmt_execute($stmt);

            if ($result) {
                $_SESSION['registration_success'] = true;
                header("Location: login.php");
                exit;
            } else {
                $error_message = "Gagal melakukan registrasi: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Vans Ecommerce</title>
    <link rel="icon" href="./Assets/Vans-logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* CSS tetap sama seperti sebelumnya */
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
            max-width: 1200px;
            width: 90%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            margin: 40px 0;
        }
        .login-card {
            background: #fff;
            padding: 3rem; /* Padding diperbesar */
            flex: 0 0 500px; /* Diperlebar dari 400px */
            border-radius: 12px 0 0 12px;
        }

        .image-container {
            flex: 1;
            position: relative;
            aspect-ratio: 4/3;
            min-height: 400px;
            max-width: 700px;
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
                margin: 20px 0; /* Margin yang lebih kecil untuk mobile */
                max-width: 90%; /* Lebih responsif di mobile */
            }
            
            .login-card {
                padding: 2rem; /* Padding lebih kecil di mobile */
            }

            .image-container {
                display: none;
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
    <div class="login-wrapper">
        <!-- Register Card -->
        <div class="login-card">
            <div class="logo text-center mb-4">
                <img src="./Assets/Vans-logo.svg" alt="Vans Logo" class="img-fluid">
            </div>
            <p class="text-center text-muted mb-3">Create New Account</p>
            <hr class="mb-4">
            <form method="POST" action="">
            <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter full name" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter address" required></textarea>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter phone number" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>
                <?php if (isset($error_message)): ?>
                    <p class="error-message"><?php echo $error_message; ?></p>
                <?php endif; ?>
            </form>
            <div class="text-center mt-3">
                <p class="text-muted" style="font-size: 0.9rem;">Already have an account? <a href="login.php" class="register-link">Login</a></p>
            </div>
            <div class="text-center mt-4">
                <p class="text-muted" style="font-size: 0.85rem;">&copy; 2025 Vans Indonesia</p>
            </div>
        </div>

        <!-- Image Slideshow -->
        <div class="image-container">
            <img src="./Assets/Login 1.jpg" class="active">
            <img src="./Assets/Login 2.jpg">
            <img src="./Assets/Login 3.jpg">
        </div>
    </div>

    <script>
        // Script slideshow sama seperti sebelumnya
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