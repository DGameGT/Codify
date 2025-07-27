<?php
require_once "includes/db.php";
require_once "includes/functions.php";

session_start();

$viewed_username = $_GET['user'] ?? '';
$current_user_logged_in = isLoggedIn();
$is_owner = false;
$user_data = null;
$snippets = [];
$message = "";

if (empty($viewed_username)) {
    if ($current_user_logged_in && isset($_SESSION['username'])) {
        header("Location: /profile?user=" . urlencode($_SESSION['username']));
        exit;
    } else {
        header("Location: /");
        exit;
    }
}

if (!isset($mysqli) || $mysqli->connect_error) {
    die("<h1>Database Connection Error</h1><p>Could not connect to the database. Please check your db.php configuration.</p>");
}

if ($current_user_logged_in && isset($_SESSION['username']) && strtolower($viewed_username) === strtolower($_SESSION['username'])) {
    $is_owner = true;
}

$sql_user = "SELECT id, username, display_name, api_key, bio, profile_picture, thumbnail, is_verified, social_links FROM users WHERE username = ?";
if (!($stmt_user = $mysqli->prepare($sql_user))) die("Database query error: " . $mysqli->error);
$stmt_user->bind_param("s", $viewed_username);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    http_response_code(404);
    die("<h1>404 User Not Found</h1><p>The user you are looking for does not exist.</p>");
}

if (empty($user_data['api_key'])) {
    $new_api_key = generateApiKey();
    $sql_key_init = "UPDATE users SET api_key = ? WHERE id = ?";
    if ($stmt_key_init = $mysqli->prepare($sql_key_init)) {
        $stmt_key_init->bind_param("si", $new_api_key, $user_data['id']);
        $stmt_key_init->execute();
        $stmt_key_init->close();
        $user_data['api_key'] = $new_api_key;
    }
}

$social_links = json_decode($user_data['social_links'] ?? '[]', true);

$sql_snippets = "SELECT share_id, title, code_content, language, created_at, view_count FROM codes WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt_snippets = $mysqli->prepare($sql_snippets)) {
    $stmt_snippets->bind_param("i", $user_data['id']);
    $stmt_snippets->execute();
    $result_snippets = $stmt_snippets->get_result();
    while ($row = $result_snippets->fetch_assoc()) {
        $snippets[] = $row;
    }
    $stmt_snippets->close();
}

if ($is_owner && $_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate_api_key') {
        $new_key = generateApiKey();
        $sql_update_key = "UPDATE users SET api_key = ? WHERE id = ?";
        if ($stmt_update_key = $mysqli->prepare($sql_update_key)) {
            $stmt_update_key->bind_param("si", $new_key, $user_data['id']);
            if ($stmt_update_key->execute()) {
                header("Location: profile.php?user=" . urlencode($user_data['username']) . "&status=key_updated");
                exit;
            }
        }
    }

    if ($action === 'save_changes') {
        $updates = [];
        $params = [];
        $types = '';
        $errors = [];

        $avatarDir = 'db/profile/';
        if (!is_dir($avatarDir)) @mkdir($avatarDir, 0755, true);
        
        if (!is_writable($avatarDir)) {
            $errors[] = "Avatar directory is not writable.";
        } else {
            if (!empty($_FILES['avatar_file']['name'])) {
                $result = saveUploadedFile($_FILES['avatar_file'], $avatarDir, "user_{$user_data['id']}");
                if (isset($result['success'])) {
                    $updates[] = "profile_picture = ?"; $params[] = $result['filename']; $types .= 's';
                } else { $errors[] = "Avatar Error: " . $result['error']; }
            } elseif (isset($_POST['avatar_url']) && !empty(trim($_POST['avatar_url']))) {
                $result = saveImageFromUrl(trim($_POST['avatar_url']), $avatarDir, "user_{$user_data['id']}");
                if (isset($result['success'])) {
                    $updates[] = "profile_picture = ?"; $params[] = $result['filename']; $types .= 's';
                } else { $errors[] = "Avatar URL Error: " . $result['error']; }
            }
        }
        
        // --- Banner Handling ---
        $bannerDir = 'db/thumbnails/';
        if (!is_dir($bannerDir)) @mkdir($bannerDir, 0755, true);

        if (!is_writable($bannerDir)) {
            $errors[] = "Banner directory is not writable.";
        } else {
            if (!empty($_FILES['banner_file']['name'])) {
                $result = saveUploadedFile($_FILES['banner_file'], $bannerDir, "thumb_user_{$user_data['id']}");
                if (isset($result['success'])) {
                    $updates[] = "thumbnail = ?"; $params[] = $result['filename']; $types .= 's';
                } else { $errors[] = "Banner Error: " . $result['error']; }
            } elseif (isset($_POST['banner_url']) && !empty(trim($_POST['banner_url']))) {
                $result = saveImageFromUrl(trim($_POST['banner_url']), $bannerDir, "thumb_user_{$user_data['id']}");
                if (isset($result['success'])) {
                    $updates[] = "thumbnail = ?"; $params[] = $result['filename']; $types .= 's';
                } else { $errors[] = "Banner URL Error: " . $result['error']; }
            }
        }

        // --- Other Fields ---
        if (isset($_POST['display_name']) && trim($_POST['display_name']) !== $user_data['display_name']) {
            $updates[] = "display_name = ?"; $params[] = trim($_POST['display_name']); $types .= 's';
        }
        if (isset($_POST['bio']) && trim($_POST['bio']) !== $user_data['bio']) {
            $updates[] = "bio = ?"; $params[] = trim($_POST['bio']); $types .= 's';
        }

        if ($user_data['is_verified'] && isset($_POST['api_key'])) {
            $custom_key = trim($_POST['api_key']);
            if (!empty($custom_key) && $custom_key !== $user_data['api_key']) {
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $custom_key)) {
                    $errors[] = "API Key can only contain letters, numbers, underscores, and dashes.";
                } else {
                    $updates[] = "api_key = ?"; $params[] = $custom_key; $types .= 's';
                }
            }
        }
        
        // FIXED: Replaced deprecated FILTER_SANITIZE_STRING with simple trim. Escaping is handled on output.
        $new_socials = [
            'github'    => trim($_POST['github'] ?? ''),
            'instagram' => trim($_POST['instagram'] ?? ''),
            'youtube'   => trim($_POST['youtube'] ?? ''),
            'website1'  => trim($_POST['website1'] ?? ''),
            'website2'  => trim($_POST['website2'] ?? ''),
        ];
        if (json_encode(array_filter($new_socials)) !== $user_data['social_links']) {
            $updates[] = "social_links = ?";
            $params[] = json_encode(array_filter($new_socials));
            $types .= 's';
        }
        
        // --- Final Decision Logic ---
        if (!empty($errors)) {
            $message = ["type" => "error", "text" => $errors[0]]; // Show the first error found
        } elseif (!empty($updates)) {
            $sql_update = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $params[] = $user_data['id'];

            if (!($stmt_update = $mysqli->prepare($sql_update))) {
                $message = ["type" => "error", "text" => "Database prepare error: " . $mysqli->error];
            } else {
                $stmt_update->bind_param($types, ...$params);
                if ($stmt_update->execute()) {
                    header("Location: profile?user=" . urlencode($user_data['username']) . "&status=updated");
                    exit;
                } else {
                    $message = ["type" => "error", "text" => "Database update failed."];
                }
                $stmt_update->close();
            }
        } else {
            // No changes were made, just refresh
            header("Location: profile?user=" . urlencode($user_data['username']));
            exit;
        }
    }
}

// Status messages from GET parameter
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'updated') {
        $message = ["type" => "success", "text" => "Profile updated successfully!"];
    } elseif ($_GET['status'] === 'key_updated') {
        $message = ["type" => "success", "text" => "API Key regenerated successfully!"];
    }
}

$social_icon_classes = [
    'github'    => 'fab fa-github',
    'instagram' => 'fab fa-instagram',
    'youtube'   => 'fab fa-youtube',
    'website1'  => 'fas fa-link',
    'website2'  => 'fas fa-link',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(!empty($user_data['display_name']) ? $user_data['display_name'] : $user_data['username']); ?>'s Profile - CSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--font-sans:'Inter',-apple-system,sans-serif;--font-mono:'JetBrains Mono',monospace;--radius:0.75rem;--transition:all 0.25s cubic-bezier(0.4,0,0.2,1);--bg-primary:#0A0A0A;--bg-secondary:#121212;--bg-tertiary:#1A1A1A;--border-color:#262626;--text-primary:#f5f5f5;--text-secondary:#a3a3a3;--accent-primary:#3B82F6;--accent-glow:rgba(59,130,246,0.5);--accent-danger:#F43F5E;--accent-success:#22C55E;}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:var(--font-sans);background-color:var(--bg-primary);color:var(--text-primary);text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased;}
        .site-header{background-color:var(--bg-secondary);border-bottom:1px solid var(--border-color);padding:1rem 2rem;margin-bottom:2rem;}
        .site-header a{color:var(--text-primary);text-decoration:none;font-size:1.5rem;font-weight:800;}
        .container{max-width:1280px;margin:0 auto;padding:0 2rem;display:grid;grid-template-columns:380px 1fr;gap:2.5rem;}
        @media (max-width:1024px){.container{grid-template-columns:1fr;}.profile-sidebar{position:static;}}
        @media (max-width:768px){.container{padding:0 1rem;gap:1.5rem;}.site-header{padding:1rem;margin-bottom:1rem;}.profile-name{font-size:1.5rem;}}
        .profile-sidebar{position:sticky;top:2rem;height:fit-content;animation:fadeIn 0.5s var(--transition);}
        .profile-card{background-color:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius);overflow:hidden;position:relative;}
        .profile-banner{height:140px;background-color:var(--bg-tertiary);}
        .profile-banner img{width:100%;height:100%;object-fit:cover;}
        .profile-info{padding:1.5rem;text-align:center;margin-top:-60px;}
        .profile-avatar{width:110px;height:110px;border-radius:50%;border:4px solid var(--bg-secondary);object-fit:cover;box-shadow:0 0 15px rgba(0,0,0,0.5);}
        .profile-name{font-size:1.75rem;font-weight:700;margin-top:1rem;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
        .verified-badge{width:1.3rem;height:1.3rem;vertical-align:-0.2rem;display:inline-block;}
        .profile-username{color:var(--text-secondary);margin-bottom:1rem;}
        .profile-bio{color:var(--text-secondary);line-height:1.6;}
        .social-links{padding:1rem 1.5rem;display:flex;justify-content:center;align-items:center;gap:1.25rem;border-top:1px solid var(--border-color);margin-top:1.5rem;}
        .social-links a{color:var(--text-secondary);display:inline-block;transition:var(--transition);font-size:1.4rem;}
        .social-links a:hover{color:var(--text-primary);transform:translateY(-2px);}
        .settings-trigger{position:absolute;top:1rem;right:1rem;background:rgba(0,0,0,0.3);backdrop-filter:blur(5px);border:1px solid var(--border-color);color:var(--text-primary);cursor:pointer;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;transition:var(--transition);z-index:10;}
        .settings-trigger:hover{background:var(--accent-primary);border-color:var(--accent-primary);transform:rotate(45deg);}
        .settings-trigger i{font-size:1rem;}
        .modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:1000;display:none;align-items:center;justify-content:center;animation:fadeIn 0.3s;}
        .modal-overlay.active{display:flex;}
        .modal-content{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius);width:90%;max-width:600px;box-shadow:0 10px 30px rgba(0,0,0,0.5);animation:fadeIn 0.3s 0.1s backwards;display:flex;flex-direction:column;}
        .modal-header{padding:1.25rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);}
        .modal-header h2{font-size:1.25rem;font-weight:600;}
        .modal-close{background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:1.5rem;transition:var(--transition);}
        .modal-close:hover{color:var(--text-primary);}
        .modal-body{padding:1.5rem;max-height:70vh;overflow-y:auto;}
        .modal-footer{padding:1.25rem;border-top:1px solid var(--border-color);text-align:right;background-color:var(--bg-tertiary);border-radius:0 0 var(--radius) var(--radius);display:flex;justify-content:flex-end;gap:0.75rem;}
        .btn{padding:0.6rem 1rem;border:none;border-radius:0.5rem;font-weight:500;cursor:pointer;transition:var(--transition);text-decoration:none;}
        .btn-primary{background-color:var(--accent-primary);color:#fff;}
        .btn-primary:hover{opacity:0.9;}
        .btn-secondary{background-color:var(--bg-tertiary);color:var(--text-primary);border:1px solid var(--border-color);}
        .btn-secondary:hover{background-color:#2a2a2a;border-color:#3a3a3a;}
        .form-group{margin-bottom:1.5rem;}
        .form-control{width:100%;padding:0.75rem;background-color:var(--bg-primary);border:1px solid var(--border-color);border-radius:0.5rem;color:var(--text-primary);font-size:1rem;transition:var(--transition);}
        .form-control:focus{outline:none;border-color:var(--accent-primary);box-shadow:0 0 0 3px rgba(59,130,246,0.2);}
        .input-group{display:flex;align-items:center;margin-bottom:1.25rem;}
        .input-group-icon{background-color:var(--bg-tertiary);border:1px solid var(--border-color);padding:0.75rem;border-radius:0.5rem 0 0 0.5rem;color:var(--text-secondary);}
        .input-group-icon i{width:1.2em;text-align:center;}
        .input-group .form-control{border-radius:0 0.5rem 0.5rem 0;border-left:none;}
        .snippets-section{animation:fadeIn 0.5s var(--transition) 0.1s backwards;}
        .section-title{font-size:1.75rem;font-weight:700;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border-color);}
        .snippets-grid{display:grid;grid-template-columns:1fr;gap:1rem;}
        .snippet-card{background-color:var(--bg-secondary);border-radius:var(--radius);text-decoration:none;color:var(--text-primary);display:flex;flex-direction:column;position:relative;transition:var(--transition);border:1px solid var(--border-color);}
        .snippet-card:before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;border-radius:var(--radius);border:1px solid transparent;background:radial-gradient(400px circle at var(--mouse-x) var(--mouse-y),var(--accent-glow),transparent 40%);z-index:0;opacity:0;transition:opacity 0.3s;}
        .snippet-card:hover:before{opacity:1;}
        .card-content{padding:1rem;z-index:1;flex-grow:1;display:flex;flex-direction:column;}
        .snippet-card h3{font-size:1.2rem;font-weight:600;margin-bottom:0.25rem;}
        .snippet-meta{color:var(--text-secondary);font-size:0.85rem;margin-bottom:1rem;}
        .snippet-preview{background-color:var(--bg-primary);border:1px solid var(--border-color);border-radius:0.5rem;overflow:hidden;font-size:0.8rem;margin-bottom:1rem;flex-grow:1;}
        .snippet-preview pre{margin:0;padding:0.75rem;}
        .snippet-preview code.hljs{padding:0!important;background:transparent!important;}
        .card-footer{display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);}
        .card-stats{display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--text-secondary);}
        .card-stats i{margin-right:0.25rem;}
        .lang-tag{display:flex;align-items:center;gap:0.5rem;background:var(--bg-tertiary);padding:0.2rem 0.6rem;border-radius:2rem;font-size:0.8rem;font-family:var(--font-mono);}
        .lang-dot{width:10px;height:10px;border-radius:50%;background-color:var(--text-secondary);}
        .alert-container{position:fixed;top:1.5rem;left:50%;transform:translateX(-50%);z-index:2000;width:90%;max-width:500px;}
        .alert{padding:1rem 1.5rem;border-radius:var(--radius);margin-bottom:1rem;font-weight:500;border:1px solid transparent;opacity:1;transition:opacity 0.5s ease-out;}
        .alert.success{background-color:#1A2E26;color:#34D399;border-color:#22C55E;}
        .alert.error{background-color:#3B1F23;color:#F87171;border-color:#F43F5E;}
        .alert.fade-out{opacity:0;}
        label{display:block;font-weight:500;margin-bottom:0.5rem;color:var(--text-secondary);}
        hr{border:none;border-top:1px solid var(--border-color);margin:1.5rem 0;}
        .upload-section{margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border-color);}
        .upload-section h3{font-size:1.1rem;font-weight:600;margin-bottom:1rem;}
        .upload-choice{display:flex;gap:0.5rem;margin-bottom:1rem;background:var(--bg-tertiary);padding:0.25rem;border-radius:0.5rem;}
        .upload-choice label{flex:1;text-align:center;padding:0.5rem;border-radius:0.375rem;cursor:pointer;transition:var(--transition);user-select:none;}
        .upload-choice input[type="radio"]{display:none;}
        .upload-choice input[type="radio"]:checked+label{background:var(--accent-primary);color:#fff;}
        .upload-panel{display:none;}
        .upload-panel.active{display:block;}
        .file-input-wrapper{position:relative;background:var(--bg-primary);border:2px dashed var(--border-color);border-radius:var(--radius);padding:2rem;text-align:center;cursor:pointer;transition:var(--transition);}
        .file-input-wrapper:hover{border-color:var(--accent-primary);}
        .file-input-wrapper input[type="file"]{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;}
        .file-input-wrapper .file-input-label{color:var(--text-secondary);}
        .file-input-wrapper .file-input-label i{font-size:2rem;display:block;margin-bottom:0.5rem;}
        .api-key-group{position:relative;display:flex;align-items:center;}
        .api-key-group .form-control{padding-right:6rem;}
        .api-key-actions{position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);display:flex;gap:0.5rem;}
        .api-key-actions button{background:none;border:1px solid var(--border-color);color:var(--text-secondary);cursor:pointer;border-radius:0.375rem;width:32px;height:32px;display:flex;align-items:center;justify-content:center;transition:var(--transition);}
        .api-key-actions button:hover{color:var(--text-primary);border-color:var(--accent-primary);}
        .form-group small{display:block;margin-top:0.5rem;font-size:0.8rem;color:var(--text-secondary);}
    </style>
</head>
<body>
    <header class="site-header"><a href="/">CSC</a></header>
    <div class="alert-container">
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo htmlspecialchars($message['type']); ?>"><?php echo htmlspecialchars($message['text']); ?></div>
        <?php endif; ?>
    </div>
    <div class="container">
        <aside class="profile-sidebar">
            <div class="profile-card">
                <?php if ($is_owner): ?>
                    <button class="settings-trigger" id="open-settings-modal" title="Profile Settings"><i class="fas fa-cog"></i></button>
                <?php endif; ?>
                <div class="profile-banner">
                    <img id="banner-preview" src="db/thumbnails/<?php echo htmlspecialchars($user_data['thumbnail'] ?? 'default_banner.png'); ?>?t=<?php echo time(); ?>" alt="Banner">
                </div>
                <div class="profile-info">
                    <img id="avatar-preview" src="db/profile/<?php echo htmlspecialchars($user_data['profile_picture'] ?? 'default_avatar.png'); ?>?t=<?php echo time(); ?>" alt="Avatar" class="profile-avatar">
                    <h1 class="profile-name">
                        <?php echo htmlspecialchars(!empty($user_data['display_name']) ? $user_data['display_name'] : $user_data['username']); ?>
                        <?php if ($user_data['is_verified']): ?>
                           <svg class="verified-badge" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#3B82F6;"/><stop offset="100%" style="stop-color:#1D4ED8;"/></linearGradient></defs><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z" fill="url(#g)"/><path d="m8.5 12.5 2.5 3 5-6" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php endif; ?>
                    </h1>
                    <p class="profile-username">@<?php echo htmlspecialchars($user_data['username']); ?></p>
                    <p class="profile-bio" id="bio-preview"><?php echo !empty($user_data['bio']) ? nl2br(htmlspecialchars($user_data['bio'])) : 'No bio yet.'; ?></p>
                </div>
                <?php if (!empty(array_filter($social_links))): ?>
                <div class="social-links">
                    <?php foreach ($social_links as $key => $value): if (!empty($value)):
                        $url = $value;
                        if ($key === 'github' || $key === 'instagram') { $url = "https://{$key}.com/" . htmlspecialchars($value); }
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo ucfirst($key); ?>"><i class="<?php echo $social_icon_classes[$key]; ?>"></i></a>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </aside>
        <main class="snippets-section">
            <h2 class="section-title">Shared Snippets (<?php echo count($snippets); ?>)</h2>
            <div class="snippets-grid">
                <?php if (count($snippets) > 0): foreach ($snippets as $snippet): ?>
                    <div class="snippet-card" onmousemove="this.style.setProperty('--mouse-x', `${event.clientX - this.getBoundingClientRect().left}px`); this.style.setProperty('--mouse-y', `${event.clientY - this.getBoundingClientRect().top}px`);">
                        <a href="view?id=<?php echo htmlspecialchars($snippet['share_id']); ?>" class="card-content">
                            <h3><?php echo htmlspecialchars($snippet['title']); ?></h3>
                            <div class="snippet-meta"><time>Shared on <?php echo date("M d, Y, g:i A", strtotime($snippet['created_at'])); ?></time></div>
                            <div class="snippet-preview"><pre><code class="language-<?php echo htmlspecialchars($snippet['language']); ?>"><?php echo htmlspecialchars(substr($snippet['code_content'], 0, 200)) . (strlen($snippet['code_content']) > 200 ? '...' : ''); ?></code></pre></div>
                            <div class="card-footer">
                                <span class="lang-tag"><span class="lang-dot"></span><?php echo htmlspecialchars($snippet['language']); ?></span>
                                <div class="card-stats"><i class="fas fa-eye"></i><span><?php echo number_format($snippet['view_count']); ?></span></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; else: ?>
                    <p><?php echo htmlspecialchars($user_data['username']); ?> hasn't shared any snippets yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php if ($is_owner): ?>
    <div class="modal-overlay" id="settings-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Profile Settings</h2>
                <button class="modal-close" id="close-settings-modal">&times;</button>
            </div>
            <form action="profile?user=<?php echo urlencode($user_data['username']); ?>" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="display-name-input">Display Name</label>
                        <input type="text" id="display-name-input" name="display_name" class="form-control" placeholder="Your public name" value="<?php echo htmlspecialchars($user_data['display_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio-input">Bio</label>
                        <textarea id="bio-input" name="bio" class="form-control" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="upload-section" style="margin-top:0; border-top:none;">
                        <h3>Avatar</h3>
                        <div class="upload-choice">
                            <input type="radio" name="avatar_source" id="avatar_upload_choice" value="file" checked> <label for="avatar_upload_choice">Upload</label>
                            <input type="radio" name="avatar_source" id="avatar_url_choice" value="url"> <label for="avatar_url_choice">URL</label>
                        </div>
                        <div id="avatar_panel_file" class="upload-panel active">
                            <div class="file-input-wrapper">
                                <input type="file" name="avatar_file" id="avatar-file-input" accept="image/*">
                                <label for="avatar-file-input" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span id="avatar-filename">Click to upload or drag & drop</span>
                                </label>
                            </div>
                        </div>
                        <div id="avatar_panel_url" class="upload-panel"><input type="url" name="avatar_url" id="avatar-url-input" class="form-control" placeholder="https://example.com/image.jpg"></div>
                    </div>
                    <div class="upload-section">
                        <h3>Banner</h3>
                         <div class="upload-choice">
                            <input type="radio" name="banner_source" id="banner_upload_choice" value="file" checked> <label for="banner_upload_choice">Upload</label>
                            <input type="radio" name="banner_source" id="banner_url_choice" value="url"> <label for="banner_url_choice">URL</label>
                        </div>
                        <div id="banner_panel_file" class="upload-panel active">
                           <div class="file-input-wrapper">
                                <input type="file" name="banner_file" id="banner-file-input" accept="image/*">
                                <label for="banner-file-input" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span id="banner-filename">Click to upload or drag & drop</span>
                                </label>
                            </div>
                        </div>
                        <div id="banner_panel_url" class="upload-panel"><input type="url" name="banner_url" id="banner-url-input" class="form-control" placeholder="https://example.com/banner.jpg"></div>
                    </div>
                    <hr>
                    <h3 style="margin-bottom: 1rem;">Social Links</h3>
                    <div class="input-group"><span class="input-group-icon"><i class="fab fa-github"></i></span><input type="text" name="github" class="form-control" placeholder="GitHub Username" value="<?php echo htmlspecialchars($social_links['github'] ?? ''); ?>"></div>
                    <div class="input-group"><span class="input-group-icon"><i class="fab fa-instagram"></i></span><input type="text" name="instagram" class="form-control" placeholder="Instagram Username" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>"></div>
                    <div class="input-group"><span class="input-group-icon"><i class="fab fa-youtube"></i></span><input type="url" name="youtube" class="form-control" placeholder="YouTube Channel URL" value="<?php echo htmlspecialchars($social_links['youtube'] ?? ''); ?>"></div>
                    <div class="input-group"><span class="input-group-icon"><i class="fas fa-link"></i></span><input type="url" name="website1" class="form-control" placeholder="Website / Portfolio URL" value="<?php echo htmlspecialchars($social_links['website1'] ?? ''); ?>"></div>
                    <div class="input-group"><span class="input-group-icon"><i class="fas fa-link"></i></span><input type="url" name="website2" class="form-control" placeholder="Another Website URL (Optional)" value="<?php echo htmlspecialchars($social_links['website2'] ?? ''); ?>"></div>
                    <hr>
                    <div class="form-group">
                        <h3>API Key</h3>
                        <div class="api-key-group">
                            <?php if ($user_data['is_verified']): ?>
                                <input type="password" id="api-key-input" name="api_key" class="form-control" value="<?php echo htmlspecialchars($user_data['api_key']); ?>">
                            <?php else: ?>
                                <input type="password" id="api-key-input" class="form-control" value="<?php echo htmlspecialchars($user_data['api_key']); ?>" readonly>
                            <?php endif; ?>
                            <div class="api-key-actions">
                                <button type="button" id="toggle-api-key" title="Show/Hide Key"><i class="fas fa-eye"></i></button>
                                <button type="button" id="copy-api-key" title="Copy Key"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <small>
                            <?php if ($user_data['is_verified']): ?>
                                You can set a custom API Key. Only use letters, numbers, <code>_</code>, and <code>-</code>.
                            <?php else: ?>
                                Verify your account to be able to set a custom API Key.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="action" value="regenerate_api_key" class="btn btn-secondary">Regenerate Key</button>
                    <button type="submit" name="action" value="save_changes" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',()=>{const openBtn=document.getElementById('open-settings-modal');const closeBtn=document.getElementById('close-settings-modal');const modal=document.getElementById('settings-modal');if(openBtn)openBtn.addEventListener('click',()=>modal.classList.add('active'));if(closeBtn)closeBtn.addEventListener('click',()=>modal.classList.remove('active'));if(modal)modal.addEventListener('click',(e)=>{if(e.target===modal)modal.classList.remove('active');});function setupUploadTabs(type){document.querySelectorAll(`input[name="${type}_source"]`).forEach(radio=>{radio.addEventListener('change',(e)=>{document.getElementById(`${type}_panel_file`).classList.toggle('active',e.target.value==='file');document.getElementById(`${type}_panel_url`).classList.toggle('active',e.target.value==='url');});});}function setupImagePreview(type){const fileInput=document.getElementById(`${type}-file-input`);const urlInput=document.getElementById(`${type}-url-input`);const previewImg=document.getElementById(`${type}-preview`);const fileNameSpan=document.getElementById(`${type}-filename`);fileInput.addEventListener('change',()=>{const file=fileInput.files[0];if(file){previewImg.src=URL.createObjectURL(file);if(fileNameSpan)fileNameSpan.textContent=file.name;}});urlInput.addEventListener('input',()=>{if(urlInput.value.trim()&&urlInput.validity.valid){previewImg.src=urlInput.value;}});}setupUploadTabs('avatar');setupImagePreview('avatar');setupUploadTabs('banner');setupImagePreview('banner');const apiKeyInput=document.getElementById('api-key-input');const toggleBtn=document.getElementById('toggle-api-key');const copyBtn=document.getElementById('copy-api-key');if(toggleBtn&&apiKeyInput){toggleBtn.addEventListener('click',()=>{const icon=toggleBtn.querySelector('i');if(apiKeyInput.type==='password'){apiKeyInput.type='text';icon.classList.remove('fa-eye');icon.classList.add('fa-eye-slash');}else{apiKeyInput.type='password';icon.classList.remove('fa-eye-slash');icon.classList.add('fa-eye');}});}if(copyBtn&&apiKeyInput){copyBtn.addEventListener('click',()=>{const isReadOnly=apiKeyInput.readOnly;if(isReadOnly)apiKeyInput.readOnly=false;apiKeyInput.select();apiKeyInput.setSelectionRange(0,99999);navigator.clipboard.writeText(apiKeyInput.value);if(isReadOnly)apiKeyInput.readOnly=true;apiKeyInput.blur();copyBtn.innerHTML='<i class="fas fa-check"></i>';setTimeout(()=>{copyBtn.innerHTML='<i class="fas fa-copy"></i>';},1500);});}const alertBox=document.querySelector('.alert-container .alert');if(alertBox){setTimeout(()=>{alertBox.classList.add('fade-out');setTimeout(()=>{alertBox.style.display='none';},500);},5000);}});
    </script>
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
</body>
</html>