<?php
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isLoggedIn()) {
    header("location: /");
    exit;
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
    return ['plaintext' => 'Plain Text', 'html' => 'HTML', 'css' => 'CSS', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'php' => 'PHP', 'python' => 'Python', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'c' => 'C', 'ruby' => 'Ruby', 'go' => 'Go', 'rust' => 'Rust', 'swift' => 'Swift', 'kotlin' => 'Kotlin', 'scala' => 'Scala', 'sql' => 'SQL', 'bash' => 'Bash', 'json' => 'JSON', 'yaml' => 'YAML', 'markdown' => 'Markdown', 'dockerfile' => 'Dockerfile', 'vue' => 'Vue', 'svelte' => 'Svelte', 'angularjs' => 'Angular'];
}

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['code_content'])) {
        $title = trim($_POST['title']);
        $code_content = trim($_POST['code_content']);
        $language = trim($_POST['language']);
        $edit_share_id = trim($_POST['edit_share_id'] ?? '');

        $supported_languages = getSupportedLanguages();

        if (!empty($title) && !empty($code_content) && array_key_exists($language, $supported_languages)) {
            if (!empty($edit_share_id)) {
                $sql_update = "UPDATE codes SET title = ?, code_content = ?, language = ? WHERE share_id = ? AND user_id = ?";
                if ($stmt_update = $mysqli->prepare($sql_update)) {
                    $stmt_update->bind_param("ssssi", $title, $code_content, $language, $edit_share_id, $user_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            } else {
                $share_id = generateShareId($mysqli);
                $sql_insert = "INSERT INTO codes (share_id, user_id, title, code_content, language) VALUES (?, ?, ?, ?, ?)";
                if ($stmt_insert = $mysqli->prepare($sql_insert)) {
                    $stmt_insert->bind_param("sisss", $share_id, $user_id, $title, $code_content, $language);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
            }
        }
    }
    header("Location: dashboard.php");
    exit;
}

$user_details = null;
$sql_user = "SELECT profile_picture, is_verified FROM users WHERE id = ?";
if ($stmt_user = $mysqli->prepare($sql_user)) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_details = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
}
$profile_picture = $user_details['profile_picture'] ?? 'default.png';

$codes_list = [];
$sql_codes = "SELECT share_id, title, language, created_at, views FROM codes WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt_codes = $mysqli->prepare($sql_codes)) {
    $stmt_codes->bind_param("i", $user_id);
    $stmt_codes->execute();
    $result_codes = $stmt_codes->get_result();
    while ($row = $result_codes->fetch_assoc()) {
        $codes_list[] = $row;
    }
    $stmt_codes->close();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Codify</title>
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

        .theme-glassmorphism {
            --bg-color: #0d1117; --sidebar-bg: rgba(22, 27, 34, 0.7); --main-bg: transparent; --card-bg: rgba(34, 40, 49, 0.6); --modal-bg: rgba(22, 27, 34, 0.85); --border-color: rgba(139, 148, 158, 0.3); --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-glow: 0 0 15px rgba(38, 129, 255, 0.6); --accent-color: #2681ff; --hover-bg: rgba(56, 139, 253, 0.1);
        }
        .theme-neumorphism {
            --bg-color: #e0e5ec; --sidebar-bg: #e0e5ec; --main-bg: #e0e5ec; --card-bg: #e0e5ec; --modal-bg: #e0e5ec; --border-color: transparent; --text-primary: #5c677b; --text-secondary: #9ba6b9; --accent-color: #4a7dff; --shadow-light: #ffffff; --shadow-dark: #a3b1c6; --card-shadow: inset 6px 6px 12px var(--shadow-dark), inset -6px -6px 12px var(--shadow-light); --button-shadow: 6px 6px 12px var(--shadow-dark), -6px -6px 12px var(--shadow-light);
        }
        .dark.theme-neumorphism {
            --bg-color: #2c3038; --sidebar-bg: #2c3038; --main-bg: #2c3038; --card-bg: #2c3038; --modal-bg: #2c3038; --text-primary: #d0d3d8; --text-secondary: #7e8490; --accent-color: #5a8dff; --shadow-light: #363b44; --shadow-dark: #22252c;
        }
        .theme-hacker {
            --bg-color: #000; --sidebar-bg: rgba(0,0,0,0.8); --main-bg: transparent; --card-bg: rgba(10, 25, 47, 0.2); --modal-bg: #0a192f; --border-color: rgba(0, 255, 128, 0.3); --text-primary: #00ff80; --text-secondary: #00a354; --accent-color: #a855f7; --accent-glow: 0 0 12px rgba(168, 85, 247, 0.8);
        }
        .theme-minimal {
            --bg-color: #111111; --sidebar-bg: #111111; --main-bg: #111111; --card-bg: #1C1C1C; --modal-bg: #222222; --border-color: #333; --text-primary: #f0f0f0; --text-secondary: #999; --accent-color: #3b82f6; --hover-bg: #2a2a2a;
        }
        .theme-hybrid {
             --bg-color: #0a0a0a; --sidebar-bg: rgba(18, 18, 18, 0.7); --main-bg: transparent; --card-bg: rgba(26, 26, 26, 0.6); --modal-bg: #121212; --border-color: #2a2a2a; --text-primary: #e5e5e5; --text-secondary: #888; --accent-color: #00e0b8; --accent-glow: 0 0 15px rgba(0, 224, 184, 0.5); --shadow-light: #2c2c2c; --shadow-dark: #000000; --button-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        }

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
    isProfileMenuOpen: false,
    isModalOpen: false,
    modalMode: "new",
    currentSnippet: {},
    allSnippets: <?php echo json_encode($codes_list); ?>,
    searchQuery: "",
    get filteredSnippets() {
        if (!this.searchQuery) return this.allSnippets;
        return this.allSnippets.filter(s => s.title.toLowerCase().includes(this.searchQuery.toLowerCase()));
    },
    async openEditModal(shareId) {
        try {
            const response = await fetch(`get_code_details.php?id=${shareId}`);
            if (!response.ok) throw new Error("Network response error.");
            const data = await response.json();
            if (data.error) { alert(data.error); return; }

            this.currentSnippet = {
                share_id: shareId,
                title: data.title,
                language: data.language,
                code_content: data.code_content
            };
            this.modalMode = "edit";
            this.isModalOpen = true;
        } catch (error) {
            console.error("Failed to fetch snippet:", error);
            alert("Failed to load snippet data.");
        }
    }
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
                <a href="#" class="flex items-center gap-2 text-xl font-bold" style="color: var(--text-primary);">Codify</a>
                <button @click="isSidebarOpen = false" class="md:hidden" style="color:var(--text-secondary);">&times;</button>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="#" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-primary); background-color: var(--hover-bg);"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg> Dashboard</a>
                <a href="docs.php" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-secondary);"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg> Docs</a>
                <a href="leaderboard.php" class="flex items-center gap-3 px-4 py-2 rounded-lg" style="color: var(--text-secondary);"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg> Leaderboard</a>
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
                <div class="relative hidden md:block">
                    <input type="text" x-model="searchQuery" placeholder="Search snippets..."
                        class="pl-10 pr-4 py-2 rounded-lg w-64 transition-all duration-300"
                        :style="{
                            'background-color': theme === 'theme-neumorphism' ? 'transparent' : 'var(--card-bg)',
                            'box-shadow': theme === 'theme-neumorphism' ? 'var(--card-shadow)' : 'none',
                            'border': theme.includes('neumorphism') ? 'none' : '1px solid var(--border-color)',
                            'color': 'var(--text-primary)'
                        }"
                    >
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5" style="color: var(--text-secondary);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button @click="isModalOpen = true; modalMode = 'new';"
                    class="px-4 py-2 rounded-lg font-semibold flex items-center gap-2 transition-all duration-300"
                    :style="{
                        'color': theme === 'theme-neumorphism' ? 'var(--accent-color)' : '#fff',
                        'background-color': theme === 'theme-neumorphism' ? 'var(--card-bg)' : 'var(--accent-color)',
                        'box-shadow': theme === 'theme-neumorphism' || theme === 'theme-hybrid' ? 'var(--button-shadow)' : (theme.includes('hacker') || theme.includes('glassmorphism') || theme.includes('hybrid') ? 'var(--accent-glow)' : 'none'),
                    }"
                >+ New Snippet</button>

                <div class="relative">
                    <button @click="isProfileMenuOpen = !isProfileMenuOpen" class="flex items-center gap-2">
                        <img src="db/profile/<?php echo htmlspecialchars($profile_picture); ?>" alt="Avatar" class="w-8 h-8 rounded-full">
                        <span class="hidden md:inline" style="color: var(--text-primary);"><?php echo htmlspecialchars($username); ?> — Online</span>
                    </button>
                    <div x-show="isProfileMenuOpen" @click.away="isProfileMenuOpen = false" x-cloak
                        class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg py-1"
                        style="background-color: var(--sidebar-bg); border: 1px solid var(--border-color);">
                        <a href="profile.php" class="block px-4 py-2 text-sm" style="color: var(--text-secondary);">Profile</a>
                        <a href="logout.php" class="block px-4 py-2 text-sm" style="color: var(--text-secondary);">Sign Out</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6" style="background-color: var(--main-bg);">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">My Snippets</h1>
                <div class="relative md:hidden">
                    <input type="text" x-model="searchQuery" placeholder="Search..."
                        class="pl-10 pr-4 py-2 rounded-lg w-full transition-all duration-300"
                        :style="{
                            'background-color': theme === 'theme-neumorphism' ? 'transparent' : 'var(--card-bg)',
                            'box-shadow': theme === 'theme-neumorphism' ? 'var(--card-shadow)' : 'none',
                            'border': theme.includes('neumorphism') ? 'none' : '1px solid var(--border-color)',
                            'color': 'var(--text-primary)'
                        }"
                    >
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5" style="color: var(--text-secondary);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <template x-for="snippet in filteredSnippets" :key="snippet.share_id">
                    <div
                        class="rounded-2xl p-5 flex flex-col transition-all duration-300"
                        :style="{
                            'background-color': 'var(--card-bg)',
                            'border': theme.includes('neumorphism') ? 'none' : '1px solid var(--border-color)',
                            'box-shadow': theme.includes('neumorphism') ? 'var(--button-shadow)' : 'none',
                        }"
                    >
                        <h3 class="font-bold mb-2 truncate" style="color: var(--text-primary);" x-text="snippet.title"></h3>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: getLanguageInfo(snippet.language).color }"></span>
                            <span class="text-sm capitalize" style="color: var(--text-secondary);" x-text="snippet.language"></span>
                        </div>
                        <p class="text-xs mb-auto" style="color: var(--text-secondary);">Uploaded on <span x-text="new Date(snippet.created_at).toLocaleDateString()"></span></p>
                        <div class="flex items-center justify-end gap-2 mt-4">
                            <button @click="openEditModal(snippet.share_id)" class="px-3 py-1 text-xs rounded-md" style="color: var(--text-secondary); background-color: var(--hover-bg);">Edit</button>
                            <a :href="'view.php?id=' + snippet.share_id" class="px-3 py-1 text-xs rounded-md" style="color: #000; background-color: var(--accent-color);">View</a>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<div x-show="isModalOpen" x-cloak class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @keydown.escape.window="isModalOpen = false">
    <div @click.away="isModalOpen = false"
        class="w-full max-w-2xl rounded-2xl"
        style="background-color: var(--modal-bg); border: 1px solid var(--border-color); backdrop-filter: blur(16px);"
        x-show="isModalOpen"
        x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90"
    >
        <form id="snippet-form" method="post" action="dashboard.php" class="p-6 space-y-4">
             <input type="hidden" name="edit_share_id" x-model="currentSnippet.share_id">
            <h2 class="text-xl font-bold" style="color: var(--text-primary);" x-text="modalMode === 'edit' ? 'Edit Snippet' : 'New Snippet'"></h2>
            <div>
                <label class="text-sm" style="color: var(--text-secondary);">Title</label>
                <input type="text" name="title" x-model="currentSnippet.title" required class="w-full mt-1 p-2 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
            </div>
            <div>
                <label class="text-sm" style="color: var(--text-secondary);">Language</label>
                <select name="language" x-model="currentSnippet.language" class="w-full mt-1 p-2 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                    <?php foreach (getSupportedLanguages() as $value => $name) { echo "<option value=\"$value\">$name</option>"; } ?>
                </select>
            </div>
            <div>
                <label class="text-sm" style="color: var(--text-secondary);">Code</label>
                <textarea name="code_content" rows="10" x-model="currentSnippet.code_content" required class="w-full mt-1 p-2 rounded-lg font-mono text-sm" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
            </div>
            <div class="flex justify-end gap-4 pt-4">
                <button type="button" @click="isModalOpen = false" class="px-4 py-2 rounded-lg" style="color: var(--text-secondary); background-color: var(--hover-bg);">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg font-semibold" style="color: #000; background-color: var(--accent-color);" x-text="modalMode === 'edit' ? 'Save Changes' : 'Create Snippet'"></button>
            </div>
        </form>
    </div>
</div>

<script>
    function getLanguageInfo(language) {
        if (!language) return { color: '#A8B7C5' };
        const lang = language.toLowerCase();
        const map = {
            'javascript': { color: '#F7DF1E' }, 'typescript': { color: '#3178C6' },
            'python': { color: '#3776AB' }, 'html': { color: '#E34F26' },
            'css': { color: '#1572B6' }, 'php': { color: '#777BB4' },
            'csharp': { color: '#239120' }, 'cpp': { color: '#00599C' },
            'java': { color: '#007396' }, 'go': { color: '#00ADD8' },
            'sql': { color: '#4479A1' }, 'vue': { color: '#4FC08D' }
        };
        return map[lang] || { color: '#A8B7C5' };
    }
</script>

</body>
</html>