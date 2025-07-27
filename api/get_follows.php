<?php
require_once "../includes/db.php";
header('Content-Type: application/json');

$username = $_GET['user'] ?? '';
$type = $_GET['type'] ?? 'followers'; 

if (empty($username)) {
    echo json_encode([]);
    exit;
}

$stmt_user = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
if ($user_result->num_rows === 0) {
    echo json_encode([]);
    exit;
}
$user_id = $user_result->fetch_assoc()['id'];
$stmt_user->close();

$sql = "";
if ($type === 'followers') {
    $sql = "SELECT u.username, u.display_name, u.profile_picture FROM followers f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ?";
} elseif ($type === 'following') {
    $sql = "SELECT u.username, u.display_name, u.profile_picture FROM followers f JOIN users u ON f.following_id = u.id WHERE f.follower_id = ?";
} else {
    echo json_encode([]);
    exit;
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($users);
$mysqli->close();