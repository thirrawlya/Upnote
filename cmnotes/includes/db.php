<?php
$host = "localhost"; // Sesuaikan dengan konfigurasi database
$user = "root"; // Default XAMPP MySQL user
$password = ""; // Default XAMPP password kosong
$database = "upnote"; // Sesuaikan dengan nama database

try {
    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    // Redirect ke halaman error atau tampilkan pesan yang user-friendly
    die("Maaf, terjadi kesalahan koneksi database");
}   
?>
