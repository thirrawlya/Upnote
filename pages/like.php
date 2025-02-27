<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu']);
    exit();
}

$response = ['success' => false];
$user_id = $_SESSION['user_id'];

if (isset($_GET['post_id']) && isset($_GET['type'])) {
    $post_id = intval($_GET['post_id']);
    // Pastikan hanya tipe 'like' atau 'dislike' yang diterima
    $type = $_GET['type'] === 'like' ? 'like' : 'dislike';

    // Mulai transaksi agar proses update reaksi dan notifikasi bersifat atomik
    $conn->begin_transaction();

    try {
        $action = '';
        // Cek apakah user sudah pernah memberikan reaksi pada postingan ini
        $check = $conn->prepare("SELECT type FROM likes WHERE user_id = ? AND post_id = ?");
        $check->bind_param("ii", $user_id, $post_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_reaction = $row['type'];
            
            if ($current_reaction === $type) {
                // Jika user mengklik tombol yang sama, hapus reaksi
                $action = 'removed';
                $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
                $stmt->bind_param("ii", $user_id, $post_id);
                $stmt->execute();
            } else {
                // Jika berbeda, perbarui reaksi yang sudah ada
                $action = 'updated';
                $stmt = $conn->prepare("UPDATE likes SET type = ? WHERE user_id = ? AND post_id = ?");
                $stmt->bind_param("sii", $type, $user_id, $post_id);
                $stmt->execute();
            }
        } else {
            // Jika belum ada reaksi, masukkan reaksi baru
            $action = 'inserted';
            $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id, type) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $post_id, $type);
            $stmt->execute();
        }
        
        // === PROSES NOTIFIKASI ===
        if ($action === 'removed') {
            // Jika reaksi dihapus, hapus notifikasi terkait
            $delNotif = $conn->prepare("DELETE FROM notifications WHERE sender_id = ? AND post_id = ? AND type IN ('like','dislike')");
            $delNotif->bind_param("ii", $user_id, $post_id);
            $delNotif->execute();
        } else {
            // Jika reaksi ditambahkan atau diubah:
            // Hapus notifikasi lama (jika ada) terlebih dahulu
            $delNotif = $conn->prepare("DELETE FROM notifications WHERE sender_id = ? AND post_id = ?");
            $delNotif->bind_param("ii", $user_id, $post_id);
            $delNotif->execute();
            
            // Ambil data pemilik postingan
            $postQuery = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
            $postQuery->bind_param("i", $post_id);
            $postQuery->execute();
            $postResult = $postQuery->get_result();
            if ($postResult->num_rows > 0) {
                $postData = $postResult->fetch_assoc();
                // Hanya buat notifikasi jika pemberi reaksi bukan pemilik postingan
                if ($postData['user_id'] != $user_id) {
                    $notif = $conn->prepare("INSERT INTO notifications (user_id, sender_id, post_id, type, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $notif->bind_param("iiis", $postData['user_id'], $user_id, $post_id, $type);
                    $notif->execute();
                }
            }
        }
        
        // Ambil jumlah terbaru reaksi dan reaksi user saat ini
        $counts_query = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM likes WHERE post_id = ? AND type = 'like') as likes_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = ? AND type = 'dislike') as dislikes_count,
                (SELECT type FROM likes WHERE post_id = ? AND user_id = ?) as user_reaction
        ");
        $counts_query->bind_param("iiii", $post_id, $post_id, $post_id, $user_id);
        $counts_query->execute();
        $counts = $counts_query->get_result()->fetch_assoc();
        
        $conn->commit();
        
        $response = [
            'success' => true,
            'likes_count' => (int)$counts['likes_count'],
            'dislikes_count' => (int)$counts['dislikes_count'],
            'user_reaction' => $counts['user_reaction']
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'error' => 'Terjadi kesalahan saat memproses reaksi'];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
