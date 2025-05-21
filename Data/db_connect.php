<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "toko_vans";

// Membuat koneksi menggunakan MySQLi object-oriented
$conn = new mysqli($host, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset ke utf8
$conn->set_charset("utf8mb4");
?>