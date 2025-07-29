<?php
header('Content-Type: text/html; charset=utf-8');
ob_start("ob_gzhandler");

require_once "includes/db.php";
require_once "includes/functions.php";

$current_user_id = $_SESSION['id'] ?? null;
$username = $_SESSION['username'] ?? null;
$user_role = '';
$profile_picture = 'default.png';

if ($current_user_id) {
    $stmt_user = $mysqli->prepare("SELECT role, profile_picture FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $current_user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($user_data = $result_user->fetch_assoc()) {
        $user_role = $user_data['role'];
        $profile_picture = $user_data['profile_picture'] ?? 'default.png';
    }
    $stmt_user->close();
}

$leaderboard_data = fetchLeaderboardData($mysqli);

function getProfilePicture($filename, $username) {
    if ($filename && filter_var($filename, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($filename);
    }
    $path = "db/profile/" . $filename;
    if ($filename && $filename !== 'default.png' && file_exists($path)) {
        return htmlspecialchars($path);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=4f46e5&color=ffffff&size=128&bold=true';
}

function getBannerUrl($filename) {
    if ($filename && filter_var($filename, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($filename);
    }
    $path = "db/thumbnails/" . $filename;
    if ($filename && file_exists($path)) {
        return htmlspecialchars($path);
    }
    // Fallback keren jika tidak ada banner
    return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0a192f"/><stop offset="100%" stop-color="#112240"/></linearGradient><filter id="n"><feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch"/></filter></defs><rect width="100%" height="100%" fill="url(#g)"/><rect width="100%" height="100%" fill="url(#g)" opacity="0.2" filter="url(#n)"/></svg>');
}

function fetchLeaderboardData($mysqli) {
    $data = [];
    // Query mengambil data user untuk leaderboard
    $sql = "SELECT u.id, u.username, u.display_name, u.profile_picture, u.thumbnail, u.is_verified, COUNT(c.id) AS code_count 
            FROM users u LEFT JOIN codes c ON u.id = c.user_id 
            GROUP BY u.id HAVING code_count > 0 
            ORDER BY code_count DESC, u.id ASC LIMIT 50";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
    return $data;
}

function renderVerifiedBadge($classes = 'w-5 h-5') {
    return "<div class='inline-flex items-center justify-center {$classes}' style='color: var(--accent-color);'>
        <svg class='w-full h-full' fill='currentColor' viewBox='0 0 20 20'>
            <path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z' clip-rule='evenodd'/>
        </svg>
    </div>";
}

function renderPodiumCard($user, $rank) {
    $username = htmlspecialchars($user['display_name'] ?? $user['username']);
    $profilePic = getProfilePicture($user['profile_picture'], $user['username']);
    
    $rank_styles = [
        1 => ['order' => 'order-2 md:order-2', 'height' => 'h-48', 'icon' => '👑', 'color' => '#ffd700', 'shadow' => 'shadow-[0_0_30px_#ffd700]'],
        2 => ['order' => 'order-1 md:order-1', 'height' => 'h-40 self-end', 'icon' => '🥈', 'color' => '#c0c0c0', 'shadow' => 'shadow-[0_0_20px_#c0c0c0]'],
        3 => ['order' => 'order-3 md:order-3', 'height' => 'h-40 self-end', 'icon' => '🥉', 'color' => '#cd7f32', 'shadow' => 'shadow-[0_0_20px_#cd7f32]']
    ];
    $style = $rank_styles[$rank];

    echo "<div class='flex flex-col items-center text-center {$style['order']} group'>";
    echo "  <p class='text-4xl font-bold mb-2' style='color:{$style['color']}'>{$style['icon']}</p>";
    echo "  <a href='profile.php?user=".urlencode($user['username'])."' class='relative'>";
    echo "      <img src='{$profilePic}' alt='{$username}' class='w-24 h-24 rounded-full border-4 transition-all duration-300 group-hover:scale-110 {$style['shadow']}' style='border-color: {$style['color']};'>";
    echo "  </a>";
    echo "  <h3 class='mt-4 text-lg font-bold flex items-center gap-2' style='color: var(--text-primary);'>{$username}" . ($user['is_verified'] ? renderVerifiedBadge() : '') . "</h3>";
    echo "  <p class='text-sm font-mono' style='color: var(--text-secondary);'>".number_format($user['code_count'])." snippets</p>";
    echo "</div>";
}

function renderLeaderboardRow($user, $rank, $isCurrentUser) {
    $username = htmlspecialchars($user['display_name'] ?? $user['username']);
    $profilePic = getProfilePicture($user['profile_picture'], $user['username']);
    $highlightClass = $isCurrentUser ? 'border-l-4' : '';

    echo "<a href='profile.php?user=".urlencode($user['username'])."' class='grid grid-cols-12 items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 hover:scale-[1.02] {$highlightClass}' style='background-color: var(--card-bg); border: 1px solid var(--border-color); " . ($isCurrentUser ? "border-left-color: var(--accent-color);" : "") . "'>";
    echo "  <div class='col-span-1 text-center font-bold' style='color: var(--text-secondary);'>#{$rank}</div>";
    echo "  <div class='col-span-6 flex items-center gap-4'>";
    echo "      <img src='{$profilePic}' alt='{$username}' class='w-10 h-10 rounded-full'>";
    echo "      <div>";
    echo "          <h4 class='font-bold flex items-center gap-2' style='color: var(--text-primary);'>{$username}" . ($user['is_verified'] ? renderVerifiedBadge('w-4 h-4') : '') . "</h4>";
    echo "          <p class='text-xs' style='color: var(--text-secondary);'>@".htmlspecialchars($user['username'])."</p>";
    echo "      </div>";
    echo "  </div>";
    echo "  <div class='col-span-4 text-right font-mono font-bold' style='color: var(--text-primary);'>".number_format($user['code_count'])." <span style='color: var(--text-secondary);'>snippets</span></div>";
    if ($isCurrentUser) {
        echo "<div class='col-span-1 text-center'><span class='px-2 py-1 text-xs rounded-full font-semibold' style='color: var(--accent-color); background-color: var(--hover-bg);'>You</span></div>";
    }
    echo "</a>";
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Codify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@v2.15.1/devicon.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Inter', sans-serif;
            --font-serif: 'Sora', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: var(--font-sans); 
            background-color: var(--bg-color);
            -webkit-font-smoothing: antialiased; 
            -moz-osx-font-smoothing: grayscale; 
            transition: background-color .3s ease; 
        }
        .theme-glassmorphism { --bg-color: #0d1117; --sidebar-bg: rgba(22, 27, 34, 0.7); --main-bg: transparent; --card-bg: rgba(34, 40, 49, 0.6); --modal-bg: rgba(22, 27, 34, 0.85); --border-color: rgba(139, 148, 158, 0.3); --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow: 0 0 15px rgba(38, 129, 255, 0.6); --accent-color: #2681ff; --hover-bg: rgba(56, 139, 253, 0.1); }
        .theme-neumorphism { --bg-color: #e0e5ec; --sidebar-bg: #e0e5ec; --main-bg: #e0e5ec; --card-bg: #e0e5ec; --modal-bg: #e0e5ec; --border-color: transparent; --text-primary: #5c677b; --text-secondary: #9ba6b9; --accent-color: #4a7dff; --shadow-light: #ffffff; --shadow-dark: #a3b1c6; --card-shadow: inset 6px 6px 12px var(--shadow-dark), inset -6px -6px 12px var(--shadow-light); --button-shadow: 6px 6px 12px var(--shadow-dark), -6px -6px 12px var(--shadow-light); }
        .dark.theme-neumorphism { --bg-color: #2c3038; --sidebar-bg: #2c3038; --main-bg: #2c3038; --card-bg: #2c3038; --modal-bg: #2c3038; --text-primary: #d0d3d8; --text-secondary: #7e8490; --accent-color: #5a8dff; --shadow-light: #363b44; --shadow-dark: #22252c; }
        .theme-hacker { --bg-color: #000; --sidebar-bg: rgba(0,0,0,0.8); --main-bg: transparent; --card-bg: rgba(10, 25, 47, 0.2); --modal-bg: #0a192f; --border-color: rgba(0, 255, 128, 0.3); --text-primary: #00ff80; --text-secondary: #00a354; --accent-color: #a855f7; --accent-glow: 0 0 12px rgba(168, 85, 247, 0.8); }
        .theme-minimal { --bg-color: #111111; --sidebar-bg: #111111; --main-bg: #111111; --card-bg: #1C1C1C; --modal-bg: #222222; --border-color: #333; --text-primary: #f0f0f0; --text-secondary: #999; --accent-color: #3b82f6; --hover-bg: #2a2a2a; }
        .theme-hybrid { --bg-color: #0a0a0a; --sidebar-bg: rgba(18, 18, 18, 0.7); --main-bg: transparent; --card-bg: rgba(26, 26, 26, 0.6); --modal-bg: #121212; --border-color: #2a2a2a; --text-primary: #e5e5e5; --text-secondary: #888; --accent-color: #00e0b8; --accent-glow: 0 0 15px rgba(0, 224, 184, 0.5); --shadow-light: #2c2c2c; --shadow-dark: #000000; --button-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light); }
        .theme-hacker { font-family: var(--font-mono); }
        .theme-minimal { font-family: var(--font-serif); }
        .bg-gradient-animate { background-size: 200% 200%; animation: gradient 15s ease infinite; }
        @keyframes gradient { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .bg-grid { background-image: linear-gradient(var(--border-color) 1px, transparent 1px), linear-gradient(to right, var(--border-color) 1px, transparent 1px); background-size: 2rem 2rem; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data='{
    theme: localStorage.getItem("theme") || "theme-glassmorphism",
    setTheme(t) { this.theme = t; localStorage.setItem("theme", t); },
    isSidebarOpen: window.innerWidth > 768,
    isProfileMenuOpen: false
}'
:class="theme"
>
<div class="fixed inset-0 -z-10 bg-gradient-to-br from-blue-500/20 via-cyan-500/20 to-purple-500/20 bg-gradient-animate" x-show="theme === 'theme-glassmorphism' || theme === 'theme-hybrid'"></div>
<div class="fixed inset-0 -z-10 bg-black bg-grid" x-show="theme === 'theme-hacker'"></div>

<div class="flex min-h-screen w-full">
    <aside
        class="fixed top-0 left-0 h-full z-40 transition-transform duration-300 ease-in-out w-[260px]"
        :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        style="background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); backdrop-filter: blur(16px);"
        :style="theme === 'theme-neumorphism' ? { boxShadow: 'var(--button-shadow)' } : {}"
    >
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between h-16 px-6 border-b shrink-0" style="border-color: var(--border-color);">
                <a href="index.php" class="flex items-center gap-2 text-xl font-bold" style="color: var(--text-primary);">Codify</a>
                <button @click="isSidebarOpen = false" class="md:hidden" style="color:var(--text-secondary);">&times;</button>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-secondary);"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg> Dashboard</a>
                <a href="leaderboard.php" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-primary); background-color: var(--hover-bg);"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg> Leaderboard</a>
                <?php if (strtolower($user_role) === 'owner'): ?>
                <a href="admin.php" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-secondary);">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" /></svg>
                    Admin Panel
                </a>
                <?php endif; ?>
            </nav>
            <div class="p-4 border-t" style="border-color: var(--border-color);">
                <div x-data="{
                        themes: [
                            { name: 'Glassmorphism', value: 'theme-glassmorphism'},
                            { name: 'Neumorphism', value: 'theme-neumorphism' },
                            { name: 'Cyber Hacker', value: 'theme-hacker' },
                            { name: 'Minimal Dark', value: 'theme-minimal' },
                            { name: 'Hybrid', value: 'theme-hybrid' }
                        ],
                        isOpen: false,
                        currentThemeName() {
                            return this.themes.find(t => t.value === theme).name;
                        }
                    }" class="relative">
                    <button @click="isOpen = !isOpen" class="w-full flex items-center justify-between px-4 py-2 rounded-lg" style="color: var(--text-secondary); background-color: var(--card-bg);">
                        <span x-text="currentThemeName()"></span>
                        <span class="text-xs transition-transform" :class="{'rotate-180': isOpen}">&#9662;</span>
                    </button>
                    <div x-show="isOpen" @click.away="isOpen = false" x-cloak class="absolute bottom-full mb-2 w-full rounded-lg" style="background-color: var(--sidebar-bg); border: 1px solid var(--border-color);">
                        <template x-for="t in themes">
                            <a href="#" @click.prevent="setTheme(t.value); isOpen = false;" class="block px-4 py-2 text-sm" style="color: var(--text-secondary);" x-text="t.name"></a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col transition-all duration-300 ease-in-out" :class="{ 'md:ml-[260px]': isSidebarOpen }">
        <header class="flex items-center justify-between h-16 px-6 shrink-0 border-b gap-4" style="background-color: var(--sidebar-bg); border-color: var(--border-color); backdrop-filter: blur(16px);">
            <div class="flex items-center gap-4">
                <button @click="isSidebarOpen = !isSidebarOpen" style="color: var(--text-secondary);">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($username): ?>
                    <div class="relative">
                        <button @click="isProfileMenuOpen = !isProfileMenuOpen" class="flex items-center gap-2">
                            <img src="db/profile/<?php echo htmlspecialchars($profile_picture); ?>" alt="Avatar" class="w-8 h-8 rounded-full">
                            <span class="hidden md:inline" style="color: var(--text-primary);"><?php echo htmlspecialchars($username); ?></span>
                        </button>
                        <div x-show="isProfileMenuOpen" @click.away="isProfileMenuOpen = false" x-cloak
                            class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg py-1"
                            style="background-color: var(--sidebar-bg); border: 1px solid var(--border-color);">
                            <a href="profile.php" class="block px-4 py-2 text-sm" style="color: var(--text-secondary);">Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm" style="color: var(--text-secondary);">Sign Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="index.php" class="px-4 py-2 rounded-lg font-semibold" style="color: var(--text-primary);">Login</a>
                <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 p-6" style="background-color: var(--main-bg);">
            <header class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-extrabold mb-2" style="color: var(--text-primary);">
                    Codify <span style="color: var(--accent-color);">Leaderboard</span>
                </h1>
                <p class="text-lg" style="color: var(--text-secondary);">Meet the top contributors of our community.</p>
            </header>
            
            <?php if (!empty($leaderboard_data)): ?>
                <?php if (count($leaderboard_data) >= 1): ?>
                <section class="mb-12">
                    <div class="grid grid-cols-3 md:grid-cols-3 gap-4 md:gap-8 items-end max-w-4xl mx-auto">
                        <?php 
                            // Render podium, pastikan tidak error jika user kurang dari 3
                            if (isset($leaderboard_data[1])) renderPodiumCard($leaderboard_data[1], 2); else echo "<div></div>";
                            if (isset($leaderboard_data[0])) renderPodiumCard($leaderboard_data[0], 1); else echo "<div></div>";
                            if (isset($leaderboard_data[2])) renderPodiumCard($leaderboard_data[2], 3); else echo "<div></div>";
                        ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="max-w-4xl mx-auto">
                    <div class="space-y-3">
                        <?php if (count($leaderboard_data) > 3): ?>
                            <?php foreach (array_slice($leaderboard_data, 3) as $index => $user):
                                renderLeaderboardRow($user, $index + 4, $user['id'] == $current_user_id);
                            endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <div class="text-center py-16 rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <h3 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">The Stage is Empty</h3>
                    <p style="color: var(--text-secondary);">Be the first to share a snippet and claim the top spot!</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>
</body>
</html>