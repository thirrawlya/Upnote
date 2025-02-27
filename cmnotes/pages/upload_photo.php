<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $image_data = $_POST['image_data'];

    if (!empty($image_data)) {
        $image = str_replace('data:image/png;base64,', '', $image_data);
        $image = str_replace(' ', '+', $image);
        $image = base64_decode($image);
        
        $file_name = uniqid() . '.png';
        $file_path = "../assets/images/" . $file_name;
        file_put_contents($file_path, $image);

        // Simpan ke database
        $stmt = $conn->prepare("INSERT INTO posts (user_id, image) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $file_name);
        $stmt->execute();

        header("Location: home.php");
        exit();
    }
}
?>
