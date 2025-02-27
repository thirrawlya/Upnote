<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil postingan berdasarkan like terbanyak
$query = "
    SELECT posts.id, posts.user_id, posts.content, posts.image, users.username,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count
    FROM posts
    JOIN users ON posts.user_id = users.id
    ORDER BY like_count DESC, posts.id DESC
    LIMIT 10"; // Batasi hanya menampilkan 10 trending teratas

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Trending Konten</title>
</head>
<body>
    <h2>Trending Konten</h2>
    <a href="home.php">⬅ Kembali ke Beranda</a>

    <?php while ($row = $result->fetch_assoc()): ?>
        <div style="border: 1px solid #ccc; padding: 10px; margin-top: 10px;">
            <p><strong>@<?php echo htmlspecialchars($row['username']); ?></strong></p>
            <?php if ($row['image']): ?>
                <img src="../assets/images/<?php echo htmlspecialchars($row['image']); ?>" width="200" alt="Post Image"><br>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($row['content']); ?></p>
            <p>❤️ <?php echo $row['like_count']; ?> likes</p>
        </div>
    <?php endwhile; ?>

</body>
</html>
