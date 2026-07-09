<?php
// ==========================================
// KONEKSI DATABASE
// ==========================================
$host = "localhost";
$dbname = "dokpin_db";
$db_user = "root";
$db_pass = ""; // default XAMPP kosong

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
