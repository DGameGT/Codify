<?php
// Set header ke JSON
header('Content-Type: application/json');

require_once "includes/db.php";
require_once "includes/functions.php";

// Pastikan pengguna sudah login
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

// Periksa apakah 'id' (share_id) ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Snippet ID is missing.']);
    exit;
}

$share_id = $_GET['id'];
$user_id = $_SESSION['id']; // Ambil user ID dari session untuk keamanan

$response = [];

// Siapkan query untuk mengambil detail kode
// Penting: tambahkan "AND user_id = ?" untuk memastikan pengguna hanya bisa mengambil datanya sendiri
$sql = "SELECT title, code_content, language FROM codes WHERE share_id = ? AND user_id = ?";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("si", $share_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $response = $result->fetch_assoc();
    } else {
        $response['error'] = 'Snippet not found or you do not have permission to edit it.';
    }
    $stmt->close();
} else {
    $response['error'] = 'Database query failed.';
}

$mysqli->close();

// Kembalikan data sebagai JSON
echo json_encode($response);