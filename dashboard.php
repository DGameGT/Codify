<?php
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isLoggedIn()) {
    header("location: /login");
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

function getLanguageIconClass($language) {
    $language = strtolower($language);
    $map = ['csharp' => 'csharp', 'cpp' => 'cplusplus', 'html' => 'html5', 'css' => 'css3', 'dockerfile' => 'docker', 'sql' => 'mysql', 'vue' => 'vuejs', 'angularjs' => 'angularjs'];
    $iconName = $map[$language] ?? $language;
    return "devicon-{$iconName}-plain colored";
}

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_selected_ids'])) {
        $ids_to_delete_str = $_POST['delete_selected_ids'];
        $ids_to_delete = array_filter(explode(',', $ids_to_delete_str));

        if (!empty($ids_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
            $types = str_repeat('s', count($ids_to_delete)) . 'i';
            $params = array_merge($ids_to_delete, [$user_id]);
            
            $sql_delete = "DELETE FROM codes WHERE share_id IN ($placeholders) AND user_id = ?";
            if ($stmt_delete = $mysqli->prepare($sql_delete)) {
                $stmt_delete->bind_param($types, ...$params);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
        }
    } elseif (isset($_POST['code_content'])) {
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
$sql_codes = "SELECT share_id, title, language, created_at FROM codes WHERE user_id = ? ORDER BY created_at DESC";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { 'primary': 'hsl(210, 40%, 98%)', 'background': 'hsl(222, 47%, 11%)', 'card': 'hsl(222, 47%, 14%)', 'card-foreground': 'hsl(210, 40%, 98%)', 'accent': { DEFAULT: 'hsl(200, 98%, 50%)', 'foreground': 'hsl(222, 47%, 11%)', }, 'border': 'hsl(222, 47%, 20%)', 'input': 'hsl(222, 47%, 18%)', 'muted': { DEFAULT: 'hsl(222, 47%, 25%)', 'foreground': 'hsl(215, 20%, 65%)', }, 'destructive': { DEFAULT: 'hsl(0, 72%, 51%)', 'foreground': 'hsl(210, 40%, 98%)' } }, fontFamily: { sans: ['Inter', 'sans-serif'], }, keyframes: { 'fade-in': { '0%': { opacity: '0' }, '100%': { opacity: '1' } }, 'scale-in': { '0%': { opacity: '0', transform: 'scale(0.95) translateY(10px)' }, '100%': { opacity: '1', transform: 'scale(1) translateY(0)' } } }, animation: { 'fade-in': 'fade-in 0.5s ease-out forwards', 'scale-in': 'scale-in 0.3s ease-out forwards', } }, }, };
    </script>
    <style>body { background-color: hsl(222, 47%, 11%); color: hsl(210, 40%, 98%); font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }</style>
</head>
<body x-data="{ isSidebarOpen: false, isProfileMenuOpen: false, isModalOpen: false, modalMode: 'new', currentSnippet: {} }">

<div class="flex min-h-screen bg-background">
    <div @click="isSidebarOpen = false" class="fixed inset-0 bg-black/60 z-30 md:hidden" x-show="isSidebarOpen" x-transition:enter="transition-opacity ease-in-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-in-out duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-card/70 backdrop-blur-lg border-r border-border flex-col transition-transform duration-300 -translate-x-full md:translate-x-0 flex" :class="{ 'translate-x-0': isSidebarOpen }">
        <div class="p-4 border-b border-border flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2.5 text-xl font-bold">
                <span class="p-2 bg-accent rounded-lg text-accent-foreground flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                </span>
                <span>CodeShare</span>
            </a>
            <button @click="isSidebarOpen = false" class="p-2 text-muted-foreground md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <nav class="flex-1 flex flex-col gap-2 p-4">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 text-primary bg-accent/20 border border-accent/30 rounded-lg transition-colors hover:bg-accent/30"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg><span>Dashboard</span></a>
             <a href="docs.php" class="flex items-center gap-3 px-3 py-2 text-muted-foreground hover:text-primary transition-colors rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        <span>Docs</span>
    </a>
            <a href="leaderboard.php" class="flex items-center gap-3 px-3 py-2 text-muted-foreground hover:text-primary transition-colors rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17h4v-2.34"/><path d="M12 9v5.66"/><path d="M8 12h8"/><path d="M12 18h.01"/></svg><span>Leaderboard</span></a>
        </nav>
        <div class="mt-auto p-2 border-t border-border relative">
            <div @click="isProfileMenuOpen = !isProfileMenuOpen" class="group flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 cursor-pointer transition-colors">
                <img src="db/profile/<?php echo htmlspecialchars($profile_picture); ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover transition-transform group-hover:scale-105">
                <div class="flex-1 min-w-0"><p class="font-bold truncate"><?php echo htmlspecialchars($username); ?></p><p class="text-xs text-green-400">Online</p></div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-muted-foreground transition-transform" :class="{ 'rotate-180': isProfileMenuOpen }" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </div>
            <div x-show="isProfileMenuOpen" @click.away="isProfileMenuOpen = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute bottom-full left-2 right-2 mb-2 w-auto bg-card border border-border rounded-lg shadow-xl" style="display: none;">
                <a href="profile.php" class="flex items-center gap-3 px-3 py-2 text-sm text-muted-foreground hover:bg-muted/30 hover:text-primary rounded-t-lg transition-colors"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
                <a href="logout.php" class="flex items-center gap-3 px-3 py-2 text-sm text-destructive hover:bg-destructive/10 rounded-b-lg transition-colors"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg><span>Sign Out</span></a>
            </div>
        </div>
    </aside>

    <div class="flex-1 md:ml-64 flex flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-card/70 backdrop-blur-xl px-4 md:px-6">
            <button @click="isSidebarOpen = true" class="md:hidden p-2 text-muted-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
            </button>
            <div class="flex-1"></div>
            <div class="flex items-center gap-4">
                <button @click="isModalOpen = true; modalMode = 'new'; $nextTick(() => document.getElementById('snippet-form').reset());" class="group inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-bold ring-offset-background transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 bg-accent text-accent-foreground hover:bg-accent/90 h-10 px-5 shadow-lg shadow-accent/20 hover:shadow-accent/30 hover:-translate-y-0.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-4 h-4 mr-2 transition-transform group-hover:rotate-90"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    New Snippet
                </button>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-6 lg:p-8 animate-fade-in" x-data="snippetManager()">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                <h1 class="text-3xl font-extrabold tracking-tight">My Snippets</h1>
                <div class="flex items-center gap-2">
                    <div class="relative w-full max-w-xs">
                         <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input x-model="searchQuery" type="text" placeholder="Search snippets..." class="w-full h-10 pl-10 pr-4 rounded-md border border-border bg-input focus:ring-2 focus:ring-accent focus:border-accent outline-none transition-all">
                    </div>
                </div>
            </div>

            <form id="bulk-delete-form" method="POST" action="/dashboard" @submit.prevent="submitBulkDelete">
                <input type="hidden" name="delete_selected_ids" x-model="selectedSnippets.join(',')">
                <div class="rounded-xl border border-border bg-card/80">
                    <div class="p-4 border-b border-border flex items-center justify-between" x-show="selectedSnippets.length > 0" x-transition>
                        <span class="text-sm font-medium text-muted-foreground" x-text="`${selectedSnippets.length} selected`"></span>
                        <button type="submit" class="inline-flex items-center justify-center rounded-md text-sm font-bold bg-destructive text-destructive-foreground h-9 px-4 hover:bg-destructive/90 transition-colors">
                            <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Delete Selected
                        </button>
                    </div>
                    <?php if (empty($codes_list)): ?>
                    <div class="text-center p-16 border-2 border-dashed border-border rounded-lg m-4">
                        <svg class="mx-auto h-12 w-12 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 12" /></svg>
                        <h3 class="mt-4 text-lg font-semibold">No Snippets Found</h3>
                        <p class="text-muted-foreground mt-1">Click 'New Snippet' to create your first one.</p>
                    </div>
                    <?php else: ?>
                    <div class="w-full overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border/50">
                                <tr class="text-left text-muted-foreground">
                                    <th class="p-4 w-12"><input type="checkbox" @change="toggleSelectAll($event.target.checked)" :checked="areAllSelected()" class="w-4 h-4 rounded border-border bg-input text-accent focus:ring-accent"></th>
                                    <th class="p-4 font-semibold">Title</th>
                                    <th class="p-4 font-semibold">Language</th>
                                    <th class="p-4 font-semibold hidden md:table-cell">Uploaded</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="code in filteredCodes" :key="code.share_id">
                                    <tr class="border-b border-border/50 transition-all hover:bg-muted/30">
                                        <td class="p-4"><input type="checkbox" :value="code.share_id" x-model="selectedSnippets" class="w-4 h-4 rounded border-border bg-input text-accent focus:ring-accent"></td>
                                        <td class="p-4 font-bold text-primary" x-text="code.title"></td>
                                        <td class="p-4">
                                            <span class="inline-flex items-center gap-2 rounded-lg bg-muted/50 px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                                                <i :class="getLanguageIconClass(code.language)" class="text-lg"></i>
                                                <span x-text="code.language.charAt(0).toUpperCase() + code.language.slice(1)"></span>
                                            </span>
                                        </td>
                                        <td class="p-4 text-muted-foreground hidden md:table-cell" x-text="formatDate(code.created_at)"></td>
                                        <td class="p-4 text-right">
                                            <div class="flex justify-end items-center space-x-2">
                                                <button type="button" @click="openEditModal(code.share_id)" class="inline-flex items-center justify-center rounded-md text-xs font-bold h-8 px-3 transition-colors hover:bg-muted hover:text-primary">Edit</button>
                                                <a :href="'view.php?id=' + code.share_id" class="inline-flex items-center justify-center rounded-md text-xs font-bold h-8 px-3 transition-colors text-primary hover:bg-accent hover:text-accent-foreground">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </main>
    </div>
</div>

<div x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm" style="display: none;">
    <div @click.away="isModalOpen = false" x-show="isModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative w-full max-w-2xl bg-card border border-border rounded-xl shadow-2xl m-4">
        <form id="snippet-form" method="post" action="dashboard.php" class="p-6 space-y-4">
            <input type="hidden" name="edit_share_id" id="edit-share-id-input" x-model="currentSnippet.share_id">
            <div class="text-center mb-4">
                <h2 class="text-2xl font-bold" x-text="modalMode === 'edit' ? 'Edit Snippet' : 'Share a New Snippet'"></h2>
                <p class="text-sm text-muted-foreground" x-text="modalMode === 'edit' ? 'Update the details of your snippet.' : 'Fill the details and share your masterpiece.'"></p>
            </div>
            <div>
                <label for="title" class="block text-sm font-medium mb-2">Title</label>
                <input type="text" id="title-input" name="title" class="flex h-10 w-full rounded-md border border-input bg-background/50 px-3 py-2 text-sm focus:ring-2 focus:ring-accent outline-none" placeholder="e.g., Database Connection" x-model="currentSnippet.title" required>
            </div>
            <div>
                <label for="language" class="block text-sm font-medium mb-2">Language</label>
                <select id="language-input" name="language" class="flex h-10 w-full rounded-md border border-input bg-background/50 px-3 py-2 text-sm focus:ring-2 focus:ring-accent outline-none" x-model="currentSnippet.language">
                    <?php foreach (getSupportedLanguages() as $value => $name) { echo "<option value=\"$value\">$name</option>"; } ?>
                </select>
            </div>
            <div>
                <label for="code_content" class="block text-sm font-medium mb-2">Code</label>
                <textarea id="code-content-input" name="code_content" class="flex min-h-[180px] w-full rounded-md border border-input bg-background/50 px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-accent outline-none" placeholder="Paste your code here..." x-model="currentSnippet.code_content" required></textarea>
            </div>
            <div class="flex justify-end pt-4 gap-3">
                <button type="button" @click="isModalOpen = false" class="h-10 px-4 py-2 rounded-md text-sm font-bold hover:bg-muted transition-colors">Cancel</button>
                <button type="submit" class="inline-flex items-center justify-center rounded-md text-sm font-bold bg-accent text-accent-foreground h-10 px-6 hover:bg-accent/90 transition-transform hover:scale-105" x-text="modalMode === 'edit' ? 'Save Changes' : 'Share Now'"></button>
            </div>
        </form>
    </div>
</div>

<script>
    function snippetManager() {
        const allCodes = <?php echo json_encode($codes_list); ?>;
        return {
            searchQuery: '',
            selectedSnippets: [],
            codes: allCodes,
            get filteredCodes() {
                if (this.searchQuery === '') {
                    return this.codes;
                }
                return this.codes.filter(code => {
                    return code.title.toLowerCase().includes(this.searchQuery.toLowerCase());
                });
            },
            areAllSelected() {
                return this.filteredCodes.length > 0 && this.selectedSnippets.length === this.filteredCodes.length;
            },
            toggleSelectAll(checked) {
                if (checked) {
                    this.selectedSnippets = this.filteredCodes.map(c => c.share_id);
                } else {
                    this.selectedSnippets = [];
                }
            },
            submitBulkDelete() {
                if (this.selectedSnippets.length === 0) {
                    alert('Please select at least one snippet to delete.');
                    return;
                }
                if (confirm(`Are you sure you want to delete ${this.selectedSnippets.length} selected snippet(s)? This action cannot be undone.`)) {
                    document.getElementById('bulk-delete-form').submit();
                }
            },
            async openEditModal(shareId) {
                try {
                    const response = await fetch(`get_code_details.php?id=${shareId}`);
                    if (!response.ok) throw new Error('Network response error.');
                    const data = await response.json();
                    if(data.error) { alert(data.error); return; }
                    
                    this.currentSnippet = {
                        share_id: shareId,
                        title: data.title,
                        language: data.language,
                        code_content: data.code_content
                    };
                    this.modalMode = 'edit';
                    this.isModalOpen = true;
                } catch (error) {
                    console.error('Failed to fetch snippet:', error);
                    alert('Failed to load snippet data.');
                }
            },
            getLanguageIconClass(language) {
                const lang = language.toLowerCase();
                const map = {'csharp':'csharp','cpp':'cplusplus','html':'html5','css':'css3','dockerfile':'docker','sql':'mysql','vue':'vuejs','angularjs':'angularjs'};
                const iconName = map[lang] || lang;
                return `devicon-${iconName}-plain colored`;
            },
            formatDate(dateString) {
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                return new Date(dateString).toLocaleDateString('en-US', options);
            }
        };
    }
</script>
</body>
</html>