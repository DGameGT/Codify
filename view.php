<?php
// session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

$snippet = null;
$share_id = $_GET['id'] ?? '';
$is_logged_in = isLoggedIn();
$current_user_id = $_SESSION['id'] ?? null;

if (empty($share_id)) {
    http_response_code(404);
    die("Snippet not found.");
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
    $sql = "SELECT c.id, c.title, c.code_content, c.language, c.created_at, c.views, u.id as owner_user_id, u.username, u.profile_picture, u.is_verified
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
    <title><?php echo $snippet ? htmlspecialchars($snippet['title']) : 'Snippet Not Found'; ?> - Codify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Inter', sans-serif; --font-mono: 'JetBrains Mono', monospace; --radius: 0.75rem; --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); --bg-primary: #0A0A0A; --bg-secondary: #171717; --border-color: #27272a; --text-primary: #f4f4f5; --text-secondary: #a1a1aa; --accent-primary: #3882F6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background-color: var(--bg-primary); color: var(--text-primary); }
        .container { max-width: 1000px; margin: 1rem auto; padding: 0 2rem 4rem; }
        .page-header { padding: 1.5rem 2rem; max-width: 1000px; margin: 0 auto; }
        .logo { font-size: 1.5rem; font-weight: 800; text-decoration: none; color: var(--text-primary); }
        .logo span { color: var(--accent-primary); }
        .snippet-container, .error-container, .comments-section { background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .snippet-container { overflow: hidden; }
        .snippet-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
        .header-info h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.75rem; }
        .meta-info, .user-info, .stats-info { display: flex; align-items: center; gap: 0.75rem; }
        .user-info { color: var(--text-primary); text-decoration: none; }
        .user-info img { width: 32px; height: 32px; border-radius: 50%; }
        .header-actions, .comment-actions { display: flex; gap: 0.5rem; }
        .action-btn { background: none; border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 0.75rem; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; text-decoration: none; transition: var(--transition); }
        .action-btn:hover { background-color: #2a2a2a; color: var(--text-primary); border-color: #3f3f46; }
        .code-view { position: relative; }
        .code-view pre { margin: 0; }
        .code-view code.hljs { font-family: var(--font-mono); padding: 2rem !important; padding-top: 3.5rem !important; background: none; }
        #copy-code-btn { position: absolute; top: 1rem; right: 1rem; }
        .error-container { padding: 4rem 2rem; text-align: center; }
        .comments-section { margin-top: 2rem; }
        .comments-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); }
        .comments-header h2 { font-size: 1.5rem; font-weight: 600; }
        .comment-list { padding: 1rem 2rem 2rem; }
        .comment-thread { margin-top: 1.5rem; }
        .comment { display: flex; gap: 1rem; position: relative; }
        .replies { margin-left: 3rem; margin-top: 1rem; border-left: 2px solid var(--border-color); padding-left: 1.5rem; }
        .comment-avatar img { width: 40px; height: 40px; border-radius: 50%; }
        .comment-body { flex-grow: 1; }
        .comment-author { font-weight: 600; }
        .comment-time, .edited-tag { font-size: 0.8rem; color: var(--text-secondary); margin-left: 0.5rem; }
        .comment-text { margin-top: 0.25rem; line-height: 1.6; color: var(--text-secondary); word-break: break-word; }
        .comment-actions { margin-top: 0.75rem; font-size: 0.8rem; }
        .comment-action-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.25rem 0.5rem; font-weight: 500; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.35rem; border-radius: 4px; }
        .comment-action-btn:hover { color: var(--text-primary); background-color: #2a2a2a; }
        .like-btn.liked { color: #ef4444; }
        .comment-form textarea, .edit-textarea { width: 100%; background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.75rem; color: var(--text-primary); font-size: 1rem; resize: vertical; min-height: 80px; font-family: var(--font-sans); }
        .comment-form-container { padding: 1.5rem 2rem; border-top: 1px solid var(--border-color); background-color: #1a1a1a; }
        .comment-form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
        .comment-form button { background-color: var(--accent-primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: var(--transition); }
        .comment-form button[type="button"] { background-color: #333; }
        .reply-form, .edit-form { margin-top: 1rem; }
        .not-logged-in { padding: 2rem; text-align: center; color: var(--text-secondary); }
        .not-logged-in a { color: var(--accent-primary); }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="index.php" class="logo">Codify<span>.</span></a>
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
                                <span><?php echo htmlspecialchars($snippet['username']); ?></span>
                            </a>
                            <div class="stats-info"><span>•</span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"></path></svg> <span><?php echo number_format($snippet['views']); ?> views</span></div>
                        </div>
                    </div>
                </div>
                <div class="code-view">
                    <pre><code id="code-block" class="language-<?php echo htmlspecialchars($snippet['language']); ?>"><?php echo htmlspecialchars($snippet['code_content']); ?></code></pre>
                </div>
            </div>

            <section class="comments-section"
                data-share-id="<?php echo htmlspecialchars($share_id); ?>"
                data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>"
                data-current-user-id="<?php echo htmlspecialchars($current_user_id ?? ''); ?>"
                data-snippet-owner-id="<?php echo htmlspecialchars($snippet['owner_user_id'] ?? ''); ?>"
            >
                <div class="comments-header"><h2 id="comment-count">Comments</h2></div>
                <div id="comment-list" class="comment-list"><p style="color: var(--text-secondary);">Loading comments...</p></div>
                <div class="comment-form-container">
                    <?php if ($is_logged_in): ?>
                        <form id="comment-form" class="comment-form">
                            <textarea id="comment-text-input" placeholder="Write a comment..." required></textarea>
                            <div class="comment-form-actions"><button type="submit" id="submit-comment-btn">Post Comment</button></div>
                        </form>
                    <?php else: ?>
                        <div class="not-logged-in"><p><a href="index.php">Log in</a> to join the conversation.</p></div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="error-container"><h1>404 - Snippet Not Found</h1></div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        hljs.highlightAll();
        
        const commentsSection = document.querySelector('.comments-section');
        if (!commentsSection) return;

        const shareId = commentsSection.dataset.shareId;
        const isLoggedIn = commentsSection.dataset.isLoggedIn === 'true';
        const currentUserId = commentsSection.dataset.currentUserId ? parseInt(commentsSection.dataset.currentUserId, 10) : null;
        const snippetOwnerId = commentsSection.dataset.snippetOwnerId ? parseInt(commentsSection.dataset.snippetOwnerId, 10) : null;
        
        const commentList = document.getElementById('comment-list');
        const mainCommentForm = document.getElementById('comment-form');
        const commentCountHeader = document.getElementById('comment-count');

        const formatDate = (dateString) => new Date(dateString).toLocaleString();
        const sanitizeHTML = (str) => {
            const temp = document.createElement('div');
            temp.textContent = str;
            return temp.innerHTML;
        };

        const createCommentElement = (comment) => {
            const isCommentOwner = isLoggedIn && currentUserId === comment.user_id;
            const isSnippetOwner = isLoggedIn && currentUserId === snippetOwnerId;
            const canDelete = isCommentOwner || isSnippetOwner;
            const canEdit = isCommentOwner;

            const profilePic = comment.profile_picture ? `db/profile/${sanitizeHTML(comment.profile_picture)}` : 'db/profile/default.png';
            const displayName = sanitizeHTML(comment.display_name || comment.username);
            
            const threadContainer = document.createElement('div');
            threadContainer.className = 'comment-thread';
            threadContainer.id = `comment-thread-${comment.id}`;

            threadContainer.innerHTML = `
                <div class="comment" id="comment-${comment.id}" data-user-id="${comment.user_id}">
                    <div class="comment-avatar">
                        <a href="profile.php?user=${encodeURIComponent(comment.username)}">
                            <img src="${profilePic}" alt="${displayName}">
                        </a>
                    </div>
                    <div class="comment-body">
                        <p>
                            <a href="profile.php?user=${encodeURIComponent(comment.username)}" style="color: var(--text-primary); text-decoration: none;">
                                <strong class="comment-author">${displayName}</strong>
                            </a>
                            <span class="comment-time">${formatDate(comment.created_at)}</span>
                            ${comment.is_edited ? '<span class="edited-tag">(edited)</span>' : ''}
                        </p>
                        <div class="comment-text" id="comment-text-${comment.id}">${sanitizeHTML(comment.comment_text).replace(/\n/g, '<br>')}</div>
                        <div class="comment-actions">
                            <button class="comment-action-btn like-btn" data-comment-id="${comment.id}">
                                <i class="fas fa-heart"></i> <span class="like-count">${comment.likes_count}</span>
                            </button>
                            ${isLoggedIn ? `<button class="comment-action-btn" data-action="reply" data-comment-id="${comment.id}">Reply</button>` : ''}
                            ${canEdit ? `<button class="comment-action-btn" data-action="edit" data-comment-id="${comment.id}">Edit</button>` : ''}
                            ${canDelete ? `<button class="comment-action-btn" data-action="delete" data-comment-id="${comment.id}" style="color: #ef4444;">Delete</button>` : ''}
                        </div>
                        <div class="reply-form" id="reply-form-${comment.id}"></div>
                    </div>
                </div>
                <div class="replies" id="replies-${comment.id}"></div>
            `;
            
            if (comment.replies && comment.replies.length > 0) {
                const repliesContainer = threadContainer.querySelector(`#replies-${comment.id}`);
                comment.replies.forEach(reply => {
                    repliesContainer.appendChild(createCommentElement(reply));
                });
            }
            return threadContainer;
        };
        
        let totalComments = 0;
        const renderComments = (comments, container) => {
            comments.forEach(comment => {
                totalComments++;
                container.appendChild(createCommentElement(comment));
                if (comment.replies && comment.replies.length > 0) {
                    totalComments += comment.replies.length;
                }
            });
        };

        const fetchComments = async () => {
            try {
                const response = await fetch(`api/handle_comments.php?code_id=${shareId}`);
                const data = await response.json();
                commentList.innerHTML = '';
                totalComments = 0;
                if (data.status === 'success' && data.comments.length > 0) {
                    renderComments(data.comments, commentList);
                } else {
                    commentList.innerHTML = '<p style="color: var(--text-secondary);">No comments yet. Be the first to comment!</p>';
                }
                commentCountHeader.textContent = `Comments (${totalComments})`;
            } catch (error) {
                console.error('Error fetching comments:', error);
                commentList.innerHTML = '<p style="color: red;">Could not load comments.</p>';
            }
        };

        const handleFormSubmit = async (form, isReply = false) => {
            const textArea = form.querySelector('textarea');
            const submitBtn = form.querySelector('button[type="submit"]');
            const commentText = textArea.value.trim();
            const parentId = isReply ? form.dataset.parentId : null;
            if (!commentText) return;

            submitBtn.disabled = true;
            submitBtn.textContent = '...';

            try {
                const response = await fetch(`api/handle_comments.php?code_id=${shareId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ comment_text: commentText, parent_id: parentId })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    const newCommentElement = createCommentElement(data.comment);
                    if (isReply) {
                        document.getElementById(`replies-${parentId}`).appendChild(newCommentElement);
                        form.remove();
                    } else {
                        if (commentList.querySelector('p')) commentList.innerHTML = '';
                        commentList.appendChild(newCommentElement);
                        textArea.value = '';
                    }
                    totalComments++;
                    commentCountHeader.textContent = `Comments (${totalComments})`;
                } else {
                    alert(`Error: ${data.message}`);
                }
            } catch (error) {
                console.error('Error posting comment:', error);
                alert('An error occurred.');
            } finally {
                if (!isReply) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Comment';
                }
            }
        };
        
        if (mainCommentForm) mainCommentForm.addEventListener('submit', (e) => { e.preventDefault(); handleFormSubmit(mainCommentForm); });
        
        commentList.addEventListener('click', async (e) => {
            const target = e.target.closest('.comment-action-btn');
            if (!target) return;
            
            const action = target.dataset.action;
            const commentId = target.dataset.commentId;

            if (action === 'reply') {
                const replyContainer = document.getElementById(`reply-form-${commentId}`);
                if (replyContainer.innerHTML !== '') {
                    replyContainer.innerHTML = '';
                    return;
                }
                const replyForm = document.createElement('form');
                replyForm.className = 'comment-form reply-form';
                replyForm.dataset.parentId = commentId;
                replyForm.innerHTML = `
                    <textarea placeholder="Write a reply..." required></textarea>
                    <div class="comment-form-actions">
                        <button type="button" class="cancel-reply">Cancel</button>
                        <button type="submit">Reply</button>
                    </div>
                `;
                replyContainer.appendChild(replyForm);
                replyForm.querySelector('textarea').focus();
                replyForm.addEventListener('submit', (ev) => { ev.preventDefault(); handleFormSubmit(replyForm, true); });
                replyForm.querySelector('.cancel-reply').addEventListener('click', () => replyContainer.innerHTML = '');
            }

            if (action === 'delete') {
                if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                    const response = await fetch(`api/handle_comments.php?code_id=${shareId}&comment_id=${commentId}`, { method: 'DELETE' });
                    const data = await response.json();
                    if (data.status === 'success') {
                        document.getElementById(`comment-thread-${commentId}`).remove();
                        fetchComments(); // Recalculate total comments
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                }
            }

            if (action === 'edit') {
                const commentTextEl = document.getElementById(`comment-text-${commentId}`);
                const originalText = commentTextEl.innerText;
                commentTextEl.innerHTML = `
                    <form class="edit-form comment-form">
                        <textarea class="edit-textarea" required>${originalText}</textarea>
                        <div class="comment-form-actions">
                            <button type="button" class="cancel-edit">Cancel</button>
                            <button type="submit">Save Changes</button>
                        </div>
                    </form>
                `;
                const editForm = commentTextEl.querySelector('form');
                const editTextArea = editForm.querySelector('textarea');
                editTextArea.style.height = editTextArea.scrollHeight + 'px';
                editTextArea.focus();
                
                editForm.querySelector('.cancel-edit').addEventListener('click', () => commentTextEl.innerHTML = sanitizeHTML(originalText).replace(/\n/g, '<br>'));
                editForm.addEventListener('submit', async (ev) => {
                    ev.preventDefault();
                    const newText = editTextArea.value.trim();
                    if(newText && newText !== originalText) {
                        const response = await fetch(`api/handle_comments.php?code_id=${shareId}&comment_id=${commentId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ comment_text: newText })
                        });
                        const data = await response.json();
                        if(data.status === 'success') {
                            commentTextEl.innerHTML = sanitizeHTML(newText).replace(/\n/g, '<br>');
                            const timeEl = document.querySelector(`#comment-${commentId} .comment-time`);
                            if (!timeEl.nextElementSibling || !timeEl.nextElementSibling.classList.contains('edited-tag')) {
                                timeEl.insertAdjacentHTML('afterend', ' <span class="edited-tag">(edited)</span>');
                            }
                        } else {
                            alert(data.message);
                            commentTextEl.innerHTML = sanitizeHTML(originalText).replace(/\n/g, '<br>');
                        }
                    } else {
                         commentTextEl.innerHTML = sanitizeHTML(originalText).replace(/\n/g, '<br>');
                    }
                });
            }
            
            if (target.classList.contains('like-btn')) {
                if (!isLoggedIn) { alert('You must be logged in to like comments.'); return; }
                const likeCountSpan = target.querySelector('.like-count');
                let currentLikes = parseInt(likeCountSpan.textContent, 10);
                
                const isLiked = target.classList.contains('liked');
                likeCountSpan.textContent = isLiked ? currentLikes - 1 : currentLikes + 1;
                target.classList.toggle('liked');
                
                try {
                    const response = await fetch(`api/handle_comment_likes.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ comment_id: commentId })
                    });
                    const data = await response.json();
                    if(data.status !== 'success') {
                        likeCountSpan.textContent = currentLikes;
                        target.classList.toggle('liked');
                        alert('Failed to update like status.');
                    }
                } catch(e) {
                    likeCountSpan.textContent = currentLikes;
                    target.classList.toggle('liked');
                    alert('An error occurred while liking the comment.');
                }
            }
        });
        
        fetchComments();
    });
    </script>
</body>
</html>