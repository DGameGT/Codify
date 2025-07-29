<?php
header('Content-Type: application/json');
require_once "../includes/db.php";
require_once "../includes/functions.php";

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if (!isLoggedIn()) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

// Verifikasi bahwa pengguna adalah Owner
$current_user_id = $_SESSION['id'];
$user_role = '';
$stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    $user_role = $user['role'];
}
$stmt->close();

if (strtolower($user_role) !== 'owner') {
    $response['message'] = 'You do not have permission to perform this action.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $data['user_id'] ?? null;
    $action = $data['action'] ?? null;
    $value = $data['value'] ?? null;

    if (!$target_user_id || !$action) {
        $response['message'] = 'Missing parameters.';
    } else {
        $sql = "";
        $stmt_update = null;
        
        if ($action === 'change_role' && in_array($value, ['user', 'moderator', 'admin', 'owner'])) {
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt_update = $mysqli->prepare($sql);
            $stmt_update->bind_param("si", $value, $target_user_id);
        } elseif ($action === 'toggle_verified' && is_bool($value)) {
            $sql = "UPDATE users SET is_verified = ? WHERE id = ?";
            $stmt_update = $mysqli->prepare($sql);
            $stmt_update->bind_param("ii", $value, $target_user_id);
        } else {
            $response['message'] = 'Invalid action or value.';
        }

        if ($stmt_update && $stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $response = ['status' => 'success', 'message' => 'User updated successfully.'];
            } else {
                $response = ['status' => 'success', 'message' => 'No changes were made.'];
            }
        } elseif ($stmt_update) {
            $response['message'] = 'Failed to update user.';
        }
        if($stmt_update) $stmt_update->close();
    }
}

$mysqli->close();
echo json_encode($response);
?>