<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$enteredPin = $data['pin'];
$page = $data['page']; // Ambil parameter halaman

$correctPin = '123456'; // Ganti dengan PIN dari database

if ($enteredPin === $correctPin) {
    $_SESSION["pin_verified_{$page}"] = true; // Set session spesifik halaman
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>