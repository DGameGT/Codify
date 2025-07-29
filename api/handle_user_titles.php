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

$current_user_id = $_SESSION['id'];
$user_role = '';
$stmt_role = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
$stmt_role->bind_param("i", $current_user_id);
$stmt_role->execute();
$result_role = $stmt_role->get_result();
if ($user = $result_role->fetch_assoc()) {
    $user_role = $user['role'];
}
$stmt_role->close();

if (strtolower($user_role) !== 'owner') {
    $response['message'] = 'You do not have permission to perform this action.';
    echo json_encode($response);
    exit;
}

$target_user_id = $_GET['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $target_user_id) {
    // Mengambil gelar yang dimiliki oleh user tertentu
    $sql = "SELECT title_id FROM user_titles WHERE user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_titles = [];
    while($row = $result->fetch_assoc()) {
        $assigned_titles[] = $row['title_id'];
    }
    $stmt->close();
    $response = ['status' => 'success', 'assigned_title_ids' => $assigned_titles];

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $data['user_id'] ?? null;
    $title_ids = $data['title_ids'] ?? [];

    if ($target_user_id) {
        $mysqli->begin_transaction();
        try {
            // Hapus semua gelar lama dari pengguna ini
            $stmt_delete = $mysqli->prepare("DELETE FROM user_titles WHERE user_id = ?");
            $stmt_delete->bind_param("i", $target_user_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Masukkan gelar-gelar baru jika ada
            if (!empty($title_ids)) {
                $sql_insert = "INSERT INTO user_titles (user_id, title_id) VALUES (?, ?)";
                $stmt_insert = $mysqli->prepare($sql_insert);
                foreach ($title_ids as $title_id) {
                    $stmt_insert->bind_param("ii", $target_user_id, $title_id);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }
            
            $mysqli->commit();
            $response = ['status' => 'success', 'message' => 'User titles updated successfully.'];

        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            $response['message'] = 'Database transaction failed: ' . $exception->getMessage();
        }
    } else {
        $response['message'] = 'Target user ID is missing.';
    }
}

$mysqli->close();
echo json_encode($response);
?>