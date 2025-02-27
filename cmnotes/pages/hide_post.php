<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    
    // Cek apakah postingan sudah di-hide sebelumnya
    $check = $conn->prepare("SELECT * FROM hidden_posts WHERE user_id = ? AND post_id = ?");
    $check->bind_param("ii", $user_id, $post_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows == 0) {
        // Hide post jika belum di-hide
        $stmt = $conn->prepare("INSERT INTO hidden_posts (user_id, post_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $post_id);
        $stmt->execute();
    }
}

header("Location: home.php");
exit();
?>
