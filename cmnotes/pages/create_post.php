<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['post_content']);
    
    // Validasi konten
    if (empty($content)) {
        header("Location: home.php?error=Konten post tidak boleh kosong");
        exit();
    }

    // Pastikan direktori uploads ada
    $uploadDirectory = "../assets/uploads/";
    if (!file_exists($uploadDirectory)) {
        mkdir($uploadDirectory, 0777, true);
    }
    
    // Handle upload gambar
    $image_name = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['post_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            // Generate nama file unik
            $newname = uniqid() . '.' . $filetype;
            $upload_path = $uploadDirectory . $newname;
            
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_path)) {
                $image_name = $newname;
            } else {
                header("Location: home.php?error=Gagal mengupload gambar");
                exit();
            }
        } else {
            header("Location: home.php?error=Format file gambar tidak didukung");
            exit();
        }
    }

    // Handle upload video
    $video_name = null;
    if (isset($_FILES['post_video']) && $_FILES['post_video']['error'] == 0) {
        $allowed_video = ['mp4', 'webm', 'ogg'];
        $video_filename = $_FILES['post_video']['name'];
        $video_filetype = pathinfo($video_filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($video_filetype), $allowed_video)) {
            // Generate nama file unik untuk video
            $new_video_name = uniqid() . '.' . $video_filetype;
            $upload_path_video = $uploadDirectory . $new_video_name;
            
            // Misalnya, batas maksimum ukuran video adalah 20MB
            if ($_FILES['post_video']['size'] > 20 * 1024 * 1024) {
                header("Location: home.php?error=Ukuran video terlalu besar. Maksimum 20MB");
                exit();
            }
            
            if (move_uploaded_file($_FILES['post_video']['tmp_name'], $upload_path_video)) {
                $video_name = $new_video_name;
            } else {
                header("Location: home.php?error=Gagal mengupload video");
                exit();
            }
        } else {
            header("Location: home.php?error=Format video tidak didukung");
            exit();
        }
    }

    try {
        // Update query untuk memasukkan data post dengan image dan video
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image, video, created_at) VALUES (?, ?, ?, ?, NOW())");
        // Jika tidak ada file yang diupload, variabelnya akan bernilai null.
        $stmt->bind_param("isss", $user_id, $content, $image_name, $video_name);
        
        if ($stmt->execute()) {
            header("Location: home.php?success=Post berhasil dibuat");
            exit();
        } else {
            throw new Exception("Database error");
        }
    } catch (Exception $e) {
        // Jika terjadi error, hapus file yang sudah diupload (jika ada)
        if ($image_name && file_exists($upload_path)) {
            unlink($upload_path);
        }
        if ($video_name && file_exists($upload_path_video)) {
            unlink($upload_path_video);
        }
        header("Location: home.php?error=Gagal membuat post: " . $e->getMessage());
        exit();
    }
}

// Jika bukan POST request, redirect ke home
header("Location: home.php");
exit();
?>
