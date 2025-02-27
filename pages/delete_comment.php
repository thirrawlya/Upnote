<?php
error_log('Delete comment request received: ' . json_encode($_POST));

session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$response = ['success' => false];
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment_id'])) {
    $comment_id = intval($_POST['comment_id']);
    error_log("Processing delete request for comment ID: $comment_id by user ID: $user_id");
    
    // Check if the comment belongs to the current user
    $check = $conn->prepare("SELECT * FROM comments WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $comment_id, $user_id);
    $check->execute();
    $result = $check->get_result(); 
    
    error_log("Comment ownership check result: " . $result->num_rows . " rows");
    
    if ($result->num_rows > 0) {
        // Get post_id before deleting for updating comment count
        $comment_data = $result->fetch_assoc();
        $post_id = $comment_data['post_id'];
        error_log("Found comment for post ID: $post_id");
        
        // Delete the comment
        $delete = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $delete->bind_param("i", $comment_id);
        
        if ($delete->execute()) {
            error_log("Comment deleted successfully");
            // Get updated comment count
            $count_query = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
            $count_query->bind_param("i", $post_id);
            $count_query->execute();
            $count_result = $count_query->get_result()->fetch_assoc();
            
            $response = [
                'success' => true,
                'message' => 'Comment deleted successfully',
                'post_id' => $post_id,
                'comments_count' => $count_result['count']
            ];
            error_log("Response: " . json_encode($response));
        } else {
            error_log("Failed to delete comment: " . $conn->error);
            $response['error'] = "Failed to delete comment";
        }
    } else {
        error_log("Permission denied: Comment does not belong to current user");
        $response['error'] = "You don't have permission to delete this comment";
    }
} else {
    error_log("Invalid request or missing comment_id");
    $response['error'] = "Invalid request";
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>