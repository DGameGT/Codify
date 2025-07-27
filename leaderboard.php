<?php
header('Content-Type: text/html; charset=utf-8');
ob_start("ob_gzhandler");

require_once "includes/db.php";
require_once "includes/functions.php";

session_start();

$view_mode = isset($_GET['profile']) && !empty($_GET['profile']) ? 'profile' : 'leaderboard';
$current_user_id = $_SESSION['id'] ?? null;
$page_data = [];
$message = [];

function getProfilePicture($filename, $username) {
    $path = "db/profile/" . $filename;
    if ($filename && $filename !== 'default.png' && file_exists($path)) {
        return htmlspecialchars($path);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=6366f1&color=ffffff&size=256&bold=true&format=svg';
}

function getBannerUrl($filename) {
    $path = "db/thumbnails/" . $filename;
    if ($filename && file_exists($path)) {
        return htmlspecialchars($path);
    }
    return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 400"><defs><linearGradient id="modernGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#667eea"/><stop offset="50%" style="stop-color:#764ba2"/><stop offset="100%" style="stop-color:#f093fb"/></linearGradient><filter id="noise"><feTurbulence baseFrequency="0.9" numOctaves="4" stitchTiles="stitch"/><feColorMatrix type="saturate" values="0"/></filter></defs><rect width="100%" height="100%" fill="url(#modernGrad)"/><rect width="100%" height="100%" fill="url(#modernGrad)" opacity="0.4" filter="url(#noise)"/></svg>');
}

function fetchLeaderboardData($mysqli) {
    $data = [];
    $sql = "SELECT u.id, u.username, u.display_name, u.profile_picture, u.thumbnail, u.is_verified, COUNT(c.id) AS code_count 
            FROM users u LEFT JOIN codes c ON u.id = c.user_id 
            GROUP BY u.id HAVING code_count > 0 
            ORDER BY code_count DESC, u.id ASC LIMIT 50";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $stmt->close();
    }
    return $data;
}

function fetchUserDataByUsername($mysqli, $username) {
    $sql = "SELECT id, username, display_name, api_key, bio, profile_picture, thumbnail, is_verified, social_links FROM users WHERE username = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return null;
}

function fetchUserSnippets($mysqli, $userId) {
    $snippets = [];
    $sql = "SELECT share_id, title, language, created_at, view_count FROM codes WHERE user_id = ? ORDER BY created_at DESC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $snippets[] = $row;
        $stmt->close();
    }
    return $snippets;
}

if ($view_mode === 'leaderboard') {
    $page_data['leaderboard'] = fetchLeaderboardData($mysqli);
} else {
    $viewed_username = $_GET['profile'];
    $user_data = fetchUserDataByUsername($mysqli, $viewed_username);

    if (!$user_data) {
        http_response_code(404);
        die("User not found.");
    }

    $page_data['user'] = $user_data;
    $page_data['snippets'] = fetchUserSnippets($mysqli, $user_data['id']);
    $page_data['is_owner'] = ($current_user_id === $user_data['id']);
    $page_data['social_links'] = json_decode($user_data['social_links'] ?? '[]', true);

    if ($page_data['is_owner'] && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
        header("Location: ?profile=" . urlencode($user_data['username']) . "&status=updated");
        exit;
    }
    
    if (isset($_GET['status']) && $_GET['status'] === 'updated') {
        $message = ["type" => "success", "text" => "Profile updated successfully!"];
    }
}

function renderVerifiedBadge($classes = 'w-5 h-5') {
    return "<div class='inline-flex items-center justify-center {$classes} bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full'>
        <svg class='w-3 h-3 text-white' fill='currentColor' viewBox='0 0 20 20'>
            <path fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/>
        </svg>
    </div>";
}

function renderPodiumCard($user, $rank) {
    $username = htmlspecialchars($user['display_name'] ?? $user['username']);
    $profilePic = getProfilePicture($user['profile_picture'], $user['username']);
    $bannerUrl = getBannerUrl($user['thumbnail']);
    
    $rankStyles = [
        1 => 'from-amber-400 via-yellow-400 to-amber-500',
        2 => 'from-slate-300 via-slate-400 to-slate-500', 
        3 => 'from-orange-400 via-amber-500 to-orange-600'
    ];
    
    $animationDelay = $rank * 150;

    echo "<div class='group relative overflow-hidden rounded-3xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200/50 dark:border-slate-700/50 shadow-xl transition-all duration-700 hover:scale-105 hover:shadow-2xl hover:shadow-indigo-500/25' style='animation: slideInUp 0.8s ease-out {$animationDelay}ms both;'>
        <div class='absolute inset-0 bg-gradient-to-br {$rankStyles[$rank]} opacity-5'></div>
        <a href='?profile=".urlencode($user['username'])."' class='block relative'>
            <div class='relative h-32 overflow-hidden'>
                <div class='absolute inset-0 bg-gradient-to-br {$rankStyles[$rank]} opacity-90'></div>
                <div class='absolute inset-0' style='background-image: url(\"{$bannerUrl}\"); background-size: cover; background-position: center; mix-blend-mode: overlay;'></div>
                <div class='absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-white font-bold text-lg'>#{$rank}</div>
            </div>
            <div class='relative px-6 pb-6 -mt-12'>
                <div class='flex justify-center mb-4'>
                    <div class='relative'>
                        <img src='{$profilePic}' alt='{$username}' class='w-20 h-20 rounded-2xl border-4 border-white dark:border-slate-800 shadow-lg transition-transform duration-500 group-hover:scale-110'>
                        <div class='absolute -bottom-1 -right-1 w-6 h-6 bg-gradient-to-r {$rankStyles[$rank]} rounded-full flex items-center justify-center'>
                            <span class='text-white text-xs font-bold'>#{$rank}</span>
                        </div>
                    </div>
                </div>
                <div class='text-center space-y-2'>
                    <div class='flex items-center justify-center gap-2'>
                        <h3 class='text-lg font-bold text-slate-800 dark:text-white'>{$username}</h3>
                        " . ($user['is_verified'] ? renderVerifiedBadge('w-5 h-5') : '') . "
                    </div>
                    <div class='inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-full text-sm font-semibold shadow-lg'>
                        <svg class='w-4 h-4 mr-2' fill='currentColor' viewBox='0 0 20 20'>
                            <path d='M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z'/>
                        </svg>
                        ".number_format($user['code_count'])." Codes
                    </div>
                </div>
            </div>
        </a>
    </div>";
}

function renderLeaderboardRow($user, $rank, $isCurrentUser) {
    $username = htmlspecialchars($user['display_name'] ?? $user['username']);
    $profilePic = getProfilePicture($user['profile_picture'], $user['username']);
    $highlightClass = $isCurrentUser ? 'bg-gradient-to-r from-indigo-50/50 to-purple-50/50 dark:from-indigo-900/20 dark:to-purple-900/20 border-l-4 border-l-indigo-500' : 'border-l-4 border-l-transparent';

    echo "<a href='?profile=".urlencode($user['username'])."' class='group flex items-center justify-between p-6 transition-all duration-300 hover:bg-slate-50/50 dark:hover:bg-slate-700/30 {$highlightClass}' style='animation: slideInLeft 0.6s ease-out ".($rank * 50)."ms both;'>
        <div class='flex items-center gap-6'>
            <div class='flex items-center justify-center w-12 h-12 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 text-slate-600 dark:text-slate-300 font-bold text-lg group-hover:scale-110 transition-transform duration-300'>
                #{$rank}
            </div>
            <div class='relative'>
                <img src='{$profilePic}' alt='{$username}' class='w-14 h-14 rounded-2xl shadow-md group-hover:scale-105 transition-transform duration-300'>
                <div class='absolute inset-0 rounded-2xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300'></div>
            </div>
            <div class='space-y-1'>
                <div class='flex items-center gap-2'>
                    <h3 class='font-semibold text-slate-800 dark:text-slate-100 text-lg'>{$username}</h3>
                    " . ($user['is_verified'] ? renderVerifiedBadge('w-5 h-5') : '') . "
                    " . ($isCurrentUser ? "<span class='px-3 py-1 bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-xs font-medium rounded-full'>You</span>" : "") . "
                </div>
                <p class='text-sm text-slate-500 dark:text-slate-400'>@".htmlspecialchars($user['username'])."</p>
            </div>
        </div>
        <div class='text-right space-y-1'>
            <div class='text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent'>".number_format($user['code_count'])."</div>
            <div class='text-sm text-slate-500 dark:text-slate-400'>snippets</div>
        </div>
    </a>";
}

function renderProfileHeader($user, $social_links, $is_owner) {
    $username = htmlspecialchars($user['display_name'] ?? $user['username']);
    $profilePic = getProfilePicture($user['profile_picture'], $user['username']);
    $bannerUrl = getBannerUrl($user['thumbnail']);
    
    echo "<div class='relative overflow-hidden rounded-3xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200/50 dark:border-slate-700/50 shadow-2xl mb-8' style='animation: slideInUp 0.8s ease-out;'>
        <div class='relative h-64 md:h-80 overflow-hidden'>
            <div class='absolute inset-0' style='background-image: url(\"{$bannerUrl}\"); background-size: cover; background-position: center;'></div>
            <div class='absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent'></div>
            <div class='absolute inset-0 bg-gradient-to-br from-indigo-500/20 via-purple-500/10 to-pink-500/20'></div>
        </div>
        <div class='relative px-8 pb-8 -mt-20 md:-mt-24'>
            <div class='flex flex-col md:flex-row items-center md:items-end gap-6'>
                <div class='relative group'>
                    <img src='{$profilePic}' alt='{$username}' class='w-36 h-36 md:w-44 md:h-44 rounded-3xl border-6 border-white dark:border-slate-800 shadow-2xl transition-transform duration-500 group-hover:scale-105'>
                    <div class='absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300'></div>
                </div>
                <div class='flex-1 text-center md:text-left space-y-3'>
                    <div class='flex items-center justify-center md:justify-start gap-3'>
                        <h1 class='text-4xl md:text-5xl font-black bg-gradient-to-r from-slate-800 via-slate-700 to-slate-800 dark:from-white dark:via-slate-100 dark:to-white bg-clip-text text-transparent'>
                            {$username}
                        </h1>
                        " . ($user['is_verified'] ? renderVerifiedBadge('w-8 h-8') : '') . "
                    </div>
                    <p class='text-slate-500 dark:text-slate-400 text-lg font-medium'>@".htmlspecialchars($user['username'])."</p>
                    <p class='text-slate-600 dark:text-slate-300 max-w-2xl leading-relaxed'>".nl2br(htmlspecialchars($user['bio']))."</p>
                </div>
                " . ($is_owner ? "
                <button onclick=\"document.getElementById('edit-profile-modal').classList.remove('hidden')\" class='px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105'>
                    <svg class='w-5 h-5 inline-block mr-2' fill='currentColor' viewBox='0 0 20 20'>
                        <path d='M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z'/>
                    </svg>
                    Edit Profile
                </button>
                " : "") . "
            </div>
        </div>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view_mode === 'profile' ? htmlspecialchars($page_data['user']['display_name'] ?? $page_data['user']['username']) . ' - Profile' : 'CSC Leaderboard'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dark .glass-effect {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 6s ease-in-out infinite;
        }

        .gradient-border {
            position: relative;
            background: linear-gradient(45deg, #6366f1, #8b5cf6, #ec4899);
            padding: 2px;
            border-radius: 24px;
        }

        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            padding: 2px;
            background: linear-gradient(45deg, #6366f1, #8b5cf6, #ec4899);
            border-radius: inherit;
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: xor;
        }

        .theme-toggle {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 50;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.2);
        }

        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'slide-up': 'slideInUp 0.8s ease-out',
                        'slide-left': 'slideInLeft 0.6s ease-out',
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                    },
                }
            }
        }
    </style>
    <script>
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const storedTheme = localStorage.getItem('theme');
        const theme = storedTheme || (prefersDark ? 'dark' : 'light');
        
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.setAttribute('data-theme', theme);

        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            const newTheme = isDark ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }
    </script>
</head>
<body class="min-h-screen text-slate-800 dark:text-slate-200 transition-colors duration-300">
    <div class="theme-toggle" onclick="toggleTheme()">
        <svg class="w-6 h-6 text-white dark:hidden" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
        </svg>
        <svg class="w-6 h-6 text-white hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
        </svg>
    </div>

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <?php if ($view_mode === 'leaderboard'): ?>
            <header class="text-center mb-16 space-y-6" style="animation: slideInUp 0.8s ease-out;">
                <div class="floating">
                    <a href="/" class="inline-block no-underline group">
                        <h1 class="text-6xl md:text-7xl font-black mb-4">
                            <span class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">CSC</span>
                            <span class="text-slate-800 dark:text-white ml-4">Leaderboard</span>
                        </h1>
                    </a>
                </div>
                <p class="text-xl md:text-2xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto leading-relaxed">
                    Discover the most active code contributors in our vibrant community. Share your creativity and climb the ranks!
                </p>
            </header>

            <?php if (isset($page_data['leaderboard']) && count($page_data['leaderboard']) >= 3): ?>
            <section class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                <?php foreach (array_slice($page_data['leaderboard'], 0, 3) as $index => $user) {
                    renderPodiumCard($user, $index + 1);
                } ?>
            </section>
            <?php endif; ?>

            <section class="glass-effect rounded-3xl shadow-2xl overflow-hidden" style="animation: slideInUp 0.8s ease-out 0.3s both;">
                <div class="divide-y divide-slate-200/50 dark:divide-slate-700/50">
                    <?php if (isset($page_data['leaderboard']) && !empty($page_data['leaderboard'])): ?>
                        <?php foreach ($page_data['leaderboard'] as $index => $user):
                            renderLeaderboardRow($user, $index + 1, $user['id'] == $current_user_id);
                        endforeach; ?>
                    <?php else: ?>
                        <div class="p-16 text-center">
                            <div class="w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-slate-600 dark:text-slate-300 mb-2">No contributors yet</h3>
                            <p class="text-slate-500 dark:text-slate-400">Be the first to share your code and claim the top spot!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <nav class="mb-8" style="animation: slideInLeft 0.6s ease-out;">
                <a href="/leaderboard" class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl text-white hover:bg-white/20 transition-all duration-300 hover:scale-105 shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Leaderboard
                </a>
            </nav>

            <?php renderProfileHeader($page_data['user'], $page_data['social_links'], $page_data['is_owner']); ?>

            <main class="space-y-8" style="animation: slideInUp 0.8s ease-out 0.2s both;">
                <div class="flex items-center justify-between">
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-slate-800 to-slate-600 dark:from-white dark:to-slate-300 bg-clip-text text-transparent">
                        Shared Snippets
                    </h2>
                    <div class="flex items-center gap-2 px-4 py-2 glass-effect rounded-2xl">
                        <svg class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                        </svg>
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400"><?= count($page_data['snippets']) ?> snippets</span>
                    </div>
                </div>

                <?php if (empty($page_data['snippets'])): ?>
                    <div class="glass-effect rounded-3xl p-16 text-center">
                        <div class="w-32 h-32 mx-auto mb-8 bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 rounded-full flex items-center justify-center">
                            <svg class="w-16 h-16 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-600 dark:text-slate-300 mb-4">No code snippets yet</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-lg">This developer hasn't shared any code snippets with the community yet.</p>
                    </div>
                <?php else: ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($page_data['snippets'] as $index => $snippet): ?>
                            <div class="group glass-effect rounded-2xl p-6 transition-all duration-500 hover:scale-105 hover:shadow-2xl hover:shadow-indigo-500/25" style="animation: slideInUp 0.6s ease-out <?= $index * 100 ?>ms both;">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-2 line-clamp-2"><?= htmlspecialchars($snippet['title']) ?></h3>
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="px-3 py-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-xs font-semibold rounded-full">
                                                <?= htmlspecialchars($snippet['language']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <div class="space-y-3 mb-6">
                                    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span><?= number_format($snippet['view_count']) ?> views</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span><?= date('M j, Y', strtotime($snippet['created_at'])) ?></span>
                                    </div>
                                </div>

                                <a href="/view?id=<?= $snippet['share_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl transition-all duration-300 group-hover:scale-105 shadow-lg">
                                    <span>View Code</span>
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        <?php endif; ?>
    </div>

    <?php if ($view_mode === 'profile' && $page_data['is_owner']): ?>
    <div id="edit-profile-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4" onclick="event.target === this && this.classList.add('hidden')">
        <div class="glass-effect rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto scrollbar-hide" onclick="event.stopPropagation()" style="animation: slideInUp 0.5s ease-out;">
            <form action="?profile=<?= urlencode($page_data['user']['username']) ?>" method="POST" enctype="multipart/form-data" class="p-8">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-slate-800 to-slate-600 dark:from-white dark:to-slate-300 bg-clip-text text-transparent">
                        Edit Your Profile
                    </h2>
                    <button type="button" onclick="document.getElementById('edit-profile-modal').classList.add('hidden')" class="w-10 h-10 rounded-full bg-slate-200/50 dark:bg-slate-700/50 flex items-center justify-center hover:bg-slate-300/50 dark:hover:bg-slate-600/50 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <input type="hidden" name="action" value="save_changes">
                
                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Display Name</label>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($page_data['user']['display_name'] ?? '') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/50 dark:bg-slate-800/50 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Bio</label>
                        <textarea name="bio" rows="4" placeholder="Tell us about yourself..." class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/50 dark:bg-slate-800/50 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all resize-none"><?= htmlspecialchars($page_data['user']['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Profile Picture</label>
                            <input type="file" name="profile_picture" accept="image/*" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/50 dark:bg-slate-800/50 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Banner Image</label>
                            <input type="file" name="banner" accept="image/*" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/50 dark:bg-slate-800/50 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-slate-200/50 dark:border-slate-700/50">
                    <button type="button" onclick="document.getElementById('edit-profile-modal').classList.add('hidden')" class="px-6 py-3 bg-slate-200/50 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 font-semibold rounded-xl hover:bg-slate-300/50 dark:hover:bg-slate-600/50 transition-all duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
    <div id="notification" class="fixed top-4 right-4 z-50 px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-2xl shadow-lg" style="animation: slideInLeft 0.5s ease-out;">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            <span class="font-semibold"><?= htmlspecialchars($message['text']) ?></span>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.animation = 'slideInUp 0.5s ease-out reverse';
                setTimeout(() => notification.remove(), 500);
            }
        }, 3000);
    </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.group');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            document.querySelectorAll('[style*="animation"]').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>