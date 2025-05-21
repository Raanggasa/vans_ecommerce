<?php
session_start(); // Memulai sesi

// Hapus sesi
session_unset(); // Menghapus semua data sesi
session_destroy(); // Menghancurkan sesi

// Hapus cookie jika ada
if (isset($_COOKIE['username'])) {
    setcookie('username', '', time() - 3600, '/'); // Menghapus cookie 'username'
}

if (isset($_COOKIE['admin_id'])) {
    setcookie('admin_id', '', time() - 3600, '/'); // Menghapus cookie 'admin_id'
}

// Redirect ke halaman login setelah logout
header("Location: ../Admin/index.php");
exit;
?>
