<?php
require_once "../includes/db.php";
require_once "../includes/functions.php";
session_start();
header('Content-Type: application/json');

if (!isLoggedIn() || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = $data['target_user_id'] ?? null;
$action = $data['action'] ?? null;

if (!$target_user_id || !$action || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

if ($current_user_id == $target_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot follow yourself.']);
    exit;
}

if ($action === 'follow') {
    $sql = "INSERT INTO followers (follower_id, following_id) VALUES (?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ii", $current_user_id, $target_user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to follow user.']);
    }
    $stmt->close();
} elseif ($action === 'unfollow') {
    $sql = "DELETE FROM followers WHERE follower_id = ? AND following_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ii", $current_user_id, $target_user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unfollow user.']);
    }
    $stmt->close();
}

$mysqli->close();