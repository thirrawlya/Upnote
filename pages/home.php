<?php
session_start();

// Pastikan user sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set cookie "visited" selama 1 hari (86400 detik)
// Pastikan setcookie dipanggil sebelum ada output HTML
setcookie("visited", "true", time() + 86400, "/");

require_once '../includes/db.php';

// Create uploads directory if it doesn't exist
$uploadDirectory = "../assets/uploads/";
if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

// Fetch user profile information
$user_id = $_SESSION['user_id'];
$userQuery = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result) ?? [];

// Query untuk posts dengan semua informasi yang dibutuhkan, termasuk profile_pic
$postsQuery = "
    SELECT 
        p.*, 
        u.username,
        u.profile_pic,
        COALESCE((SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'like'), 0) as likes_count,
        COALESCE((SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'dislike'), 0) as dislikes_count,
        COALESCE((SELECT COUNT(*) FROM comments WHERE post_id = p.id), 0) as comments_count,
        (SELECT type FROM likes WHERE post_id = p.id AND user_id = ?) as user_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id NOT IN (SELECT post_id FROM hidden_posts WHERE user_id = ?)
    ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $postsQuery);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$postsResult = mysqli_stmt_get_result($stmt);

// Trending posts query dengan syarat minimal 2 like (tidak mengubah fungsi yang telah ada)
$trendingQuery = "
    SELECT p.*, u.username, u.profile_pic,
           COALESCE((SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'like'), 0) as likes_count,
           COALESCE((SELECT COUNT(*) FROM comments WHERE post_id = p.id), 0) as comments_count
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id NOT IN (SELECT post_id FROM hidden_posts WHERE user_id = ?)
      AND (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'like') >= 2
    ORDER BY likes_count DESC, p.created_at DESC 
    LIMIT 5";
$stmt = mysqli_prepare($conn, $trendingQuery);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$trendingResult = mysqli_stmt_get_result($stmt);
$trendingPosts = mysqli_fetch_all($trendingResult, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - UpNote</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script>
    function deleteComment(commentId, postId) {
      if (!confirm('Are you sure you want to delete this comment?')) {
          return;
      }
      
      const formData = new FormData();
      formData.append('comment_id', commentId);
      
      fetch('delete_comment.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              const commentElement = document.querySelector(`#comment-${commentId}`);
              if (commentElement) {
                  commentElement.remove();
              }
              const commentCount = document.querySelector(`#comment-count-${postId}`);
              if (commentCount) {
                  commentCount.textContent = data.comments_count;
              }
          } else {
              alert('Error: ' + (data.error || 'Failed to delete comment'));
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while deleting the comment');
      });
    }

    function toggleTheme() {
      document.body.classList.toggle('dark');
      let theme = document.body.classList.contains('dark') ? 'dark' : 'light';
      localStorage.setItem('theme', theme);
    }

    document.addEventListener('DOMContentLoaded', () => {
      if (localStorage.getItem('theme') === 'dark') {
          document.body.classList.add('dark');
      }
      // Inisialisasi slider trending posts
      const slides = document.querySelectorAll('#trending-slider .trending-slide');
      let currentSlide = 0;
      if(slides.length > 0) {
          slides[currentSlide].classList.remove('hidden');
          setInterval(() => {
              slides[currentSlide].classList.add('hidden');
              currentSlide = (currentSlide + 1) % slides.length;
              slides[currentSlide].classList.remove('hidden');
          }, 6000);
      }
    });

    function previewImage(input) {
      const preview = document.getElementById('image-preview');
      const previewContainer = document.getElementById('preview-container');
      
      if (input.files && input.files[0]) {
          const file = input.files[0];
          if (file.size > 5 * 1024 * 1024) {
              alert('File terlalu besar. Maksimum ukuran file adalah 5MB');
              input.value = '';
              return;
          }
          if (!file.type.match('image.*')) {
              alert('Hanya file gambar yang diperbolehkan');
              input.value = '';
              return;
          }
          const reader = new FileReader();
          reader.onload = function(e) {
              preview.src = e.target.result;
              previewContainer.classList.remove('hidden');
          }
          reader.readAsDataURL(file);
      }
    }

    function removePreview() {
      const preview = document.getElementById('image-preview');
      const previewContainer = document.getElementById('preview-container');
      const fileInput = document.querySelector('input[name="post_image"]');
      preview.src = '';
      previewContainer.classList.add('hidden');
      fileInput.value = '';
    }

    function previewVideo(input) {
      const preview = document.getElementById('video-preview');
      const previewContainer = document.getElementById('video-preview-container');
      
      if (input.files && input.files[0]) {
          const file = input.files[0];
          if (file.size > 20 * 1024 * 1024) {
              alert('File terlalu besar. Maksimum ukuran file adalah 20MB');
              input.value = '';
              return;
          }
          if (!file.type.match('video.*')) {
              alert('Hanya file video yang diperbolehkan');
              input.value = '';
              return;
          }
          const reader = new FileReader();
          reader.onload = function(e) {
              preview.src = e.target.result;
              previewContainer.classList.remove('hidden');
          }
          reader.readAsDataURL(file);
      }
    }

    function removeVideo() {
      const preview = document.getElementById('video-preview');
      const previewContainer = document.getElementById('video-preview-container');
      const fileInput = document.querySelector('input[name="post_video"]');
      preview.src = '';
      previewContainer.classList.add('hidden');
      fileInput.value = '';
    }

    function toggleComments(postId) {
      const commentSection = document.getElementById('comments-' + postId);
      if(commentSection.classList.contains('hidden')) {
          document.querySelectorAll('[id^="comments-"]').forEach(section => {
              if(section.id !== 'comments-' + postId) {
                  section.classList.add('hidden');
              }
          });
          commentSection.classList.remove('hidden');
      } else {
          commentSection.classList.add('hidden');
      }
    }

    function handleReaction(postId, type, element) {
      const likeBtn = document.querySelector(`#like-btn-${postId}`);
      const dislikeBtn = document.querySelector(`#dislike-btn-${postId}`);
      likeBtn.disabled = true;
      dislikeBtn.disabled = true;
      fetch(`like.php?post_id=${postId}&type=${type}`, {
          method: 'GET',
          credentials: 'same-origin'
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Refresh halaman secara otomatis setelah reaksi berhasil
              window.location.reload();
          } else if (data.error) {
              alert('Gagal memberikan reaksi: ' + data.error);
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('Terjadi kesalahan saat memproses reaksi');
      })
      .finally(() => {
          likeBtn.disabled = false;
          dislikeBtn.disabled = false;
      });
    }

    // Fungsi submitComment telah diubah untuk refresh halaman otomatis
    function submitComment(postId, form) {
      form.preventDefault();
      const commentInput = form.target.querySelector('input[name="comment"]');
      const comment = commentInput.value.trim();
      if (!comment) return;
      const formData = new FormData(form.target);
      fetch('comment.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Refresh halaman secara otomatis setelah komentar berhasil ditambahkan
              window.location.reload();
          } else {
              alert('Gagal menambahkan komentar: ' + data.error);
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('Terjadi kesalahan saat menambahkan komentar');
      });
    }

    function createCommentElement(comment) {
      const div = document.createElement('div');
      div.className = 'flex gap-3 mb-3';
      div.id = `comment-${comment.id}`;
      const currentUserId = <?php echo $user_id; ?>;
      const deleteButton = comment.user_id === currentUserId ? 
          `<button onclick="deleteComment(${comment.id}, ${comment.post_id})" 
                   class="text-red-500 hover:text-red-700 ml-2" 
                   title="Delete comment">
              <i class="fas fa-trash-alt"></i>
          </button>` : '';
      const createdAt = comment.created_at || '';
      div.innerHTML = `
          <img src="../assets/uploads/${comment.profile_pic || 'default.png'}" 
               alt="Profile Picture"
               class="w-8 h-8 rounded-full object-cover">
          <div class="bg-gray-50 rounded-lg p-3 flex-1">
              <div class="flex justify-between items-center mb-1">
                  <a href="profile.php?user_id=${comment.user_id}" 
                     class="font-bold text-sm text-blue-500 hover:underline">
                      @${comment.username}
                  </a>
                  <div class="flex items-center">
                      <span class="text-xs text-gray-500">
                          ${createdAt}
                      </span>
                      ${deleteButton}
                  </div>
              </div>
              <p class="text-gray-800 text-sm">${comment.comment}</p>
          </div>
      `;
      return div;
    }
  </script>
  <style>
    /* Jika ada CSS tambahan, tambahkan di sini */
  </style>
</head>
<body class="bg-gray-100">
  <!-- Navigation -->
  <nav class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-50">
      <h1 class="text-2xl font-bold">UpNote</h1>
      <!-- Form Search -->
      <form action="search.php" method="GET" class="w-1/3">
          <input type="text" name="q" placeholder="Search..." class="border rounded-lg p-2 w-full">
      </form>
      <div class="flex items-center space-x-4">
          <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="theme-toggle" onclick="toggleTheme()" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-500 rounded-full peer peer-checked:after:translate-x-full after:absolute after:top-0.5 after:left-1 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
          <a href="profile.php?user_id=<?php echo $user_id; ?>" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Profile</a>
          <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Logout</a>
      </div>
  </nav>

  <!-- Alert Messages -->
  <?php if (isset($_GET['error'])): ?>
      <div class="mx-8 mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
          <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
  <?php endif; ?>
  <?php if (isset($_GET['success'])): ?>
      <div class="mx-8 mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
          <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
  <?php endif; ?>
  
  <!-- Main Content Container -->
  <div class="flex mt-4 mx-8 space-x-6">
      <!-- Sidebar -->
      <aside class="w-1/4 bg-white p-4 shadow-md rounded-lg h-fit sticky top-20">
          <div class="text-center">
              <img src="../assets/uploads/<?php echo !empty($userData['profile_pic']) ? htmlspecialchars($userData['profile_pic']) : 'default.png'; ?>" 
                   alt="Profile Picture"
                   class="w-24 h-24 rounded-full mx-auto object-cover">
              <h2 class="font-bold text-lg mt-2">@<?php echo htmlspecialchars($userData['username'] ?? 'Unknown'); ?></h2>
              <p class="text-gray-600"><?php echo htmlspecialchars($userData['bio'] ?? 'No bio available.'); ?></p>
          </div>
          <ul class="mt-4">
              <li class="p-2 hover:bg-gray-200 rounded">
                  <a href="home.php" class="flex items-center">
                      <i class="fas fa-home mr-2"></i> Home
                  </a>
              </li>
              <li class="p-2 hover:bg-gray-200 rounded">
                  <a href="messages.php" class="flex items-center">
                      <i class="fas fa-envelope mr-2"></i> Messages
                  </a>
              </li>
              <li class="p-2 hover:bg-gray-200 rounded">
                  <a href="notifications.php" class="flex items-center">
                      <i class="fas fa-bell mr-2"></i> Notifications
                  </a>
              </li>
             
          </ul>
      </aside>
      
      <!-- Main Content -->
      <main class="w-1/2">
          <!-- Create Post Form -->
          <div class="bg-white p-4 rounded-lg shadow-md mb-4">
              <form action="create_post.php" method="POST" enctype="multipart/form-data" id="postForm">
                  <textarea name="post_content" 
                          placeholder="What's on your mind?" 
                          class="w-full p-2 border rounded resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                          rows="3"
                          required
                          maxlength="1000"></textarea>
                  
                  <!-- Image Preview -->
                  <div id="preview-container" class="hidden mt-2">
                      <img id="image-preview" class="max-h-60 rounded" alt="Preview">
                      <button type="button" onclick="removePreview()" class="text-red-500 mt-2">
                          <i class="fas fa-times"></i> Remove Image
                      </button>
                  </div>
                  <!-- Video Preview -->
                  <div id="video-preview-container" class="hidden mt-2">
                      <video id="video-preview" class="max-h-60 rounded" controls style="max-width:300px; margin:auto;" autoplay muted playsinline></video>
                      <button type="button" onclick="removeVideo()" class="text-red-500 mt-2">
                          <i class="fas fa-times"></i> Remove Video
                      </button>
                  </div>
                  
                  <div class="mt-2 flex justify-between items-center">
                      <div class="flex items-center space-x-2">
                          <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">
                              <i class="fas fa-image mr-2"></i> Add Photo
                              <input type="file" 
                                  name="post_image" 
                                  class="hidden" 
                                  accept="image/*"
                                  onchange="previewImage(this)">
                          </label>
                          <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">
                              <i class="fas fa-video mr-2"></i> Add Video
                              <input type="file" 
                                  name="post_video" 
                                  class="hidden" 
                                  accept="video/*"
                                  onchange="previewVideo(this)">
                          </label>
                      </div>
                      <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                          Post
                      </button>
                  </div>
              </form>
          </div>
          
          <!-- Posts Grid -->
          <div class="grid grid-cols-1 gap-6">
          <?php if ($postsResult): 
              while ($post = mysqli_fetch_assoc($postsResult)): ?>
              <div class="bg-white p-4 rounded-lg shadow-md">
                  <!-- Post Header -->
                  <div class="flex justify-between items-center mb-4">
                      <div class="flex items-center">
                          <img src="../assets/uploads/<?php echo !empty($post['profile_pic']) ? htmlspecialchars($post['profile_pic']) : 'default.png'; ?>" 
                               alt="Profile Picture"
                               class="w-10 h-10 rounded-full mr-3 object-cover">
                          <div>
                              <h3 class="font-bold">
                                  <a href="profile.php?user_id=<?php echo $post['user_id']; ?>" class="text-blue-500 hover:underline">
                                      @<?php echo htmlspecialchars($post['username']); ?>
                                  </a>
                              </h3>
                              <span class="text-gray-500 text-sm">
                                  <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                              </span>
                          </div>
                      </div>
                      <?php if ($post['user_id'] == $user_id): ?>
                          <a href="delete_post.php?post_id=<?php echo $post['id']; ?>" 
                             class="text-red-500 hover:text-red-700"
                             onclick="return confirm('Are you sure you want to delete this post?')">
                              <i class="fas fa-trash"></i>
                          </a>
                      <?php else: ?>
                          <a href="hide_post.php?post_id=<?php echo $post['id']; ?>" class="text-gray-500 hover:text-gray-700">
                              <i class="fas fa-eye-slash"></i>
                          </a>
                      <?php endif; ?>
                  </div>
                  
                  <!-- Post Content -->
                  <div onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'" class="cursor-pointer">
                      <p class="text-gray-800 mb-4">
                          <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                      </p>
                      <?php if (!empty($post['image'])): ?>
                          <img src="../assets/uploads/<?php echo htmlspecialchars($post['image']); ?>" 
                               alt="Post Image" 
                               class="rounded-lg w-full mb-4">
                      <?php endif; ?>
                      <?php if (!empty($post['video'])): ?>
                          <video src="../assets/uploads/<?php echo htmlspecialchars($post['video']); ?>" 
                                 class="rounded-lg mb-4 mx-auto" style="max-width:300px;" controls autoplay muted playsinline></video>
                      <?php endif; ?>
                  </div>
                  
                  <!-- Interaction Buttons -->
                  <div class="flex justify-between items-center pt-4 border-t">
                      <div class="flex gap-4">
                          <!-- Like Button -->
                          <button id="like-btn-<?php echo $post['id']; ?>"
                              onclick="handleReaction(<?php echo $post['id']; ?>, 'like', this)" 
                                  class="flex items-center gap-1 <?php echo ($post['user_reaction'] === 'like') ? 'text-blue-600' : 'text-gray-500'; ?> hover:text-blue-600">
                              <i class="fas fa-thumbs-up"></i>
                              <span id="likes-<?php echo $post['id']; ?>"><?php echo $post['likes_count']; ?></span>
                          </button>
              
                          <!-- Dislike Button -->
                          <button id="dislike-btn-<?php echo $post['id']; ?>"
                              onclick="handleReaction(<?php echo $post['id']; ?>, 'dislike', this)" 
                                  class="flex items-center gap-1 <?php echo ($post['user_reaction'] === 'dislike') ? 'text-red-600' : 'text-gray-500'; ?> hover:text-red-600">
                              <i class="fas fa-thumbs-down"></i>
                              <span id="dislikes-<?php echo $post['id']; ?>"><?php echo $post['dislikes_count']; ?></span>
                          </button>
                      </div>
                      <!-- Comment Button -->
                      <button onclick="toggleComments(<?php echo $post['id']; ?>)" 
                              class="text-gray-500 hover:text-gray-700">
                          <i class="fas fa-comment"></i>
                          <span id="comment-count-<?php echo $post['id']; ?>"><?php echo $post['comments_count']; ?></span> Komentar
                      </button>
                  </div>
                  
                  <!-- Comments Section -->
                  <div id="comments-<?php echo $post['id']; ?>" class="hidden mt-4">
                      <form onsubmit="submitComment(<?php echo $post['id']; ?>, event)" class="mb-4">
                          <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                          <div class="flex gap-2">
                              <input type="text" 
                                     name="comment" 
                                     placeholder="Tulis komentar..." 
                                     class="flex-1 p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                     required>
                              <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                  Kirim
                              </button>
                          </div>
                      </form>
          
                      <div class="comments-list">
                          <?php
                          $commentQuery = "SELECT c.*, u.username, u.profile_pic 
                                         FROM comments c 
                                         JOIN users u ON c.user_id = u.id 
                                         WHERE c.post_id = ? 
                                         ORDER BY c.created_at DESC
                                         LIMIT 10";
                          $commentStmt = mysqli_prepare($conn, $commentQuery);
                          mysqli_stmt_bind_param($commentStmt, "i", $post['id']);
                          mysqli_stmt_execute($commentStmt);
                          $comments = mysqli_stmt_get_result($commentStmt);
                          while ($comment = mysqli_fetch_assoc($comments)): ?>
                              <div class="flex gap-3 mb-3" id="comment-<?php echo $comment['id']; ?>">
                                  <img src="../assets/uploads/<?php echo !empty($comment['profile_pic']) ? htmlspecialchars($comment['profile_pic']) : 'default.png'; ?>" 
                                       alt="Profile Picture"
                                       class="w-8 h-8 rounded-full object-cover">
                                  <div class="bg-gray-50 rounded-lg p-3 flex-1">
                                      <div class="flex justify-between items-center mb-1">
                                          <a href="profile.php?user_id=<?php echo $comment['user_id']; ?>" 
                                             class="font-bold text-sm text-blue-500 hover:underline">
                                              @<?php echo htmlspecialchars($comment['username']); ?>
                                          </a>
                                          <div class="flex items-center">
                                              <span class="text-xs text-gray-500">
                                                  <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                              </span>
                                              <?php if ($comment['user_id'] == $user_id): ?>
                                                  <button onclick="deleteComment(<?php echo $comment['id']; ?>, <?php echo $post['id']; ?>)" 
                                                          class="text-red-500 hover:text-red-700 ml-2" 
                                                          title="Delete comment">
                                                      <i class="fas fa-trash-alt"></i>
                                                  </button>
                                              <?php endif; ?>
                                          </div>
                                      </div>
                                      <p class="text-gray-800 text-sm"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                  </div>
                              </div>
                          <?php endwhile; ?>
                      </div>
                  </div>
              </div>
          <?php endwhile; endif; ?>
          </div>
      </main>
      
      <!-- Trending Posts (kanan) - Slide Show -->
      <aside class="w-1/4 bg-white p-4 shadow-md rounded-lg h-fit sticky top-20">
          <h2 class="font-bold text-lg mb-4">Trending Posts 🔥🔥🔥</h2>
          <div id="trending-slider" class="relative">
              <?php foreach ($trendingPosts as $trending): ?>
                  <div class="trending-slide hidden">
                      <div class="flex items-center mb-2">
                          <img src="../assets/uploads/<?php echo !empty($trending['profile_pic']) ? htmlspecialchars($trending['profile_pic']) : 'default.png'; ?>" 
                               alt="Profile Picture"
                               class="w-8 h-8 rounded-full mr-2 object-cover">
                          <a href="profile.php?user_id=<?php echo $trending['user_id']; ?>" 
                             class="font-bold text-blue-500 hover:underline">
                              @<?php echo htmlspecialchars($trending['username']); ?>
                          </a>
                      </div>
                      <?php if (!empty($trending['image'])): ?>
                          <div class="mb-2">
                              <img src="../assets/uploads/<?php echo htmlspecialchars($trending['image']); ?>" 
                                   alt="Post Image" 
                                   class="rounded-lg w-full">
                          </div>
                      <?php elseif(!empty($trending['video'])): ?>
                          <div class="mb-2">
                              <video src="../assets/uploads/<?php echo htmlspecialchars($trending['video']); ?>" 
                                     class="rounded-lg mb-2 mx-auto" style="max-width:300px;" controls autoplay muted playsinline></video>
                          </div>
                      <?php endif; ?>
                      <p class="text-gray-800 text-sm mb-2">
                          <?php 
                          $content = htmlspecialchars($trending['content']);
                          echo (strlen($content) > 100) ? substr($content, 0, 100) . '...' : $content;
                          ?>
                      </p>
                      <div class="flex items-center text-sm text-gray-500">
                          <span class="mr-3">
                              <i class="fas fa-thumbs-up"></i>
                              <?php echo $trending['likes_count']; ?>
                          </span>
                          <span>
                              <i class="fas fa-comment"></i>
                              <?php echo $trending['comments_count']; ?>
                          </span>
                      </div>
                  </div>
              <?php endforeach; ?>
          </div>
      </aside>
  </div>
</body>
</html>
