<?php
session_start();
require_once "includes/db.php";

$snippet = null;
$share_id = $_GET['id'] ?? '';

if (empty($share_id)) {
    // Biarkan halaman menampilkan "Snippet Not Found" di bawah
}

if (isset($_GET['raw']) && $_GET['raw'] === 'true') {
    if (!empty($share_id)) {
        $sql_raw = "SELECT code_content FROM codes WHERE share_id = ? LIMIT 1";
        if ($stmt_raw = $mysqli->prepare($sql_raw)) {
            $stmt_raw->bind_param("s", $share_id);
            $stmt_raw->execute();
            $result_raw = $stmt_raw->get_result()->fetch_assoc();
            $stmt_raw->close();

            if ($result_raw) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $result_raw['code_content'];
                exit;
            }
        }
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: Snippet not found.';
    exit;
}

if (!empty($share_id)) {
    $sql = "SELECT c.id, c.title, c.code_content, c.language, c.created_at, c.views, u.username, u.profile_picture, u.is_verified
            FROM codes c
            JOIN users u ON c.user_id = u.id
            WHERE c.share_id = ?
            LIMIT 1";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $share_id);
        $stmt->execute();
        $snippet = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($snippet) {
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $code_id = $snippet['id'];

        $check_view_sql = "SELECT id FROM snippet_views WHERE code_id = ? AND ip_address = ?";
        if ($check_stmt = $mysqli->prepare($check_view_sql)) {
            $check_stmt->bind_param("is", $code_id, $user_ip);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows === 0) {
                $mysqli->begin_transaction();
                try {
                    $update_views_sql = "UPDATE codes SET views = views + 1 WHERE id = ?";
                    $update_stmt = $mysqli->prepare($update_views_sql);
                    $update_stmt->bind_param("i", $code_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $insert_view_sql = "INSERT INTO snippet_views (code_id, ip_address) VALUES (?, ?)";
                    $insert_stmt = $mysqli->prepare($insert_view_sql);
                    $insert_stmt->bind_param("is", $code_id, $user_ip);
                    $insert_stmt->execute();
                    $insert_stmt->close();

                    $mysqli->commit();
                    $snippet['views']++;
                } catch (mysqli_sql_exception $exception) {
                    $mysqli->rollback();
                }
            }
            $check_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $snippet ? htmlspecialchars($snippet['title']) : 'Snippet Not Found'; ?> - CSC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --radius: 0.75rem;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --bg-primary: #0A0A0A;
            --bg-secondary: #171717;
            --border-color: #27272a;
            --text-primary: #f4f4f5;
            --text-secondary: #a1a1aa;
            --accent-primary: #3882F6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-sans);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: radial-gradient(circle at 25% 25%, rgba(56, 130, 246, 0.15), rgba(56, 130, 246, 0) 40%),
                              radial-gradient(circle at 75% 75%, rgba(79, 70, 229, 0.15), rgba(79, 70, 229, 0) 40%);
        }
        .page-header {
            padding: 1.5rem 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
        }
        .logo span {
            color: var(--accent-primary);
        }
        .container {
            max-width: 1000px;
            margin: 1rem auto;
            padding: 0 2rem 4rem;
        }
        .snippet-container, .error-container {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .snippet-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }
        .meta-info {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition);
        }
        .user-info:hover { opacity: 0.8; }
        .user-info img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }
        .user-info span { font-weight: 500; }
        .verified-badge {
            width: 16px; height: 16px;
            color: var(--accent-primary);
            vertical-align: middle;
            margin-left: 4px;
        }
        .stats-info { display: flex; align-items: center; gap: 0.5rem; }
        .stats-info svg { width: 16px; height: 16px; }
        .header-actions { display: flex; gap: 0.5rem; }
        .action-btn {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .action-btn:hover { background-color: #2a2a2a; color: var(--text-primary); border-color: #3f3f46; }
        .action-btn svg { width: 16px; height: 16px; }
        .code-view { position: relative; }
        .code-view pre { margin: 0; }
        .code-view code.hljs {
            font-family: var(--font-mono);
            padding: 2rem !important;
            padding-top: 3.5rem !important;
            background: none;
            font-size: 0.9rem;
        }
        #copy-code-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        .error-container {
            padding: 4rem 2rem;
            text-align: center;
        }
        .error-container h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .error-container p {
            font-size: 1rem;
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="/" class="logo">CSC<span>.</span></a>
    </header>

    <div class="container">
        <?php if ($snippet): ?>
            <div class="snippet-container">
                <div class="snippet-header">
                    <div class="header-info">
                        <h1><?php echo htmlspecialchars($snippet['title']); ?></h1>
                        <div class="meta-info">
                            <a href="profile.php?user=<?php echo urlencode($snippet['username']); ?>" class="user-info">
                                <img src="db/profile/<?php echo htmlspecialchars($snippet['profile_picture'] ?? 'default.png'); ?>" alt="User Avatar">
                                <span>
                                    <?php echo htmlspecialchars($snippet['username']); ?>
                                    <?php if ($snippet['is_verified']): ?>
                                        <svg class="verified-badge" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.03 15.44l-3.53-3.54c-.39-.39-.39-1.02 0-1.41l.71-.71c.39-.39 1.02-.39 1.41 0l2.12 2.12 4.95-4.95c.39-.39 1.02-.39 1.41 0l.71.71c.39.39.39 1.02 0 1.41l-6.36 6.36c-.39.39-1.03.39-1.42 0z"></path></svg>
                                    <?php endif; ?>
                                </span>
                            </a>
                            <span class="stats-info">•</span>
                            <div class="stats-info">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"></path></svg>
                                <span><?php echo number_format($snippet['views']); ?> views</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="action-btn" id="copy-url-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            <span id="copy-url-text">Copy URL</span>
                        </button>
                        <a href="?id=<?php echo $share_id; ?>&raw=true" target="_blank" class="action-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                            <span>Raw</span>
                        </a>
                    </div>
                </div>
                <div class="code-view">
                    <pre><code id="code-block" class="language-<?php echo htmlspecialchars($snippet['language']); ?>"><?php echo htmlspecialchars($snippet['code_content']); ?></code></pre>
                    <button class="action-btn" id="copy-code-btn" title="Copy code">
                        <span id="copy-icon-container">
                             <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" id="copy-icon-default"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="error-container">
                <h1>404 - Snippet Not Found</h1>
                <p>The code you are looking for does not exist or may have been deleted.</p>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof hljs !== 'undefined') {
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        }

        const copyUrlBtn = document.getElementById('copy-url-btn');
        const copyCodeBtn = document.getElementById('copy-code-btn');
        
        if (copyUrlBtn) {
            const copyUrlText = document.getElementById('copy-url-text');
            copyUrlBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(window.location.href.split('&raw=')[0]).then(() => {
                    copyUrlText.textContent = 'Copied!';
                    copyUrlBtn.style.color = '#34d399';
                    setTimeout(() => {
                        copyUrlText.textContent = 'Copy URL';
                        copyUrlBtn.style.color = '';
                    }, 2000);
                });
            });
        }
        
        if (copyCodeBtn) {
            const codeBlock = document.getElementById('code-block');
            const iconContainer = document.getElementById('copy-icon-container');
            const defaultIcon = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>`;
            const successIcon = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #34d399;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
            
            copyCodeBtn.addEventListener('click', () => {
                if (codeBlock) {
                    navigator.clipboard.writeText(codeBlock.innerText).then(() => {
                        iconContainer.innerHTML = successIcon;
                        copyCodeBtn.title = 'Copied!';
                        setTimeout(() => {
                            iconContainer.innerHTML = defaultIcon;
                            copyCodeBtn.title = 'Copy code';
                        }, 2000);
                    });
                }
            });
        }
    });
    </script>
</body>
</html>