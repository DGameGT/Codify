<style>
/* Style khusus untuk modal */
.modal-header { padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
.modal-header h2 { font-size: 1.25rem; font-weight: 600; }
.modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem; transition: var(--transition); }
.modal-close:hover { color: var(--text-primary); }
.modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
.modal-footer { padding: 1.25rem; border-top: 1px solid var(--border-color); text-align: right; background-color: var(--bg-tertiary); border-radius: 0 0 var(--radius) var(--radius); }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-secondary); }
.form-control { width: 100%; padding: 0.75rem; background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.5rem; color: var(--text-primary); font-size: 1rem; transition: var(--transition); }
.form-control:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.input-group { display: flex; align-items: center; margin-bottom: 1.25rem; }
.input-group-icon { background-color: var(--bg-tertiary); border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 0.5rem 0 0 0.5rem; color: var(--text-secondary); }
.input-group .form-control { border-radius: 0 0.5rem 0.5rem 0; border-left: none; }
.btn { display: inline-block; padding: 0.6rem 1.2rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: var(--transition); text-decoration: none; }
.btn-primary { background-color: var(--accent-primary); color: #fff; }
.btn-primary:hover { opacity: 0.9; }

/* Image upload preview styles */
.image-upload-group { display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; }
.image-preview { border: 2px dashed var(--border-color); padding: 0.5rem; background-color: var(--bg-primary); }
.avatar-preview { width: 80px; height: 80px; border-radius: 50%; }
.banner-preview { width: 100%; height: 100px; border-radius: var(--radius); }
.image-preview img { width: 100%; height: 100%; object-fit: cover; }
.upload-label { flex-grow: 1; }
input[type="file"] { border: 1px solid var(--border-color); padding: 0.5rem; border-radius: 0.5rem; }
input[type="file"]::file-selector-button { background-color: var(--bg-tertiary); color: var(--text-primary); border: none; padding: 0.5rem 1rem; border-radius: 0.4rem; cursor: pointer; transition: var(--transition); margin-right: 1rem;}
input[type="file"]::file-selector-button:hover { background-color: var(--accent-primary); }
</style>

<div class="modal-overlay" id="settings-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Profile Settings</h2>
            <button class="modal-close" id="close-settings-modal">&times;</button>
        </div>
        <form action="profile.php?user=<?php echo urlencode($user_data['username']); ?>" method="post" enctype="multipart/form-data">
            <div class="modal-body">
                
                <div class="image-upload-group">
                    <div class="image-preview avatar-preview">
                        <img id="modal-avatar-preview" src="db/avatars/<?php echo htmlspecialchars($user_data['profile_picture'] ?? 'default_avatar.png'); ?>" alt="Avatar Preview">
                    </div>
                    <div class="upload-label form-group">
                        <label for="avatar-input">Avatar</label>
                        <input type="file" id="avatar-input" name="avatar" class="form-control" accept="image/png, image/jpeg, image/gif">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="banner-input">Profile Banner</label>
                    <div class="image-preview banner-preview">
                        <img id="modal-banner-preview" src="db/banners/<?php echo htmlspecialchars($user_data['banner'] ?? 'default_banner.png'); ?>" alt="Banner Preview">
                    </div>
                    <input type="file" id="banner-input" name="banner" class="form-control" accept="image/png, image/jpeg, image/gif" style="margin-top: 0.5rem;">
                </div>

                <hr style="border-color: var(--border-color); margin: 1.5rem 0;">

                <div class="form-group">
                    <label for="bio-input">Bio</label>
                    <textarea id="bio-input" name="bio" class="form-control" rows="3" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                </div>
                
                <h3 style="margin-bottom: 1rem; font-weight: 600;">Social Links</h3>
                <div class="input-group"><span class="input-group-icon"><i class="fab fa-github"></i></span><input type="text" name="github" class="form-control" placeholder="GitHub Username" value="<?php echo htmlspecialchars($social_links['github'] ?? ''); ?>"></div>
                <div class="input-group"><span class="input-group-icon"><i class="fab fa-instagram"></i></span><input type="text" name="instagram" class="form-control" placeholder="Instagram Username" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>"></div>
                <div class="input-group"><span class="input-group-icon"><i class="fab fa-youtube"></i></span><input type="url" name="youtube" class="form-control" placeholder="YouTube Channel URL" value="<?php echo htmlspecialchars($social_links['youtube'] ?? ''); ?>"></div>
                <div class="input-group"><span class="input-group-icon"><i class="fas fa-link"></i></span><input type="url" name="website1" class="form-control" placeholder="Website / Portfolio URL" value="<?php echo htmlspecialchars($social_links['website1'] ?? ''); ?>"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>