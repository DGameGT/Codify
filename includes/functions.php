<?php
session_start();

/**
 * Cek apakah user sudah login atau belum.
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

/**
 * Membuat API Key acak.
 * @param int $length
 * @return string
 */
function generateApiKey($length = 15)
{
    return bin2hex(random_bytes($length));
}

/**
 * Mengambil data user berdasarkan API Key.
 * @param mysqli $mysqli
 * @param string $apiKey
 * @return array|false
 */
function getUserByApiKey($mysqli, $apiKey)
{
    $sql = "SELECT id, username FROM users WHERE api_key = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $apiKey);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                return $result->fetch_assoc();
            }
        }
    }
    return false;
}

/**
 * Menyimpan file yang di-upload.
 * @param array $file
 * @param string $destinationDir
 * @param string $baseFilename
 * @return array
 */
function saveUploadedFile($file, $destinationDir, $baseFilename)
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['error' => 'Invalid parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['error' => 'No file sent.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['error' => 'Exceeded filesize limit.'];
        default:
            return ['error' => 'Unknown upload error.'];
    }

    // Batas ukuran file 5MB
    if ($file['size'] > 5000000) { 
        return ['error' => 'Exceeded filesize limit (5MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed_exts = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $ext = array_search($finfo->file($file['tmp_name']), $allowed_exts, true);

    if ($ext === false) {
        return ['error' => 'Invalid file format. Only JPG, PNG, GIF, WEBP are allowed.'];
    }

    $newFilename = $baseFilename . '_' . time() . '.' . $ext;
    $destinationPath = rtrim($destinationDir, '/') . '/' . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        return ['error' => 'Failed to move uploaded file. Check directory permissions.'];
    }

    return ['success' => true, 'filename' => $newFilename];
}

/**
 * Menyimpan gambar dari URL.
 * @param string $url
 * @param string $destinationDir
 * @param string $baseFilename
 * @return array
 */
function saveImageFromUrl($url, $destinationDir, $baseFilename)
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['error' => 'Invalid URL provided.'];
    }

    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        return ['error' => 'Could not retrieve image from URL.'];
    }

    // Batas ukuran file 5MB
    if (strlen($imageData) > 5000000) {
        return ['error' => 'Image from URL exceeds size limit (5MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed_exts = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $ext = array_search($finfo->buffer($imageData), $allowed_exts, true);

    if ($ext === false) {
        return ['error' => 'Invalid image format from URL.'];
    }

    $newFilename = $baseFilename . '_' . time() . '.' . $ext;
    $destinationPath = rtrim($destinationDir, '/') . '/' . $newFilename;

    if (!file_put_contents($destinationPath, $imageData)) {
        return ['error' => 'Failed to save image from URL. Check directory permissions.'];
    }

    return ['success' => true, 'filename' => $newFilename];
}

?>