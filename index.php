<?php
require_once "includes/db.php";
require_once "includes/functions.php";

$auth_err = "";
$form_action = "register";

function getLanguageIconClass($language) {
    $language = strtolower($language);
    $map = ['csharp'=>'csharp', 'cpp'=>'cplusplus', 'html'=>'html5', 'css'=>'css3', 'dockerfile'=>'docker', 'sql'=>'mysql', 'vue'=>'vuejs', 'angularjs'=>'angularjs'];
    $iconName = $map[$language] ?? $language;
    return "devicon-{$iconName}-plain";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    if ($action === 'register') {
        $form_action = "register";
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if (empty($username)) $auth_err = "Username cannot be empty.";
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $auth_err = "Username can only contain letters, numbers, and underscores.";
        elseif (empty($password)) $auth_err = "Password cannot be empty.";
        elseif (strlen($password) < 6) $auth_err = "Password must be at least 6 characters long.";
        else {
            $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) $auth_err = "This username is already taken.";
            $stmt_check->close();
        }
        if (empty($auth_err)) {
            $stmt_insert = $mysqli->prepare("INSERT INTO users (uuid, username, password, api_key) VALUES (UUID(), ?, ?, ?)");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $apiKey = generateApiKey();
            $stmt_insert->bind_param("sss", $username, $hashed_password, $apiKey);
            if ($stmt_insert->execute()) {
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $stmt_insert->insert_id;
                $_SESSION["username"] = $username;
                header("location: dashboard.php");
                exit;
            } else {
                $auth_err = "Something went wrong. Please try again.";
            }
            $stmt_insert->close();
        }
    } elseif ($action === 'login') {
        $form_action = "login";
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if (empty($username) || empty($password)) {
            $auth_err = "Please enter username and password.";
        } else {
            $sql = "SELECT id, username, password, profile_picture FROM users WHERE username = ?";
            if($stmt = $mysqli->prepare($sql)){
                $stmt->bind_param("s", $username);
                if($stmt->execute()){
                    $stmt->store_result();
                    if($stmt->num_rows == 1){
                        $stmt->bind_result($id, $db_username, $hashed_password, $profile_picture);
                        if($stmt->fetch()){
                            if(password_verify($password, $hashed_password)){
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["username"] = $db_username;
                                $_SESSION["profile_picture"] = $profile_picture;
                                header("location: dashboard.php");
                            } else{
                                $auth_err = "Invalid username or password.";
                            }
                        }
                    } else{
                        $auth_err = "Invalid username or password.";
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    }
}

$snippets_per_page = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $snippets_per_page;
$total_snippets_result = $mysqli->query("SELECT COUNT(*) FROM codes");
$total_snippets = $total_snippets_result->fetch_row()[0];
$total_pages = ceil($total_snippets / $snippets_per_page);
$snippets = [];
$sql_snippets = "SELECT c.share_id, c.title, c.code_content, c.language, c.views, u.username, u.profile_picture, u.is_verified FROM codes c JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
if ($stmt = $mysqli->prepare($sql_snippets)) {
    $stmt->bind_param("ii", $snippets_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $snippets[] = $row;
    $stmt->close();
}

function generatePagination($total_pages, $current_page, $adjacents = 1) {
    if ($total_pages <= 1) return;
    $prev = $current_page - 1;
    $next = $current_page + 1;

    if ($current_page > 1) { echo '<a href="/?page=' . $prev . '" class="pagination-arrow">&laquo;</a>'; }
    else { echo '<span class="pagination-arrow disabled">&laquo;</span>'; }

    if ($total_pages <= (5 + $adjacents * 2)) {
        for ($i = 1; $i <= $total_pages; $i++) { echo '<a href="/?page=' . $i . '" class="' . ($i == $current_page ? 'active' : '') . '">' . $i . '</a>'; }
    } else {
        echo '<a href="/?page=1" class="' . (1 == $current_page ? 'active' : '') . '">1</a>';
        if ($current_page > (2 + $adjacents)) { echo '<span class="pagination-dots">...</span>'; }
        $start = max(2, $current_page - $adjacents);
        $end = min($total_pages - 1, $current_page + $adjacents);
        for ($i = $start; $i <= $end; $i++) { echo '<a href="/?page=' . $i . '" class="' . ($i == $current_page ? 'active' : '') . '">' . $i . '</a>'; }
        if ($current_page < ($total_pages - 1 - $adjacents)) { echo '<span class="pagination-dots">...</span>'; }
        echo '<a href="/?page=' . $total_pages . '" class="' . ($total_pages == $current_page ? 'active' : '') . '">' . $total_pages . '</a>';
    }

    if ($current_page < $total_pages) { echo '<a href="/?page=' . $next . '" class="pagination-arrow">&raquo;</a>'; }
    else { echo '<span class="pagination-arrow disabled">&raquo;</span>'; }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codify - Code Sharing Platform</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@v2.15.1/devicon.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Inter', sans-serif;
            --font-serif: 'Sora', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }
        .theme-glassmorphism { --bg-color: #0d1117; --header-bg: rgba(22, 27, 34, 0.7); --card-bg: rgba(34, 40, 49, 0.6); --modal-bg: rgba(22, 27, 34, 0.85); --border-color: rgba(139, 148, 158, 0.3); --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow: 0 0 15px rgba(38, 129, 255, 0.6); --accent-color: #2681ff; --hover-bg: rgba(56, 139, 253, 0.1); --danger:#f04242; --danger-bg:rgba(240,66,66,0.1); }
        .theme-hacker { --bg-color: #000; --header-bg: rgba(0,0,0,0.7); --card-bg: rgba(10, 25, 47, 0.2); --modal-bg: #0a192f; --border-color: rgba(0, 255, 128, 0.3); --text-primary: #00ff80; --text-secondary: #00a354; --accent-color: #a855f7; --accent-glow: 0 0 12px rgba(168, 85, 247, 0.8); --danger:#f04242; --danger-bg:rgba(240,66,66,0.1); }
        .theme-hacker { font-family: var(--font-mono); }
        body { font-family: var(--font-sans); background-color: var(--bg-color); color: var(--text-primary); transition: background-color .3s ease; }
        [x-cloak] { display: none !important; }

        .ascii-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; font-family: var(--font-mono); font-size: 14px; line-height: 1; white-space: pre; overflow: hidden; z-index: -10; color: var(--accent-color); opacity: 0.1; user-select: none; }
        .main-header { position: sticky; top: 0; z-index: 50; background-color: var(--header-bg); border-bottom: 1px solid var(--border-color); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .snippet-card { background-color: var(--bg-card); border: 1px solid var(--border-color); }
        .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .5rem;color:var(--text-secondary);border:1px solid transparent;border-radius:.5rem;transition:all .2s ease;font-size:.9rem}
        .pagination a:hover{color:var(--text-primary);background-color:var(--card-bg)}
        .pagination a.active{color:var(--accent-color);font-weight:600;background-color:var(--accent-glow);border-color:var(--accent-color)}
        .pagination-arrow.disabled{color:#555;cursor:not-allowed}
        .pagination-dots{color:var(--text-secondary);cursor:default}
    </style>
</head>
<body x-data='{
        theme: localStorage.getItem("theme") || "theme-hacker",
        setTheme(t) { this.theme = t; localStorage.setItem("theme", t); },
        isAuthModalOpen: <?php echo !empty($auth_err) ? 'true' : 'false'; ?>,
        activeForm: "<?php echo $form_action; ?>"
    }'
    :class="theme"
>
    <div x-show="theme === 'theme-hacker'" x-cloak class="ascii-bg" x-data="{ asciiArt: '' }" x-init="
        const chars = '01';
        const generateLine = () => Array.from({ length: Math.ceil(window.innerWidth / 8) }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        const generateArt = () => Array.from({ length: Math.ceil(window.innerHeight / 14) }, generateLine).join('\n');
        asciiArt = generateArt();
        setInterval(() => {
            const lines = asciiArt.split('\n');
            lines.shift();
            lines.push(generateLine());
            asciiArt = lines.join('\n');
        }, 100);
    "></div>

    <header class="main-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="index.php" class="text-2xl font-bold" :style="{color: 'var(--text-primary)'}">Codify<span :style="{color: 'var(--accent-color)'}">.</span></a>
                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="px-4 py-2 rounded-md text-sm font-medium" :style="{ backgroundColor: 'var(--hover-bg)', color: 'var(--text-primary)' }">Dashboard</a>
                    <?php else: ?>
                        <button @click="isAuthModalOpen = true; activeForm = 'login'" class="px-4 py-2 rounded-md text-sm font-medium" :style="{ color: 'var(--text-primary)' }">Login</button>
                        <button @click="isAuthModalOpen = true; activeForm = 'register'" class="px-4 py-2 rounded-md text-sm font-medium text-white" :style="{ backgroundColor: 'var(--accent-color)', boxShadow: 'var(--accent-glow)' }">Get Started</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        <section class="text-center py-16">
            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tighter" :style="{color: 'var(--text-primary)'}">
                Your Code Deserves <br>
                <span :style="{color: 'var(--accent-color)'}">Better.</span>
            </h1>
            <p class="mt-6 max-w-2xl mx-auto text-lg" :style="{color: 'var(--text-secondary)'}">
                Built for developers who lead, Codify delivers a smarter way to share, scale, and stand out.
            </p>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
            <?php foreach ($snippets as $snippet): ?>
                <a href="view.php?id=<?php echo htmlspecialchars($snippet['share_id']); ?>" class="snippet-card block p-6 rounded-lg hover:-translate-y-1 transition-transform">
                    <h3 class="font-bold text-lg mb-2 truncate" :style="{color: 'var(--text-primary)'}"><?php echo htmlspecialchars($snippet['title']); ?></h3>
                    <div class="flex items-center text-sm mb-4" :style="{color: 'var(--text-secondary)'}">
                        <i class="<?php echo getLanguageIconClass($snippet['language']); ?> mr-2 text-lg"></i>
                        <span><?php echo ucfirst($snippet['language']); ?></span>
                    </div>
                    <div class="flex items-center justify-between pt-4 mt-auto border-t" :style="{borderColor: 'var(--border-color)'}">
                        <div class="flex items-center gap-3">
                            <img src="db/profile/<?php echo htmlspecialchars($snippet['profile_picture'] ?? 'default.png'); ?>" alt="Avatar" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium" :style="{color: 'var(--text-primary)'}"><?php echo htmlspecialchars($snippet['username']); ?></span>
                        </div>
                        <span class="text-sm" :style="{color: 'var(--text-secondary)'}"><?php echo number_format($snippet['views']); ?> views</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>
        
        <?php if ($total_pages > 1): ?>
        <nav class="pagination flex justify-center py-12">
            <?php generatePagination($total_pages, $page); ?>
        </nav>
        <?php endif; ?>
    </main>
    
    <footer class="text-center py-8 mt-16 border-t" :style="{borderColor: 'var(--border-color)', color: 'var(--text-secondary)'}">
        <p>&copy; <?php echo date("Y"); ?> Codify. Crafted by <a href="https://github.com/dgamegt" target="_blank" rel="noopener noreferrer" :style="{color: 'var(--text-primary)'}">DGXO</a>.</p>
    </footer>

    <div x-show="isAuthModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(8px);">
        <div @click.away="isAuthModalOpen = false" class="w-full max-w-sm rounded-lg overflow-hidden" :style="{ backgroundColor: 'var(--modal-bg)', border: '1px solid var(--border-color)' }">
            <div class="flex border-b" :style="{borderColor: 'var(--border-color)'}">
                <button @click="activeForm = 'register'" class="flex-1 p-4 font-medium transition-colors" :style="activeForm === 'register' ? {backgroundColor: 'var(--accent-color)', color: '#fff'} : {color: 'var(--text-secondary)'}">Register</button>
                <button @click="activeForm = 'login'" class="flex-1 p-4 font-medium transition-colors" :style="activeForm === 'login' ? {backgroundColor: 'var(--accent-color)', color: '#fff'} : {color: 'var(--text-secondary)'}">Login</button>
            </div>
            <div class="p-6">
                <?php if (!empty($auth_err)): ?>
                    <div class="p-3 mb-4 rounded-md text-sm" :style="{ backgroundColor: 'var(--danger-bg)', color: 'var(--danger)', border: '1px solid var(--danger)' }">
                        <?php echo $auth_err; ?>
                    </div>
                <?php endif; ?>
                <div x-show="activeForm === 'register'">
                    <form action="index.php" method="post" class="space-y-4">
                        <input type="hidden" name="action" value="register">
                        <input type="text" name="username" class="w-full p-3 rounded-md bg-transparent border" :style="{borderColor: 'var(--border-color)'}" placeholder="Username" required>
                        <input type="password" name="password" class="w-full p-3 rounded-md bg-transparent border" :style="{borderColor: 'var(--border-color)'}" placeholder="Password" required>
                        <button type="submit" class="w-full p-3 rounded-md text-white font-semibold" :style="{backgroundColor: 'var(--accent-color)'}">Create Account</button>
                    </form>
                </div>
                <div x-show="activeForm === 'login'">
                    <form action="index.php" method="post" class="space-y-4">
                        <input type="hidden" name="action" value="login">
                        <input type="text" name="username" class="w-full p-3 rounded-md bg-transparent border" :style="{borderColor: 'var(--border-color)'}" placeholder="Username" required>
                        <input type="password" name="password" class="w-full p-3 rounded-md bg-transparent border" :style="{borderColor: 'var(--border-color)'}" placeholder="Password" required>
                        <button type="submit" class="w-full p-3 rounded-md text-white font-semibold" :style="{backgroundColor: 'var(--accent-color)'}">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            hljs.highlightAll();
        });
    </script>
</body>
</html>