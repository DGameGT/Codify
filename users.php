<?php
header("Content-Type: application/json; charset=UTF-8");

define('RATE_LIMIT_COUNT', 40);
define('RATE_LIMIT_WINDOW', 360);

try {
    if (!file_exists(__DIR__ . "/includes/db.php")) {
        throw new Exception("Database configuration file not found");
    }
    if (!file_exists(__DIR__ . "/includes/functions.php")) {
        throw new Exception("Functions file not found");
    }

    require_once __DIR__ . "/includes/db.php";
    require_once __DIR__ . "/includes/functions.php";

    if (!isset($mysqli) || $mysqli->connect_error) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
    exit;
}

$auth_header = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
    }
}

if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Bearer token tidak ditemukan.']);
    exit;
}

$api_key = $matches[1];
$user = getUserByApiKey($mysqli, $api_key);

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: API Key tidak valid.']);
    exit;
}

handleRateLimit($mysqli, $user);
logApiRequest($mysqli, $user);

$method = $_SERVER["REQUEST_METHOD"];
switch ($method) {
    case 'GET':
        handleGetList($mysqli, $user);
        break;
    case 'POST':
        handleUpload($mysqli, $user);
        break;
    case 'PUT':
        handleEdit($mysqli, $user);
        break;
    case 'DELETE':
        handleDelete($mysqli, $user);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid. Gunakan GET, POST, PUT, atau DELETE.']);
        break;
}

$mysqli->close();

function handleRateLimit($mysqli, $user) {
    if ($user['is_verified']) {
        return;
    }

    $user_id = $user['id'];
    $sql = "SELECT COUNT(id) as request_count FROM api_requests WHERE user_id = ? AND request_timestamp > NOW() - INTERVAL ? SECOND";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $window = RATE_LIMIT_WINDOW;
        $stmt->bind_param("ii", $user_id, $window);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result && $result['request_count'] >= RATE_LIMIT_COUNT) {
            http_response_code(429);
            echo json_encode([
                'status' => 'error',
                'message' => 'Anda telah melebihi batas 40 permintaan per jam. Silakan coba lagi nanti.'
            ]);
            exit;
        }
    }
}

function logApiRequest($mysqli, $user) {
    $sql = "INSERT INTO api_requests (user_id) VALUES (?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();
    }
}

function generateShareId($mysqli, $length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charLength = strlen($characters);
    do {
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charLength - 1)];
        }
        $stmt = $mysqli->prepare("SELECT id FROM codes WHERE share_id = ?");
        $stmt->bind_param("s", $randomString);
        $stmt->execute();
        $stmt->store_result();
        $is_unique = ($stmt->num_rows == 0);
        $stmt->close();
    } while (!$is_unique);
    return $randomString;
}

function getSupportedLanguages() {
    return [
        'plaintext' => 'Plain Text', 'html' => 'HTML', 'css' => 'CSS',
        'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'php' => 'PHP',
        'python' => 'Python', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++',
        'c' => 'C', 'ruby' => 'Ruby', 'go' => 'Go', 'rust' => 'Rust', 'swift' => 'Swift',
        'kotlin' => 'Kotlin', 'scala' => 'Scala', 'sql' => 'SQL', 'bash' => 'Bash',
        'json' => 'JSON', 'yaml' => 'YAML', 'markdown' => 'Markdown',
        'dockerfile' => 'Dockerfile', 'vue' => 'Vue', 'svelte' => 'Svelte',
        'angularjs' => 'Angular'
    ];
}

function handleUpload($mysqli, $user) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: Format JSON tidak valid.']);
        exit;
    }

    $title = isset($data['title']) ? trim($data['title']) : '';
    $content = isset($data['code_content']) ? trim($data['code_content']) : '';
    $language = isset($data['language']) ? htmlspecialchars(trim($data['language'])) : 'plaintext';

    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: Judul (title) dan konten kode (code_content) wajib diisi.']);
        exit;
    }

    if (!array_key_exists($language, getSupportedLanguages())) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: Language tidak didukung.']);
        exit;
    }

    $share_id = generateShareId($mysqli);
    $sql = "INSERT INTO codes (user_id, share_id, title, code_content, language) VALUES (?, ?, ?, ?, ?)";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("issss", $user['id'], $share_id, $title, $content, $language);
        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Kode berhasil dibagikan.',
                'code_id' => $mysqli->insert_id,
                'share_id' => $share_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan cuplikan kode.']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan statement MySQL.']);
    }
}

function handleEdit($mysqli, $user) {
    $share_id = isset($_GET['share_id']) ? trim($_GET['share_id']) : '';
    if (empty($share_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: parameter share_id dibutuhkan.']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: Format JSON tidak valid.']);
        exit;
    }

    $title = isset($data['title']) ? trim($data['title']) : '';
    $content = isset($data['code_content']) ? trim($data['code_content']) : '';
    $language = isset($data['language']) ? htmlspecialchars(trim($data['language'])) : 'plaintext';

    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: Judul (title) dan konten kode (code_content) wajib diisi.']);
        exit;
    }

    $sql = "UPDATE codes SET title = ?, code_content = ?, language = ? WHERE share_id = ? AND user_id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ssssi", $title, $content, $language, $share_id, $user['id']);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Kode berhasil diperbarui.']);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Kode tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui kode.']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan statement MySQL.']);
    }
}

function handleDelete($mysqli, $user) {
    $share_id = isset($_GET['share_id']) ? trim($_GET['share_id']) : '';
    if (empty($share_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bad Request: parameter share_id dibutuhkan.']);
        exit;
    }

    $sql = "DELETE FROM codes WHERE share_id = ? AND user_id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("si", $share_id, $user['id']);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Kode berhasil dihapus.']);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Kode tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus kode.']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan statement MySQL.']);
    }
}

function handleGetList($mysqli, $user) {
    $user_id = $user['id'];
    $sql = "SELECT share_id, title FROM codes WHERE user_id = ? ORDER BY created_at DESC";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $codes = [];
            while ($row = $result->fetch_assoc()) {
                $codes[] = $row;
            }
            $stmt->close();

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $codes
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil daftar kode.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan statement MySQL.']);
    }
}