<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$successMessage = "";
$errorMessage = "";

// Proses pengiriman pesan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    $receiver_id = intval($_POST['receiver_id']);
    $content = trim($_POST['content']);
    if (empty($content) || $receiver_id <= 0) {
        $errorMessage = "Pastikan semua field terisi dengan benar.";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $user_id, $receiver_id, $content);
        if ($stmt->execute()) {
            $successMessage = "Pesan berhasil dikirim.";
        } else {
            $errorMessage = "Gagal mengirim pesan.";
        }
    }
}

// Proses pengeditan pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_message') {
    $message_id = intval($_POST['message_id']);
    $newContent = trim($_POST['new_content']);
    if (empty($newContent)) {
        $errorMessage = "Pesan tidak boleh kosong.";
    } else {
        $stmt = $conn->prepare("UPDATE messages SET content = ? WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("sii", $newContent, $message_id, $user_id);
        if ($stmt->execute()) {
            $successMessage = "Pesan berhasil diperbarui.";
        } else {
            $errorMessage = "Gagal memperbarui pesan.";
        }
    }
}

// Ambil id partner dari parameter GET (conversation yang dipilih)
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

// Query untuk daftar percakapan (distinct conversation partner)
$conversationQuery = "
    SELECT DISTINCT
        CASE
            WHEN sender_id = ? THEN receiver_id
            ELSE sender_id
        END AS partner_id
    FROM messages
    WHERE sender_id = ? OR receiver_id = ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($conversationQuery);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$conversationPartners = [];
while ($row = mysqli_fetch_assoc($result)) {
    $conversationPartners[] = $row['partner_id'];
}
$conversationPartners = array_unique($conversationPartners);

// Ambil detail partner untuk daftar percakapan
$partnersDetails = [];
if (!empty($conversationPartners)) {
    $ids = implode(',', $conversationPartners);
    $partnerQuery = "SELECT id, username, profile_pic FROM users WHERE id IN ($ids)";
    $res = mysqli_query($conn, $partnerQuery);
    while ($row = mysqli_fetch_assoc($res)) {
        $partnersDetails[$row['id']] = $row;
    }
}

// Jika partner dipilih, ambil data detail partner
$partnerDetails = null;
$conversationMessages = [];
if ($partner_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $partnerDetails = mysqli_fetch_assoc($result);
    if ($partnerDetails) {
        // Ambil riwayat pesan antara user dan partner (urutkan dari pesan awal ke pesan terbaru)
        $msgQuery = "
            SELECT * FROM messages
            WHERE (sender_id = ? AND receiver_id = ?)
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ";
        $stmt = $conn->prepare($msgQuery);
        $stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $conversationMessages = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } else {
        $partner_id = 0; // jika partner tidak valid
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Direct Messages - UpNote</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Modal styling */
        .modal {
            display: none;
        }
        .chat-bubble {
            max-width: 70%;
            padding: 0.75rem;
            border-radius: 0.75rem;
            word-wrap: break-word;
        }
        .chat-bubble.sent {
            background-color: #DCF8C6; /* warna mirip chat WhatsApp */
            align-self: flex-end;
        }
        .chat-bubble.received {
            background-color: #FFFFFF;
            align-self: flex-start;
        }
    </style>
    <script>
        function openModal(messageId) {
            document.getElementById('editModal_' + messageId).style.display = 'flex';
        }
        function closeModal(messageId) {
            document.getElementById('editModal_' + messageId).style.display = 'none';
        }
    </script>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
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
    
    <div class="container mx-auto px-4 py-8 flex">
        <!-- Sidebar: Daftar Percakapan -->
        <div class="w-1/3 bg-white rounded-lg shadow p-4 mr-4">
            <h2 class="text-xl font-bold mb-4">Conversations</h2>
            <?php if(!empty($partnersDetails)): ?>
                <ul class="space-y-2">
                    <?php foreach($partnersDetails as $partner): ?>
                        <li class="p-2 rounded hover:bg-gray-100 <?php echo ($partner['id'] == $partner_id) ? 'bg-gray-200' : ''; ?>">
                            <a href="messages.php?partner_id=<?php echo $partner['id']; ?>" class="flex items-center">
                                <img src="../assets/uploads/<?php echo htmlspecialchars($partner['profile_pic'] ?? 'default.png'); ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                                <span class="font-semibold"><?php echo htmlspecialchars($partner['username']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-700">No conversations found.</p>
            <?php endif; ?>
        </div>
        
        <!-- Chat Area -->
        <div class="w-2/3 bg-white rounded-lg shadow p-4 flex flex-col">
            <?php if($partner_id > 0 && $partnerDetails): ?>
                <div class="mb-4 border-b pb-2 flex items-center">
                    <img src="../assets/uploads/<?php echo htmlspecialchars($partnerDetails['profile_pic'] ?? 'default.png'); ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($partnerDetails['username']); ?></h2>
                </div>
                <div class="flex-1 overflow-y-auto space-y-4 p-2" style="max-height: 500px;">
                    <?php if(!empty($conversationMessages)): ?>
                        <?php foreach($conversationMessages as $msg): ?>
                            <div class="flex <?php echo ($msg['sender_id'] == $user_id) ? 'justify-end' : 'justify-start'; ?>">
                                <div class="chat-bubble <?php echo ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                    <p><?php echo nl2br(htmlspecialchars($msg['content'])); ?></p>
                                    <small class="block text-right text-xs text-gray-500"><?php echo date('H:i', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <?php if($msg['sender_id'] == $user_id): ?>
                                    <button onclick="openModal(<?php echo $msg['id']; ?>)" class="ml-2 text-sm text-gray-500 hover:text-gray-700">
                                        Edit
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Modal untuk edit pesan -->
                            <?php if($msg['sender_id'] == $user_id): ?>
                            <div id="editModal_<?php echo $msg['id']; ?>" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-xl font-bold">Edit Message</h3>
                                        <button onclick="closeModal(<?php echo $msg['id']; ?>)" class="text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <form action="messages.php?partner_id=<?php echo $partner_id; ?>" method="POST">
                                        <input type="hidden" name="action" value="edit_message">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <div class="mb-4">
                                            <textarea name="new_content" rows="4" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required><?php echo htmlspecialchars($msg['content']); ?></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <button type="button" onclick="closeModal(<?php echo $msg['id']; ?>)" class="mr-2 px-4 py-2 border rounded hover:bg-gray-100">
                                                Cancel
                                            </button>
                                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-700">No messages in this conversation.</p>
                    <?php endif; ?>
                </div>
                <!-- Form Pengiriman Pesan Baru -->
                <form action="messages.php?partner_id=<?php echo $partner_id; ?>" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="receiver_id" value="<?php echo $partner_id; ?>">
                    <div class="flex">
                        <input type="text" name="content" class="flex-1 p-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type a message..." required>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r hover:bg-blue-600">Send</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="flex-1 flex items-center justify-center">
                    <p class="text-gray-700">Select a conversation to start chatting.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
