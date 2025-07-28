<?php
require_once "includes/db.php";
require_once "includes/functions.php";

// session_start();

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
            $stmt_insert = $mysqli->prepare("INSERT INTO users (username, password, api_key) VALUES (?, ?, ?)");
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
        }
    } elseif ($action === 'login') {
        $form_action = "login";
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if (empty($username) || empty($password)) {
            $auth_err = "Please enter username and password.";
        } else {
            $stmt_login = $mysqli->prepare("SELECT id, username, password, profile_picture FROM users WHERE username = ?");
            $stmt_login->bind_param("s", $username);
            $stmt_login->execute();
            $stmt_login->store_result();
            if ($stmt_login->num_rows == 1) {
                $stmt_login->bind_result($id, $db_username, $hashed_password, $profile_picture);
                if ($stmt_login->fetch() && password_verify($password, $hashed_password)) {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["username"] = $db_username;
                    $_SESSION["profile_picture"] = $profile_picture;
                    header("location: dashboard.php");
                    exit;
                }
            }
            $auth_err = "Invalid username or password.";
            $stmt_login->close();
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

    if ($current_page > 1) {
        echo '<a href="/?page=' . $prev . '" class="pagination-arrow">&laquo;</a>';
    } else {
        echo '<span class="pagination-arrow disabled">&laquo;</span>';
    }

    if ($total_pages <= (5 + $adjacents * 2)) {
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<a href="/?page=' . $i . '" class="' . ($i == $current_page ? 'active' : '') . '">' . $i . '</a>';
        }
    } else {
        echo '<a href="/?page=1" class="' . (1 == $current_page ? 'active' : '') . '">1</a>';
        if ($current_page > (2 + $adjacents)) {
            echo '<span class="pagination-dots">...</span>';
        }
        $start = max(2, $current_page - $adjacents);
        $end = min($total_pages - 1, $current_page + $adjacents);
        for ($i = $start; $i <= $end; $i++) {
            echo '<a href="/?page=' . $i . '" class="' . ($i == $current_page ? 'active' : '') . '">' . $i . '</a>';
        }
        if ($current_page < ($total_pages - 1 - $adjacents)) {
            echo '<span class="pagination-dots">...</span>';
        }
        echo '<a href="/?page=' . $total_pages . '" class="' . ($total_pages == $current_page ? 'active' : '') . '">' . $total_pages . '</a>';
    }

    if ($current_page < $total_pages) {
        echo '<a href="/?page=' . $next . '" class="pagination-arrow">&raquo;</a>';
    } else {
        echo '<span class="pagination-arrow disabled">&raquo;</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codify - Code Sharing Platform</title>
    <meta name="description" content="An open-source platform to share, discover, and securely store code snippets in the cloud.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@v2.15.1/devicon.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#121212;--bg-card:#1a1a1a;--text-primary:#e5e5e5;--text-secondary:#888;--border:#2a2a2a;--accent:#0070f3;--accent-glow:rgba(0,112,243,0.2);--danger:#f04242;--danger-bg:rgba(240,66,66,0.1)}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{font-family:'Inter',sans-serif;background-color:var(--bg);color:var(--text-primary);overflow-x:hidden}
        .container{width:100%;max-width:1200px;margin:0 auto;padding:0 1.5rem}
        a{color:inherit;text-decoration:none;transition:color .2s ease}
        .page-header{padding:1.25rem 0;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);backdrop-filter:blur(8px);position:sticky;top:0;z-index:10;background:rgba(18,18,18,0.8)}
        .logo{font-size:1.5rem;font-weight:700}.logo span{color:var(--accent)}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:0.5rem 1rem;border:1px solid var(--border);background-color:var(--bg-card);border-radius:0.5rem;font-weight:500;cursor:pointer;transition:all .2s ease}
        .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn:hover{border-color:#444}.btn-primary:hover{opacity:.9}
        .hero{text-align:center;padding:6rem 0;animation:fadeInUp .6s ease-out forwards}
        .hero h1{font-size:3.5rem;font-weight:800;letter-spacing:-.05em;line-height:1.1;margin-bottom:1.5rem}
        .hero p{font-size:1.125rem;color:var(--text-secondary);max-width:600px;margin:0 auto;line-height:1.6}
        
        .snippets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.5rem}
        
        .snippet-card{background-color:var(--bg-card);border-radius:.75rem;transition:all .3s ease;display:flex;flex-direction:column;cursor:pointer;opacity:0;animation:fadeInUp .5s ease-out forwards;box-shadow:0 1px 3px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.1);overflow:hidden}
        .snippet-card:hover{transform:translateY(-5px);box-shadow:0 8px 30px var(--accent-glow)}
        .card-content{padding:1.25rem}
        .card-title{font-size:1.1rem;font-weight:600;margin-bottom:.75rem}
        .card-title a:hover{color:var(--accent)}
        .card-preview{height:180px;background-color:#282c34;position:relative;padding:1rem;border-radius:.5rem;overflow:hidden}
        .card-preview::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50px;background:linear-gradient(to top,#282c34,transparent);pointer-events:none}
        .card-preview pre{margin:0;white-space:pre-wrap;word-break:break-all}.card-preview code{font-family:monospace;font-size:.85rem;padding:0!important;line-height:1.5}
        .card-footer{display:flex;justify-content:space-between;align-items:center;padding-top:1.25rem;border-top:1px solid var(--border)}
        .card-user{display:flex;align-items:center;gap:.6rem;min-width:0}.card-user img{width:28px;height:28px;border-radius:50%}
        .user-info{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:.3rem}.verified-badge{width:1rem;height:1rem;color:var(--accent);flex-shrink:0}
        .card-meta{display:flex;align-items:center;gap:1rem;color:var(--text-secondary);font-size:.875rem}
        .meta-item{display:flex;align-items:center;gap:.4rem}.meta-item i{font-size:1.1rem;color:var(--text-primary)}
        .meta-item svg{width:1rem;height:1rem;margin-right:.2rem}
        
        .pagination{display:flex;justify-content:center;align-items:center;gap:.5rem;margin:4rem 0;flex-wrap:wrap}
        .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .5rem;color:var(--text-secondary);border:1px solid transparent;border-radius:.5rem;transition:all .2s ease;font-size:.9rem}
        .pagination a:hover{color:var(--text-primary);background-color:var(--bg-card)}
        .pagination a.active{color:var(--accent);font-weight:600;background-color:var(--accent-glow);border-color:var(--accent)}
        .pagination-arrow.disabled{color:#555;cursor:not-allowed}
        .pagination-dots{color:var(--text-secondary);cursor:default}
        
        .modal-overlay{position:fixed;inset:0;background-color:rgba(0,0,0,.7);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease;padding:1rem}
        .modal-overlay.active{opacity:1;visibility:visible}
        .modal-card{background-color:var(--bg-card);border:1px solid var(--border);border-radius:.75rem;width:100%;max-width:400px;transition:transform .3s ease;animation:none;overflow:hidden}
        .modal-overlay.active .modal-card{animation:fadeInUp .4s ease-out forwards}
        .modal-tabs{display:flex;border-bottom:1px solid var(--border)}.tab-btn{flex:1;padding:1rem;background:none;border:none;color:var(--text-secondary);font-size:1rem;font-weight:500;cursor:pointer;transition:color .2s ease;position:relative}.tab-btn::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:2px;background:var(--accent);transform:scaleX(0);transition:transform .3s ease}.tab-btn.active{color:var(--text-primary)}.tab-btn.active::after{transform:scaleX(1)}
        .forms-wrapper{display:flex;width:200%;transition:transform .4s cubic-bezier(.77,0,.18,1)}
        .modal-card[data-active-form="login"] .forms-wrapper{transform:translateX(-50%)}
        .form-container{width:50%;padding:1.5rem 2rem 2rem;flex-shrink:0}
        .input-group{position:relative;margin-bottom:1.25rem}
        .form-control{width:100%;padding:.75rem 1rem;background-color:var(--bg);border:1px solid var(--border);border-radius:.5rem;color:var(--text-primary);font-size:1rem;transition:all .2s ease}.form-control:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
        .alert-danger{display:flex;align-items:center;gap:.75rem;background-color:var(--danger-bg);color:var(--danger);border:1px solid var(--danger);padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1.5rem;font-size:.9rem}.alert-danger svg{width:1.2rem;height:1.2rem;flex-shrink:0}
        
        .site-footer{text-align:center;padding:2rem 0;color:var(--text-secondary);font-size:.9rem;margin-top:4rem;border-top:1px solid var(--border)}.site-footer a{color:var(--text-primary);font-weight:500}.site-footer a:hover{color:var(--accent)}
        
        .user-profile{position:relative}
        .profile-btn{display:flex;align-items:center;gap:.75rem;background:none;border:none;color:var(--text-primary);cursor:pointer;padding:.5rem;border-radius:.5rem;transition:background-color .2s ease}
        .profile-btn:hover,.profile-btn.active{background-color:var(--bg-card)}
        .profile-btn img{width:32px;height:32px;border-radius:50%;border:1px solid var(--border)}
        .profile-btn span{font-weight:500}
        .profile-dropdown{position:absolute;top:calc(100% + 10px);right:0;background-color:var(--bg-card);border:1px solid var(--border);border-radius:.5rem;width:200px;box-shadow:0 8px 20px rgba(0,0,0,.2);z-index:100;opacity:0;visibility:hidden;transform:translateY(10px);transition:all .3s ease}
        .profile-dropdown.show{opacity:1;visibility:visible;transform:translateY(0)}
        .dropdown-header{padding:1rem;border-bottom:1px solid var(--border)}
        .dropdown-header .username{font-weight:600;margin:0}.dropdown-header .email{font-size:.875rem;color:var(--text-secondary);margin:0}
        .profile-dropdown a{display:block;padding:.75rem 1rem;color:var(--text-secondary)}.profile-dropdown a:hover{background-color:var(--bg);color:var(--text-primary)}
        .dropdown-divider{height:1px;background:var(--border);margin:.5rem 0}
        
        @media(max-width: 768px){
            .hero{padding:4rem 0}
            .hero h1{font-size:2.5rem}
            .snippets-grid{grid-template-columns:1fr;gap:1rem}
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <a href="index.php" class="logo">Codify<span>.</span></a>
            <?php 
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): 
    // Tambahkan kode ini untuk mengambil data user terbaru
    $stmt_user_pp = $mysqli->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_user_pp->bind_param("i", $_SESSION['id']);
    $stmt_user_pp->execute();
    $user_pp_result = $stmt_user_pp->get_result()->fetch_assoc();
    $profile_picture = $user_pp_result['profile_picture'] ?? 'default.png';
    $stmt_user_pp->close();
?>
    <div class="user-profile">
        <button class="profile-btn" id="profile-btn">
            <img src="db/profile/<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar">
            <span><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
        </button>
                    <div class="profile-dropdown" id="profile-dropdown">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="profile.php">Settings</a>
                        <a href="leaderboard.php">Leaderboard</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn btn-primary" id="auth-modal-btn">Get Started</button>
            <?php endif; ?>
        </header>

        <main>
            <section class="hero">
                <h1>Codify | Your Code Deserves Better.</h1>
                <p>Built for developers who lead, Codify delivers a smarter way to share, scale, and stand out.</p>
            </section>

            <section class="snippets-grid">
                <?php foreach ($snippets as $index => $snippet): ?>
                    <div class="snippet-card" data-href="view.php?id=<?php echo htmlspecialchars($snippet['share_id']); ?>" style="animation-delay: <?php echo $index * 50; ?>ms;">
                        <div class="card-preview">
                            <pre><code class="language-<?php echo htmlspecialchars($snippet['language']); ?>"><?php echo htmlspecialchars(mb_strimwidth($snippet['code_content'], 0, 250, "...")); ?></code></pre>
                        </div>
                        <div class="card-content">
                        <h3 class="card-title"><a href="view.php?id=<?php echo htmlspecialchars($snippet['share_id']); ?>"><?php echo htmlspecialchars($snippet['title']); ?></a></h3>
                            <div class="card-footer">
                                <div class="card-user">
                                <img src="db/profile/<?php echo htmlspecialchars($snippet['profile_picture'] ?? 'default.png'); ?>" alt="User Avatar">
                                    <span class="user-info">
                                        <?php echo htmlspecialchars($snippet['username']); ?>
                                        <?php if ($snippet['is_verified']): ?>
                                            <svg class="verified-badge" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.03 15.44l-3.53-3.54c-.39-.39-.39-1.02 0-1.41l.71-.71c.39-.39 1.02-.39 1.41 0l2.12 2.12 4.95-4.95c.39-.39 1.02-.39 1.41 0l.71.71c.39.39.39 1.02 0 1.41l-6.36 6.36c-.39.39-1.03.39-1.42 0z"></path></svg>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="card-meta">
                                    <span class="meta-item" title="Views">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"></path></svg>
                                        <?php echo number_format($snippet['views']); ?>
                                    </span>
                                    <span class="meta-item" title="Language">
                                        <i class="<?php echo getLanguageIconClass($snippet['language']); ?>"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
            
            <?php if ($total_pages > 1): ?>
            <nav class="pagination">
                <?php generatePagination($total_pages, $page); ?>
            </nav>
            <?php endif; ?>
        </main>
        
        <footer class="site-footer">
            <p>&copy; <?php echo date("Y"); ?> Codify. All intellectual property rights reserved. Built and owned by <a href="https://github.com/dgamegt" target="_blank" rel="noopener noreferrer">DGXO</a>.</p>
        </footer>
    </div>

    <div class="modal-overlay" id="auth-modal">
        <div class="modal-card" id="auth-modal-card" data-active-form="register">
            <div class="modal-tabs">
                <button class="tab-btn active" data-form="register">Register</button>
                <button class="tab-btn" data-form="login">Login</button>
            </div>
            <div class="forms-wrapper">
                <div class="form-container" id="register-form">
                    <?php if (!empty($auth_err) && $form_action === 'register'): ?>
                        <div class="alert-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg>
                            <span><?php echo $auth_err; ?></span>
                        </div>
                    <?php endif; ?>
                    <form action="index.php" method="post" novalidate>
                        <input type="hidden" name="action" value="register">
                        <div class="input-group"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                        <div class="input-group"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
                    </form>
                </div>
                <div class="form-container" id="login-form">
                    <?php if (!empty($auth_err) && $form_action === 'login'): ?>
                        <div class="alert-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg>
                            <span><?php echo $auth_err; ?></span>
                        </div>
                    <?php endif; ?>
                    <form action="index.php" method="post" novalidate>
                        <input type="hidden" name="action" value="login">
                        <div class="input-group"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                        <div class="input-group"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        hljs.highlightAll();

        const authModal = document.getElementById("auth-modal");
        const authModalCard = document.getElementById("auth-modal-card");
        const authModalBtn = document.getElementById("auth-modal-btn");
        const tabBtns = document.querySelectorAll(".tab-btn");
        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        const snippetCards = document.querySelectorAll('.snippet-card');

        function showAuthModal(form = "register") {
            if (authModal) {
                authModal.classList.add("active");
                switchAuthForm(form);
            }
        }

        function hideAuthModal() {
            if (authModal) {
                authModal.classList.remove("active");
            }
        }

        function switchAuthForm(form) {
            if(authModalCard) {
                authModalCard.dataset.activeForm = form;
                tabBtns.forEach(btn => {
                    btn.classList.toggle("active", btn.dataset.form === form);
                });
            }
        }
        
        if (authModalBtn) {
            authModalBtn.addEventListener("click", () => showAuthModal("register"));
        }

        if (authModal) {
            authModal.addEventListener("click", e => {
                if (e.target === authModal) {
                    hideAuthModal();
                }
            });
        }

        tabBtns.forEach(btn => {
            btn.addEventListener("click", () => switchAuthForm(btn.dataset.form));
        });

        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && authModal && authModal.classList.contains("active")) {
                hideAuthModal();
            }
        });

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
                profileBtn.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                    profileBtn.classList.remove('active');
                }
            });
        }

        snippetCards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.tagName.toLowerCase() === 'a' || e.target.closest('a')) {
                    return;
                }
                const href = card.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            });
        });

        <?php if(!empty($auth_err)): ?>
        showAuthModal('<?php echo $form_action; ?>');
        <?php endif; ?>
    });
    </script>
</body>
</html>