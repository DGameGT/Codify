<?php
// Set header untuk respons JSON
header("Content-Type: application/json; charset=UTF-8");

// Muat file konfigurasi database dan fungsi
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Set metode request yang diizinkan
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid. Hanya POST yang diizinkan.']);
    exit;
}

// Ambil Bearer token dari header Authorization
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Bearer token tidak ditemukan.']);
    exit;
}

$api_key = $matches[1];
$user = getUserByApiKey($mysqli, $api_key);

// Validasi API Key
if (!$user) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: API Key tidak valid.']);
    exit;
}

// Ambil dan decode data JSON dari body request
$data = json_decode(file_get_contents("php://input"), true);

// Periksa apakah JSON valid
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Format JSON tidak valid.']);
    exit;
}

// Validasi input yang diperlukan
$title = trim($data['title'] ?? '');
$content = trim($data['code_content'] ?? '');
$language = htmlspecialchars(trim($data['language'] ?? 'plaintext')); // Keamanan tambahan

if (empty($title) || empty($content)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Judul (title) dan konten kode (code_content) wajib diisi.']);
    exit;
}

// Siapkan dan eksekusi query untuk menyimpan kode
$sql = "INSERT INTO codes (user_id, title, code_content, language) VALUES (?, ?, ?, ?)";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("isss", $user['id'], $title, $content, $language);
    
    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode([
            'status' => 'success', 
            'message' => 'Kode berhasil dibagikan.'
        ]);
    } else {
        http_response_code(500); 
        echo json_encode([
            'status' => 'error', 
            'message' => 'Gagal menyimpan cuplikan kode ke database.'
        ]);
    }
    $stmt->close();
} else {
    http_response_code(500); 
    echo json_encode([
        'status' => 'error', 
        'message' => 'Gagal mempersiapkan statement MYSQL Silahkan Lapor Owner.'
    ]);
}

$mysqli->close();
?>