<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $user_id = $_SESSION['user_id'];

    // Cek apakah postingan milik user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Hapus postingan
        $delete_stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $delete_stmt->bind_param("i", $post_id);
        $delete_stmt->execute();
    }
}

header("Location: home.php");
exit();
?>