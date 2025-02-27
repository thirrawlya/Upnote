<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';
$user_id = $_SESSION['user_id'];

// Query untuk mengambil notifikasi untuk user saat ini, termasuk data dari user yang mengirim like
$query = "
    SELECT n.*, u.username AS sender_username, u.profile_pic AS sender_profile_pic
    FROM notifications n
    JOIN users u ON n.sender_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notifications - UpNote</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* CSS tambahan jika dibutuhkan */
    body {
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
  </style>
</head>
<body class="bg-gray-100">
  <!-- Navigation -->
  <nav class="bg-white shadow-md py-4">
    <div class="container mx-auto flex justify-between items-center px-4">
      <h1 class="text-2xl font-bold text-blue-600">UpNote</h1>
      <div class="space-x-4">
        <a href="home.php" class="text-gray-700 hover:text-blue-500">Home</a>
        <a href="messages.php" class="text-gray-700 hover:text-blue-500">Messages</a>
        <a href="notifications.php" class="text-gray-700 hover:text-blue-500">Notifications</a>
        <a href="logout.php" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Logout</a>
      </div>
    </div>
  </nav>
  
  <!-- Konten Utama -->
  <div class="container mx-auto px-4 py-8">
    <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Your Notifications</h2>
    
    <?php if(count($notifications) > 0): ?>
      <ul class="space-y-4">
      <?php foreach ($notifications as $notif): ?>
        <li class="bg-white p-6 rounded-lg shadow hover:shadow-xl transition-shadow duration-300 flex items-center">
          <img src="../assets/uploads/<?php echo htmlspecialchars($notif['sender_profile_pic'] ?? 'default.png'); ?>" 
               alt="Profile Picture" 
               class="w-12 h-12 rounded-full mr-4 object-cover">
          <div class="flex-1">
            <p class="text-gray-800">
              <a href="profile.php?user_id=<?php echo $notif['sender_id']; ?>" 
                 class="font-semibold text-blue-500 hover:underline">
                @<?php echo htmlspecialchars($notif['sender_username']); ?>
              </a> memberikan 
              <span class="text-green-600 font-bold"><?php echo htmlspecialchars($notif['type']); ?></span> pada postingan Anda.
            </p>
            <p class="text-sm text-gray-500 mt-1">
              <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
            </p>
          </div>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="bg-white p-6 rounded-lg shadow text-center">
        <p class="text-gray-700 text-lg">Tidak ada notifikasi baru.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
