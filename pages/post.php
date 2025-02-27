<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../includes/db.php';

// Check if post_id is provided
if (!isset($_GET['id'])) {
    header("Location: home.php");
    exit();
}

$post_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch post details with user information and reaction counts
$postQuery = "
    SELECT 
        p.*,
        u.username,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'like') as likes_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND type = 'dislike') as dislikes_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
        (SELECT type FROM likes WHERE post_id = p.id AND user_id = ?) as user_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $postQuery);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($result);

// If post doesn't exist or is hidden, redirect to home
if (!$post || isset($_SESSION['hidden_posts'][$post_id])) {
    header("Location: home.php");
    exit();
}

// Fetch comments for the post
$commentQuery = "
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC";

$stmt = mysqli_prepare($conn, $commentQuery);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$comments = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post - UpNote</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function deleteComment(commentId) {
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
            // Remove the comment element
            const commentElement = document.querySelector(`#comment-${commentId}`);
            if (commentElement) {
                commentElement.remove();
            }
            
            // Update comment count
            const commentCount = document.querySelector(`#comments-count`);
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
        function handleReaction(postId, type) {
            fetch(`like.php?post_id=${postId}&type=${type}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`#likes-${postId}`).textContent = data.likes_count;
                    document.querySelector(`#dislikes-${postId}`).textContent = data.dislikes_count;
                    
                    // Update reaction styling
                    const likeBtn = document.querySelector(`#like-btn-${postId}`);
                    const dislikeBtn = document.querySelector(`#dislike-btn-${postId}`);
                    
                    if (type === 'like') {
                        likeBtn.classList.toggle('text-blue-600');
                        dislikeBtn.classList.remove('text-red-600');
                    } else {
                        dislikeBtn.classList.toggle('text-red-600');
                        likeBtn.classList.remove('text-blue-600');
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-md p-4 flex justify-between items-center">
        <a href="home.php" class="text-2xl font-bold">UpNote</a>
        <div class="flex items-center space-x-4">
            <a href="profile.php?user_id=<?php echo $_SESSION['user_id']; ?>" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Profile</a>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Logout</a>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto mt-8 px-4">
        <!-- Post -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <!-- Post Header -->
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center">
                    <img src="../assets/uploads/default.png" 
                         alt="Profile Picture" 
                         class="w-10 h-10 rounded-full mr-3">
                    <div>
                        <a href="profile.php?user_id=<?php echo $post['user_id']; ?>" 
                           class="font-bold text-blue-500 hover:underline">
                            @<?php echo htmlspecialchars($post['username']); ?>
                        </a>
                        <p class="text-gray-500 text-sm">
                            <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($post['user_id'] == $user_id): ?>
                    <a href="delete_post.php?post_id=<?php echo $post['id']; ?>" 
                       class="text-red-500 hover:text-red-700"
                       onclick="return confirm('Are you sure you want to delete this post?')">
                        <i class="fas fa-trash"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Post Content -->
            <p class="text-gray-800 mb-4"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
            
            <?php if (!empty($post['image'])): ?>
                <img src="../assets/uploads/<?php echo htmlspecialchars($post['image']); ?>" 
                     alt="Post Image"
                     class="rounded-lg w-full mb-4">
            <?php endif; ?>

            <!-- Interaction Buttons -->
            <div class="flex justify-between items-center pt-4 border-t">
                <div class="flex gap-4">
                    <button id="like-btn-<?php echo $post['id']; ?>"
                            onclick="handleReaction(<?php echo $post['id']; ?>, 'like')" 
                            class="flex items-center gap-1 <?php echo ($post['user_reaction'] === 'like') ? 'text-blue-600' : 'text-gray-500'; ?> hover:text-blue-600">
                        <i class="fas fa-thumbs-up"></i>
                        <span id="likes-<?php echo $post['id']; ?>"><?php echo $post['likes_count']; ?></span>
                    </button>
                    
                    <button id="dislike-btn-<?php echo $post['id']; ?>"
                            onclick="handleReaction(<?php echo $post['id']; ?>, 'dislike')" 
                            class="flex items-center gap-1 <?php echo ($post['user_reaction'] === 'dislike') ? 'text-red-600' : 'text-gray-500'; ?> hover:text-red-600">
                        <i class="fas fa-thumbs-down"></i>
                        <span id="dislikes-<?php echo $post['id']; ?>"><?php echo $post['dislikes_count']; ?></span>
                    </button>
                </div>
                
                <div class="text-gray-500">
                    <i class="fas fa-comment"></i>
                    <span id="comments-count"><?php echo $post['comments_count']; ?></span> Comments
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="mt-6">
            <h2 class="text-xl font-bold mb-4">Comments</h2>
            
            <!-- Comment Form -->
            <form action="comment.php" method="POST" class="mb-6">
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <div class="flex gap-2">
                    <input type="text" 
                           name="comment" 
                           placeholder="Write a comment..." 
                           class="flex-1 p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        Send
                    </button>
                </div>
            </form>

            <!-- Comments List -->
<?php while ($comment = mysqli_fetch_assoc($comments)): ?>
    <div id="comment-<?php echo $comment['id']; ?>" class="bg-white rounded-lg shadow-sm p-4 mb-4">
        <div class="flex gap-3">
            <img src="../assets/uploads/default.png" 
                 alt="Profile Picture"
                 class="w-8 h-8 rounded-full">
            <div class="flex-1">
                <div class="flex justify-between items-center mb-1">
                    <a href="profile.php?user_id=<?php echo $comment['user_id']; ?>" 
                       class="font-bold text-blue-500 hover:underline">
                        @<?php echo htmlspecialchars($comment['username']); ?>
                    </a>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-500 mr-2">
                            <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                        </span>
                        <?php if ($comment['user_id'] == $user_id): ?>
                            <button onclick="deleteComment(<?php echo $comment['id']; ?>)" 
                                    class="text-red-500 hover:text-red-700" 
                                    title="Delete comment">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
            </div>
        </div>
    </div>
<?php endwhile; ?>
        </div>
    </div>
</body>
</html>