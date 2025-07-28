<?php
header('Content-Type: application/json');

require_once "../includes/db.php";
require_once "../includes/functions.php";

$response = ['status' => 'error', 'message' => 'Invalid request.'];
$share_id = $_GET['code_id'] ?? '';
$comment_id = $_GET['comment_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if (empty($share_id) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Snippet ID is missing for GET request.']);
    exit;
}

// Logika berdasarkan metode request
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Mengambil semua komentar untuk sebuah snippet
        $sql = "SELECT c.id, c.comment_text, c.created_at, c.parent_id, c.is_edited, c.likes_count, u.username, u.display_name, u.profile_picture 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                JOIN codes co ON c.code_id = co.id
                WHERE co.share_id = ? 
                ORDER BY c.created_at ASC";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $share_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $comments_flat = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Mengatur komentar menjadi berbalas (nested)
            $comments_nested = [];
            $comments_by_id = [];
            foreach ($comments_flat as $comment) {
                $comments_by_id[$comment['id']] = $comment;
                $comments_by_id[$comment['id']]['replies'] = [];
            }
            foreach ($comments_by_id as $id => &$comment) {
                if ($comment['parent_id'] && isset($comments_by_id[$comment['parent_id']])) {
                    $comments_by_id[$comment['parent_id']]['replies'][] = &$comment;
                }
            }
            foreach ($comments_by_id as $id => $comment) {
                if (!$comment['parent_id']) {
                    $comments_nested[] = $comment;
                }
            }

            $response = ['status' => 'success', 'comments' => $comments_nested];
        } else {
            $response['message'] = 'Database query failed.';
        }
        break;

    case 'POST':
        // Menambah komentar atau balasan
        if (!isLoggedIn()) {
            $response['message'] = 'Authentication required.';
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $comment_text = trim($data['comment_text'] ?? '');
        $parent_id = $data['parent_id'] ?? null;

        if (empty($comment_text)) {
            $response['message'] = 'Comment cannot be empty.';
            break;
        }

        $code_id = null;
        $stmt_get_id = $mysqli->prepare("SELECT id FROM codes WHERE share_id = ?");
        $stmt_get_id->bind_param("s", $share_id);
        $stmt_get_id->execute();
        $result_get_id = $stmt_get_id->get_result();
        if ($row = $result_get_id->fetch_assoc()) { $code_id = $row['id']; }
        $stmt_get_id->close();
        if ($code_id === null) {
            $response['message'] = 'Snippet not found.';
            break;
        }

        $sql = "INSERT INTO comments (code_id, user_id, parent_id, comment_text) VALUES (?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("iiis", $code_id, $user_id, $parent_id, $comment_text);
            if ($stmt->execute()) {
                $new_comment_id = $stmt->insert_id;
                // Ambil data baru untuk dikirim kembali
                 $sql_new = "SELECT c.id, c.comment_text, c.created_at, c.parent_id, c.is_edited, c.likes_count, u.username, u.display_name, u.profile_picture 
                            FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?";
                $stmt_new = $mysqli->prepare($sql_new);
                $stmt_new->bind_param("i", $new_comment_id);
                $stmt_new->execute();
                $new_comment = $stmt_new->get_result()->fetch_assoc();
                $stmt_new->close();
                $response = ['status' => 'success', 'comment' => $new_comment];
            } else {
                $response['message'] = 'Failed to save comment.';
            }
            $stmt->close();
        }
        break;

    case 'PUT':
        // Mengedit komentar
        if (!isLoggedIn() || !$comment_id) {
            $response['message'] = 'Authentication or Comment ID required.';
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $comment_text = trim($data['comment_text'] ?? '');
        if (empty($comment_text)) {
            $response['message'] = 'Comment text cannot be empty.';
            break;
        }

        $sql = "UPDATE comments SET comment_text = ?, is_edited = TRUE WHERE id = ? AND user_id = ?";
        if($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sii", $comment_text, $comment_id, $user_id);
            if($stmt->execute() && $stmt->affected_rows > 0) {
                $response = ['status' => 'success', 'message' => 'Comment updated.'];
            } else {
                $response['message'] = 'Could not update comment or you do not have permission.';
            }
            $stmt->close();
        }
        break;

    case 'DELETE':
        // Menghapus komentar
        if (!isLoggedIn() || !$comment_id) {
            $response['message'] = 'Authentication or Comment ID required.';
            break;
        }
        $sql = "DELETE FROM comments WHERE id = ? AND user_id = ?";
        if($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $comment_id, $user_id);
            if($stmt->execute() && $stmt->affected_rows > 0) {
                $response = ['status' => 'success', 'message' => 'Comment deleted.'];
            } else {
                $response['message'] = 'Could not delete comment or you do not have permission.';
            }
            $stmt->close();
        }
        break;
}

$mysqli->close();
echo json_encode($response);
?>