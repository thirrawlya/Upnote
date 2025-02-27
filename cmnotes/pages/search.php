<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

if(isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $query = trim($_GET['q']);
    
    // Mencari user berdasarkan username (case-insensitive)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username LIKE CONCAT('%', ?, '%') LIMIT 1");
    $stmt->bind_param("s", $query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = mysqli_fetch_assoc($result)) {
        // Jika user ditemukan, redirect ke profil user tersebut
        header("Location: profile.php?user_id=" . $row['id']);
        exit();
    } else {
        // Jika tidak ditemukan, redirect ke home dengan pesan error
        header("Location: home.php?error=User+tidak+ditemukan");
        exit();
    }
} else {
    header("Location: home.php");
    exit();
}
?>
