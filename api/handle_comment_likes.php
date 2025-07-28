<?php
header('Content-Type: application/json');

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

$response = ['status' => 'error', 'message' => 'Invalid request.'];
$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $comment_id = $data['comment_id'] ?? null;

    if (!$comment_id) {
        $response['message'] = 'Comment ID is missing.';
    } else {
        // Cek apakah user sudah like sebelumnya
        $stmt_check = $mysqli->prepare("SELECT * FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt_check->bind_param("ii", $comment_id, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $is_liked = $result_check->num_rows > 0;
        $stmt_check->close();
        
        $mysqli->begin_transaction();
        try {
            if ($is_liked) {
                // Unlike
                $stmt_like = $mysqli->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
                $stmt_like->bind_param("ii", $comment_id, $user_id);
                $stmt_like->execute();
                
                $stmt_count = $mysqli->prepare("UPDATE comments SET likes_count = likes_count - 1 WHERE id = ?");
                $stmt_count->bind_param("i", $comment_id);
                $stmt_count->execute();
                
                $response = ['status' => 'success', 'action' => 'unliked'];
            } else {
                // Like
                $stmt_like = $mysqli->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
                $stmt_like->bind_param("ii", $comment_id, $user_id);
                $stmt_like->execute();
                
                $stmt_count = $mysqli->prepare("UPDATE comments SET likes_count = likes_count + 1 WHERE id = ?");
                $stmt_count->bind_param("i", $comment_id);
                $stmt_count->execute();

                $response = ['status' => 'success', 'action' => 'liked'];
            }
            $stmt_like->close();
            $stmt_count->close();
            $mysqli->commit();

        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            $response['message'] = 'Transaction failed: ' . $exception->getMessage();
        }
    }
}

$mysqli->close();
echo json_encode($response);
?>