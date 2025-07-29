<?php
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isLoggedIn()) {
    header("location: /");
    exit;
}

$current_user_id = $_SESSION['id'];
$user_role = '';
$stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    $user_role = $user['role'];
}
$stmt->close();

if (strtolower($user_role) !== 'owner') {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_title' || $action === 'update_title') {
        $name = trim($_POST['title_name'] ?? '');
        $color = trim($_POST['title_color'] ?? '#8B5CF6');
        $icon_source_type = $_POST['icon_source'] ?? 'fa';
        $icon_value = '';

        if ($icon_source_type === 'fa') {
            $icon_value = trim($_POST['title_icon_fa'] ?? '');
        } elseif ($icon_source_type === 'url') {
            $icon_value = trim($_POST['title_icon_url'] ?? '');
        } elseif ($icon_source_type === 'upload' && !empty($_FILES['title_icon_file']['name'])) {
            $upload_dir = 'db/titles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $result = saveUploadedFile($_FILES['title_icon_file'], $upload_dir, "title_icon_".time());
            if (isset($result['success'])) {
                $icon_value = $upload_dir . $result['filename'];
            }
        } else {
            if($action === 'update_title') {
                $icon_value = $_POST['existing_icon'] ?? '';
            }
        }

        if (!empty($name)) {
            if ($action === 'create_title') {
                $stmt_insert = $mysqli->prepare("INSERT INTO titles (name, color, icon) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("sss", $name, $color, $icon_value);
                $stmt_insert->execute();
                $stmt_insert->close();
            } elseif ($action === 'update_title') {
                $title_id = $_POST['title_id'] ?? 0;
                if ($title_id > 0) {
                    $stmt_update = $mysqli->prepare("UPDATE titles SET name = ?, color = ?, icon = ? WHERE id = ?");
                    $stmt_update->bind_param("sssi", $name, $color, $icon_value, $title_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
            header("Location: admin.php?tab=titles");
            exit;
        }
    } elseif ($action === 'delete_title') {
        $title_id = $_POST['title_id'] ?? 0;
        if ($title_id > 0) {
            $stmt_delete = $mysqli->prepare("DELETE FROM titles WHERE id = ?");
            $stmt_delete->bind_param("i", $title_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            header("Location: admin.php?tab=titles");
            exit;
        }
    }
}

$all_users = [];
$sql_all_users = "SELECT id, uuid, username, display_name, role, is_verified FROM users ORDER BY id ASC";
$result_all_users = $mysqli->query($sql_all_users);
if ($result_all_users) {
    $all_users = $result_all_users->fetch_all(MYSQLI_ASSOC);
}

$all_titles = [];
$sql_all_titles = "SELECT id, name, color, icon FROM titles ORDER BY id ASC";
$result_all_titles = $mysqli->query($sql_all_titles);
if ($result_all_titles) {
    $all_titles = $result_all_titles->fetch_all(MYSQLI_ASSOC);
}

function getRoleBadgeClass($role) {
    $roles = [
        'owner' => 'bg-red-500/20 text-red-400 border-red-500/30',
        'admin' => 'bg-orange-500/20 text-orange-400 border-orange-500/30',
        'moderator' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        'user' => 'bg-purple-500/20 text-purple-400 border-purple-500/30'
    ];
    return $roles[strtolower($role)] ?? $roles['user'];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Codify</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --font-sans: 'Inter', sans-serif;
            --font-serif: 'Sora', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }
        body { 
            font-family: var(--font-sans);
            background-color: var(--bg-color);
            color: var(--text-primary);
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
        .table-custom th, .table-custom td { padding: 0.75rem 1.5rem; color: var(--text-primary); }
        .table-custom thead { border-bottom: 1px solid var(--border-color); }
        .table-custom th { color: var(--text-secondary); }
        .table-custom tbody tr { border-bottom: 1px solid var(--border-color); }
        .table-custom tbody tr:hover { background-color: var(--hover-bg, rgba(255,255,255,0.02)); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data='{
        theme: localStorage.getItem("theme") || "theme-glassmorphism",
        setTheme(t) { this.theme = t; localStorage.setItem("theme", t); },
        activeTab: new URLSearchParams(window.location.search).get("tab") || "users"
    }'
    :class="theme"
>
    <div class="flex min-h-screen">
        <aside class="w-64 flex-shrink-0 p-4 flex flex-col" style="background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); backdrop-filter: blur(16px);">
            <h1 class="text-2xl font-bold flex items-center gap-2 mb-8" style="color: var(--accent-color);"><i class="fas fa-crown"></i> Owner Panel</h1>
            <nav class="flex flex-col gap-2">
                <a href="admin.php?tab=users" @click.prevent="activeTab = 'users'; window.history.pushState({}, '', 'admin.php?tab=users')" :class="{ 'bg-hover-bg': activeTab === 'users' }" class="flex items-center gap-3 px-3 py-2 hover:bg-hover-bg rounded-lg transition-colors">
                    <i class="fas fa-users w-5 text-center" :style="{color: activeTab === 'users' ? 'var(--text-primary)' : 'var(--text-secondary)'}"></i>
                    <span style="color: var(--text-primary);">User Management</span>
                </a>
                <a href="admin.php?tab=titles" @click.prevent="activeTab = 'titles'; window.history.pushState({}, '', 'admin.php?tab=titles')" :class="{ 'bg-hover-bg': activeTab === 'titles' }" class="flex items-center gap-3 px-3 py-2 hover:bg-hover-bg rounded-lg transition-colors">
                    <i class="fas fa-tags w-5 text-center" :style="{color: activeTab === 'titles' ? 'var(--text-primary)' : 'var(--text-secondary)'}"></i>
                    <span style="color: var(--text-primary);">Title Management</span>
                </a>
                 <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors" style="color: var(--text-secondary);">
                    <i class="fas fa-trophy w-5 text-center"></i>
                    <span>Achievement Settings</span>
                </a>
            </nav>
            <div class="mt-auto">
                 <div x-data='{
                        themes: [
                            { name: "Glassmorphism", value: "theme-glassmorphism"},
                            { name: "Neumorphism", value: "theme-neumorphism" },
                            { name: "Cyber Hacker", value: "theme-hacker" },
                            { name: "Minimal Dark", value: "theme-minimal" },
                            { name: "Hybrid", value: "theme-hybrid" }
                        ],
                        isOpen: false,
                        currentThemeName() {
                            return this.themes.find(t => t.value === theme).name;
                        }
                    }' class="relative mb-4">
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
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2" style="color: var(--text-secondary);">&larr; Back to Dashboard</a>
            </div>
        </aside>

        <main class="flex-1 p-8" style="background-color: var(--main-bg);">
            <div x-show="activeTab === 'users'" x-cloak x-data="userManagement()">
                <h2 class="text-3xl font-extrabold mb-6" style="color: var(--text-primary);">User Management</h2>
                <div class="rounded-xl border" style="background-color: var(--card-bg); border-color: var(--border-color);">
                    <table class="table-custom w-full text-left">
                        <thead>
                            <tr class="text-sm"><th>ID</th><th>Username</th><th>Role</th><th class="hidden md:table-cell">UUID</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="user in users" :key="user.id">
                                <tr class="text-sm">
                                    <td class="font-mono" style="color: var(--text-secondary);" x-text="user.id"></td>
                                    <td class="font-bold" x-text="user.display_name || user.username"></td>
                                    <td><span class="px-2 py-1 rounded-full text-xs font-semibold border" :class="getRoleBadgeClass(user.role)" x-text="user.role.charAt(0).toUpperCase() + user.role.slice(1)"></span></td>
                                    <td class="hidden md:table-cell font-mono text-xs" style="color: var(--text-secondary);" x-text="user.uuid"></td>
                                    <td>
                                        <template x-if="user.is_verified == 1"><span class="font-bold" style="color: var(--accent-color);">Verified</span></template>
                                        <template x-if="user.is_verified == 0"><span style="color: var(--text-secondary);">Not Verified</span></template>
                                    </td>
                                    <td>
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" :disabled="user.id == <?php echo $current_user_id; ?>" class="disabled:opacity-50 disabled:cursor-not-allowed" style="color: var(--text-secondary);"><i class="fas fa-ellipsis-h"></i></button>
                                            <div x-show="open" @click.away="open = false" x-cloak x-transition class="absolute right-0 mt-2 w-48 rounded-md shadow-lg z-50" style="background-color: var(--modal-bg); border: 1px solid var(--border-color);">
                                                <div class="py-1">
                                                    <span class="block px-4 py-2 text-xs" style="color: var(--text-secondary);">Change Role</span>
                                                    <a href="#" @click.prevent="updateUser(user, 'change_role', 'user'); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);">User</a>
                                                    <a href="#" @click.prevent="updateUser(user, 'change_role', 'moderator'); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);">Moderator</a>
                                                    <a href="#" @click.prevent="updateUser(user, 'change_role', 'admin'); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);">Admin</a>
                                                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                                                    <a href="#" @click.prevent="openAssignModal(user); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);">Assign Titles</a>
                                                    <a href="#" @click.prevent="updateUser(user, 'toggle_verified', !(user.is_verified == 1)); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);" x-text="user.is_verified == 1 ? 'Un-verify' : 'Verify'"></a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                
                <div x-show="isAssignModalOpen" x-cloak class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
                    <div @click.away="closeAssignModal()" class="rounded-xl border p-6 w-full max-w-md" style="background-color: var(--modal-bg); border-color: var(--border-color);">
                        <h3 class="text-lg font-bold mb-4" style="color: var(--text-primary);">Assign Titles for <span x-text="assigningUser.username"></span></h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <template x-for="title in allTitles" :key="title.id">
                                <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-hover-bg cursor-pointer">
                                    <input type="checkbox" :value="title.id" x-model="assignedTitleIds" class="h-4 w-4 rounded bg-transparent border-2 focus:ring-offset-0" style="border-color: var(--border-color); color: var(--accent-color); --tw-ring-color: transparent;">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-2" :style="{ backgroundColor: title.color, color: '#fff' }">
                                        <template x-if="title.icon && (title.icon.startsWith('http') || title.icon.startsWith('db/titles/'))"><img :src="title.icon" class="w-4 h-4 object-contain"></template>
                                        <template x-if="title.icon && !title.icon.startsWith('http') && !title.icon.startsWith('db/titles/')"><i :class="title.icon"></i></template>
                                        <span x-text="title.name"></span>
                                    </span>
                                </label>
                            </template>
                        </div>
                        <div class="flex justify-end gap-4 pt-4 mt-4 border-t" style="border-color: var(--border-color);">
                            <button type="button" @click="closeAssignModal()" class="px-4 py-2 rounded-lg" style="background-color: var(--hover-bg); color: var(--text-primary);">Cancel</button>
                            <button type="button" @click="saveAssignedTitles()" class="px-4 py-2 rounded-lg text-white font-bold" style="background-color: var(--accent-color);">Save Titles</button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'titles'" x-cloak x-data="titleManagement()">
                <h2 class="text-3xl font-extrabold mb-6" style="color: var(--text-primary);">Title Management</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1">
                        <div class="rounded-xl border p-6" style="background-color: var(--card-bg); border-color: var(--border-color);">
                            <h3 class="text-lg font-bold mb-4" style="color: var(--text-primary);">Create New Title</h3>
                            <form action="admin.php?tab=titles" method="POST" class="space-y-4" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create_title">
                                <div><label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Title Name</label><input type="text" name="title_name" required class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                                <div><label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Color</label><input type="color" name="title_color" value="#8B5CF6" class="w-full h-10 bg-transparent border rounded-lg cursor-pointer p-1" style="border-color: var(--border-color);"></div>
                                <div>
                                    <label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Icon Source</label>
                                    <div class="flex bg-black/20 rounded-lg p-1 space-x-1">
                                        <label :class="{'bg-black/40': createIconSource === 'fa'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">FA<input type="radio" name="icon_source" value="fa" x-model="createIconSource" class="hidden"></label>
                                        <label :class="{'bg-black/40': createIconSource === 'url'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">URL<input type="radio" name="icon_source" value="url" x-model="createIconSource" class="hidden"></label>
                                        <label :class="{'bg-black/40': createIconSource === 'upload'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">Upload<input type="radio" name="icon_source" value="upload" x-model="createIconSource" class="hidden"></label>
                                    </div>
                                    <div x-show="createIconSource === 'fa'" class="mt-2"><input type="text" name="title_icon_fa" placeholder="e.g., fas fa-code" class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                                    <div x-show="createIconSource === 'url'" class="mt-2"><input type="url" name="title_icon_url" placeholder="https://.../icon.png" class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                                    <div x-show="createIconSource === 'upload'" class="mt-2"><input type="file" name="title_icon_file" accept="image/*" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold" style="color: var(--text-secondary);" :style="{'--accent-color': 'var(--accent-color)'}"></div>
                                </div>
                                <button type="submit" class="w-full text-white font-bold py-2 px-4 rounded-lg hover:opacity-90 transition-opacity" style="background-color: var(--accent-color);">Create Title</button>
                            </form>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="rounded-xl border" style="background-color: var(--card-bg); border-color: var(--border-color);">
                            <table class="table-custom w-full text-left">
                                <thead><tr class="text-sm"><th>Preview</th><th>Name</th><th>Icon Source</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <template x-for="title in titles" :key="title.id">
                                        <tr class="text-sm">
                                            <td><span class="px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-2" :style="{ backgroundColor: title.color, color: '#fff' }">
                                                <template x-if="title.icon && (title.icon.startsWith('http') || title.icon.startsWith('db/titles/'))"><img :src="title.icon" class="w-4 h-4 object-contain"></template>
                                                <template x-if="title.icon && !title.icon.startsWith('http') && !title.icon.startsWith('db/titles/')"><i :class="title.icon"></i></template>
                                                <span x-text="title.name"></span></span>
                                            </td>
                                            <td class="font-bold" x-text="title.name"></td>
                                            <td class="font-mono text-xs truncate max-w-xs" style="color: var(--text-secondary);" x-text="title.icon"></td>
                                            <td>
                                                <div x-data="{ open: false }" class="relative">
                                                    <button @click="open = !open" style="color: var(--text-secondary);"><i class="fas fa-ellipsis-h"></i></button>
                                                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-32 rounded-md shadow-lg z-50" style="background-color: var(--modal-bg); border: 1px solid var(--border-color);">
                                                        <a href="#" @click.prevent="openEditModal(title); open = false" class="block px-4 py-2 text-sm" style="color: var(--text-primary);">Edit</a>
                                                        <form :action="'admin.php?tab=titles'" method="POST" onsubmit="return confirm('Are you sure you want to delete this title?');">
                                                            <input type="hidden" name="action" value="delete_title">
                                                            <input type="hidden" name="title_id" :value="title.id">
                                                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-400">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div x-show="isEditModalOpen" x-cloak class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
                    <div @click.away="closeEditModal()" class="rounded-xl border p-6 w-full max-w-md" style="background-color: var(--modal-bg); border-color: var(--border-color);">
                        <h3 class="text-lg font-bold mb-4" style="color: var(--text-primary);">Edit Title</h3>
                        <form action="admin.php?tab=titles" method="POST" class="space-y-4" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_title">
                            <input type="hidden" name="title_id" :value="editingTitle.id">
                            <input type="hidden" name="existing_icon" :value="editingTitle.icon">
                            <div><label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Title Name</label><input type="text" name="title_name" x-model="editingTitle.name" required class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                            <div><label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Color</label><input type="color" name="title_color" x-model="editingTitle.color" class="w-full h-10 bg-transparent border rounded-lg p-1" style="border-color: var(--border-color);"></div>
                            <div>
                                <label class="text-sm font-medium block mb-2" style="color: var(--text-secondary);">Icon Source</label>
                                <div class="flex bg-black/20 rounded-lg p-1 space-x-1">
                                    <label :class="{'bg-black/40': editIconSource === 'fa'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">FA<input type="radio" name="icon_source" value="fa" x-model="editIconSource" class="hidden"></label>
                                    <label :class="{'bg-black/40': editIconSource === 'url'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">URL<input type="radio" name="icon_source" value="url" x-model="editIconSource" class="hidden"></label>
                                    <label :class="{'bg-black/40': editIconSource === 'upload'}" class="flex-1 text-center text-sm py-1 rounded-md cursor-pointer">Upload<input type="radio" name="icon_source" value="upload" x-model="editIconSource" class="hidden"></label>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Current: <span x-text="editingTitle.icon || 'None'"></span></p>
                                <div x-show="editIconSource === 'fa'" class="mt-2"><input type="text" name="title_icon_fa" :placeholder="editingTitle.icon" class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                                <div x-show="editIconSource === 'url'" class="mt-2"><input type="url" name="title_icon_url" :placeholder="editingTitle.icon" class="w-full bg-transparent border rounded-lg px-3 py-2" style="border-color: var(--border-color); color: var(--text-primary);"></div>
                                <div x-show="editIconSource === 'upload'" class="mt-2"><input type="file" name="title_icon_file" class="w-full text-sm" style="color: var(--text-secondary);"></div>
                            </div>
                            <div class="flex justify-end gap-4 pt-4">
                                <button type="button" @click="closeEditModal()" class="px-4 py-2 rounded-lg" style="background-color: var(--hover-bg); color: var(--text-primary);">Cancel</button>
                                <button type="submit" class="px-4 py-2 rounded-lg text-white font-bold" style="background-color: var(--accent-color);">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script>
    function userManagement() {
        return {
            users: <?php echo json_encode($all_users); ?>,
            isAssignModalOpen: false,
            assigningUser: {},
            assignedTitleIds: [],
            allTitles: <?php echo json_encode($all_titles); ?>,
            getRoleClass(role) {
                const roles = { 'owner': 'bg-red-500/20 text-red-400 border-red-500/30', 'admin': 'bg-orange-500/20 text-orange-400 border-orange-500/30', 'moderator': 'bg-blue-500/20 text-blue-400 border-blue-500/30', 'user': 'bg-purple-500/20 text-purple-400 border-purple-500/30' };
                return roles[role.toLowerCase()] || roles['user'];
            },
            async updateUser(user, action, value) {
                if (user.id == <?php echo $current_user_id; ?> && action === 'change_role') {
                    alert("You cannot change your own role."); return;
                }
                try {
                    const response = await fetch('api/handle_user_management.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: user.id, action: action, value: value }) });
                    const result = await response.json();
                    if (result.status === 'success') {
                        let userIndex = this.users.findIndex(u => u.id == user.id);
                        if (userIndex !== -1) {
                            if (action === 'change_role') this.users[userIndex].role = value;
                            if (action === 'toggle_verified') this.users[userIndex].is_verified = value ? 1 : 0;
                        }
                    } else { alert('Error: ' + result.message); }
                } catch (error) { console.error('Failed to update user:', error); alert('An error occurred.'); }
            },
            async openAssignModal(user) {
                this.assigningUser = user;
                try {
                    const response = await fetch(`api/handle_user_titles.php?user_id=${user.id}`);
                    const result = await response.json();
                    if (result.status === 'success') {
                        this.assignedTitleIds = result.assigned_title_ids.map(String);
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) { console.error(e); }
                this.isAssignModalOpen = true;
            },
            closeAssignModal() {
                this.isAssignModalOpen = false;
            },
            async saveAssignedTitles() {
                try {
                    const response = await fetch('api/handle_user_titles.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            user_id: this.assigningUser.id,
                            title_ids: this.assignedTitleIds.map(Number)
                        })
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        this.closeAssignModal();
                        alert('Titles updated successfully!');
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) { console.error(e); }
            }
        }
    }
    function titleManagement() {
        return {
            titles: <?php echo json_encode($all_titles); ?>,
            openDropdownId: null,
            isEditModalOpen: false,
            editingTitle: { id: null, name: '', color: '#8B5CF6', icon: '' },
            createIconSource: 'fa',
            editIconSource: 'fa',
            openEditModal(title) {
                this.editingTitle = JSON.parse(JSON.stringify(title));
                const icon = this.editingTitle.icon;
                if (icon && (icon.startsWith('http') || icon.startsWith('db/titles/'))) { this.editIconSource = 'url'; } 
                else if (icon) { this.editIconSource = 'fa'; } 
                else { this.editIconSource = 'fa'; }
                this.isEditModalOpen = true;
            },
            closeEditModal() { this.isEditModalOpen = false; }
        }
    }
</script>
</body>
</html>