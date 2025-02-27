<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../includes/db.php';

// Create uploads directory if it doesn't exist
$uploadDirectory = "../assets/uploads/";
if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

// Get user_id from URL or session
$profile_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$is_own_profile = $profile_user_id == $_SESSION['user_id'];

// Fetch user profile information
$userQuery = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($stmt, "i", $profile_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result);

// Get user's posts
$postsQuery = "
    SELECT p.*, 
           COALESCE((SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'like'), 0) as likes_count,
           COALESCE((SELECT COUNT(*) FROM comments WHERE post_id = p.id), 0) as comments_count
    FROM posts p 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC";
$posts_stmt = $conn->prepare($postsQuery);
$posts_stmt->bind_param("i", $profile_user_id);
$posts_stmt->execute();
$posts = $posts_stmt->get_result();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_own_profile) {
    $newUsername = trim($_POST['username']);
    $newBio = trim($_POST['bio']);
    $userId = $_SESSION['user_id'];
    $error = null;

    try {
        // Check if username exists
        $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkUsername->bind_param("si", $newUsername, $userId);
        $checkUsername->execute();
        $checkResult = $checkUsername->get_result();
        
        if ($checkResult->num_rows > 0) {
            throw new Exception("Username sudah digunakan");
        }

        // Handle profile picture upload
        $profilePicUpdate = false;
        $newProfilePic = null;
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (!in_array(strtolower($filetype), $allowed)) {
                throw new Exception("Format file tidak didukung");
            }
            
            if ($_FILES['profile_pic']['size'] > 5 * 1024 * 1024) {
                throw new Exception("Ukuran file terlalu besar (maksimum 5MB)");
            }
            
            $newname = uniqid() . '.' . $filetype;
            $upload_path = $uploadDirectory . $newname;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profilePicUpdate = true;
                $newProfilePic = $newname;
            } else {
                throw new Exception("Gagal mengupload file");
            }
        }
        
        // Update user data
        if ($profilePicUpdate) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, bio = ?, profile_pic = ? WHERE id = ?");
            $stmt->bind_param("sssi", $newUsername, $newBio, $newProfilePic, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newUsername, $newBio, $userId);
        }
        
        if ($stmt->execute()) {
            // Delete old profile picture if new one was uploaded
            if ($profilePicUpdate && isset($userData['profile_pic']) && 
                $userData['profile_pic'] != 'default.png' && 
                file_exists($uploadDirectory . $userData['profile_pic'])) {
                unlink($uploadDirectory . $userData['profile_pic']);
            }
            
            $_SESSION['success'] = "Profil berhasil diperbarui!";
            header("Location: profile.php?user_id=" . $userId);
            exit();
        } else {
            throw new Exception("Gagal memperbarui profil");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($userData['username']); ?>'s Profile - UpNote</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-50">
        <a href="home.php" class="text-2xl font-bold">UpNote</a>
        <div class="flex items-center space-x-4">
            <a href="home.php" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Logout</a>
        </div>
    </nav>

    <!-- Profile Section -->
    <div class="max-w-4xl mx-auto mt-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <img src="<?php echo '../assets/uploads/' . (!empty($userData['profile_pic']) ? $userData['profile_pic'] : 'default.png'); ?>" 
                     alt="Profile Picture" 
                     class="w-32 h-32 rounded-full object-cover">
                <div class="ml-6">
                    <h1 class="text-2xl font-bold">@<?php echo htmlspecialchars($userData['username']); ?></h1>
                    <p class="text-gray-600 mt-2"><?php echo nl2br(htmlspecialchars($userData['bio'] ?? '')); ?></p>
                    <div class="mt-4 flex space-x-4">
                        <?php if ($is_own_profile): ?>
                            <button onclick="document.getElementById('editModal').classList.remove('hidden')" 
                                    class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                Edit Profile
                            </button>
                        <?php else: ?>
                            <!-- Tombol Kirim Pesan -->
                            <a href="send_message.php?receiver_id=<?php echo $profile_user_id; ?>" 
                               class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                                <i class="fas fa-envelope"></i> Kirim Pesan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Stats -->
            <div class="flex justify-center space-x-8 mt-6 pt-6 border-t">
                <div class="text-center">
                    <div class="text-2xl font-bold"><?php echo mysqli_num_rows($posts); ?></div>
                    <div class="text-gray-600">Posts</div>
                </div>
            </div>
        </div>

        <!-- User Posts -->
        <div class="mt-8">
            <h2 class="text-xl font-bold mb-4">Posts</h2>
            <?php while ($post = mysqli_fetch_assoc($posts)): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-4">
                    <p class="text-gray-800 mb-4"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    <?php if (!empty($post['image'])): ?>
                        <img src="../assets/uploads/<?php echo htmlspecialchars($post['image']); ?>" 
                             alt="Post Image" 
                             class="rounded-lg w-full mb-4">
                    <?php endif; ?>
                    <div class="flex items-center text-gray-500 text-sm">
                        <span class="mr-4">
                            <i class="fas fa-thumbs-up"></i>
                            <?php echo $post['likes_count']; ?> Likes
                        </span>
                        <span>
                            <i class="fas fa-comment"></i>
                            <?php echo $post['comments_count']; ?> Comments
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <?php if ($is_own_profile): ?>
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Edit Profile</h2>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                        Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?php echo htmlspecialchars($userData['username']); ?>"
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bio">
                        Bio
                    </label>
                    <textarea id="bio" 
                              name="bio" 
                              rows="3"
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($userData['bio'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="profile_pic">
                        Profile Picture
                    </label>
                    <input type="file" 
                           id="profile_pic" 
                           name="profile_pic" 
                           accept="image/*"
                           class="w-full">
                    <p class="text-sm text-gray-500 mt-1">Maksimum ukuran file: 5MB. Format: JPG, PNG, GIF</p>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button"
                            onclick="document.getElementById('editModal').classList.add('hidden')"
                            class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Show success message if it exists
        <?php if (isset($_SESSION['success'])): ?>
            alert('<?php echo addslashes($_SESSION['success']); ?>');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
