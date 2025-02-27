<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['receiver_id'])) {
    header("Location: home.php");
    exit();
}
$receiver_id = intval($_GET['receiver_id']);

// Ambil data user penerima
$stmt = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $receiver_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$receiver = mysqli_fetch_assoc($result);
if (!$receiver) {
    die("User tidak ditemukan.");
}

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content']);
    if (empty($content)) {
        $error = "Pesan tidak boleh kosong.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iis", $current_user_id, $receiver_id, $content);
        if (mysqli_stmt_execute($stmt)) {
            // Setelah sukses, redirect ke halaman pesan
            header("Location: messages.php");
            exit();
        } else {
            $error = "Gagal mengirim pesan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Kirim Pesan ke <?php echo htmlspecialchars($receiver['username']); ?> - UpNote</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <!-- Navigation Bar -->
  <nav class="bg-white shadow-md p-4">
    <div class="container mx-auto flex justify-between items-center">
      <h1 class="text-2xl font-bold text-blue-600">UpNote</h1>
      <div class="space-x-4">
        <a href="home.php" class="text-gray-700 hover:text-blue-500">Home</a>
        <a href="messages.php" class="text-gray-700 hover:text-blue-500">Messages</a>
        <a href="notifications.php" class="text-gray-700 hover:text-blue-500">Notifications</a>
        <a href="logout.php" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Logout</a>
      </div>
    </div>
  </nav>
  
  <!-- Form Pengiriman Pesan -->
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg mx-auto">
      <h2 class="text-2xl font-bold mb-4">Kirim Pesan ke <?php echo htmlspecialchars($receiver['username']); ?></h2>
      
      <?php if(isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo $error; ?>
        </div>
      <?php endif; ?>
      
      <form action="send_message.php?receiver_id=<?php echo $receiver_id; ?>" method="POST">
        <div class="mb-4">
          <label for="content" class="block text-gray-700 mb-2">Pesan</label>
          <textarea name="content" id="content" rows="5" class="w-full p-2 border rounded" placeholder="Tulis pesan di sini..." required></textarea>
        </div>
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
          Kirim Pesan
        </button>
      </form>
    </div>
  </div>
</body>
</html>
