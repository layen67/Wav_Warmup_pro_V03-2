<?php
/**
 * Vue moderne des Templates (v3.1)
 * Remplace l'ancienne vue liste simple par une interface riche (Grid/List, Folders, Drag&Drop).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue styles/scripts specifically for this page is handled in Admin.php
?>

<div class="wrap pw-dashboard pw-templates-v31">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-layout"></span>
            <?php _e('Gestion des Templates', 'postal-warmup'); ?>
        </h1>
        <div class="pw-actions">
            <button id="pw-new-template-btn" class="pw-btn pw-btn-primary">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Nouveau Template', 'postal-warmup'); ?>
            </button>
        </div>
    </div>

    <div class="pw-layout-container">
        <!-- Sidebar: Folders & Filters -->
        <aside class="pw-sidebar">
            <div class="pw-sidebar-header">
                <h3><?php _e('Dossiers', 'postal-warmup'); ?></h3>
                <button id="pw-add-folder-btn" class="pw-icon-btn" title="<?php _e('Nouveau dossier', 'postal-warmup'); ?>">
                    <span class="dashicons dashicons-plus"></span>
                </button>
            </div>

            <ul class="pw-folders-list" id="pw-folders-tree">
                <!-- Dynamic Content -->
                <li class="pw-folder-item active" data-folder-id="">
                    <span class="dashicons dashicons-category"></span>
                    <?php _e('Tous les templates', 'postal-warmup'); ?>
                </li>
            </ul>

            <div class="pw-sidebar-divider"></div>

            <div class="pw-sidebar-section">
                <h3><?php _e('Filtres rapides', 'postal-warmup'); ?></h3>
                <ul class="pw-quick-filters">
                    <li class="pw-quick-link" data-filter="favorite">
                        <span class="dashicons dashicons-star-filled"></span> <?php _e('Favoris', 'postal-warmup'); ?>
                        <span id="pw-favorites-count" class="pw-count-badge">0</span>
                    </li>
                    <li class="pw-system-item" data-template="null">
                        <span class="dashicons dashicons-admin-settings"></span> <?php _e('Configuration Système', 'postal-warmup'); ?>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content: Template Grid/List -->
        <main class="pw-main-content">
            <!-- Toolbar -->
            <div class="pw-toolbar">
                <div class="pw-search-box">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="pw-search-input" placeholder="<?php _e('Rechercher un template...', 'postal-warmup'); ?>">
                    <span class="pw-search-clear dashicons dashicons-dismiss" style="display:none;"></span>
                </div>

                <div class="pw-toolbar-actions">
                    <select id="pw-filter-status">
                        <option value=""><?php _e('Tous les statuts', 'postal-warmup'); ?></option>
                        <option value="active"><?php _e('Actifs', 'postal-warmup'); ?></option>
                        <option value="draft"><?php _e('Brouillons', 'postal-warmup'); ?></option>
                        <option value="archived"><?php _e('Archivés', 'postal-warmup'); ?></option>
                    </select>

                    <button id="pw-toggle-view" class="pw-icon-btn" title="<?php _e('Changer la vue', 'postal-warmup'); ?>">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>

                    <button id="pw-import-btn" class="pw-btn pw-btn-secondary">
                        <span class="dashicons dashicons-upload"></span> <?php _e('Importer', 'postal-warmup'); ?>
                    </button>
                    <input type="file" id="pw-import-file" style="display:none;" accept=".json">
                </div>
            </div>

            <!-- Content Area -->
            <div id="pw-templates-container" class="pw-templates-grid">
                <!-- Cards will be injected by JS -->
                <div class="pw-loading-state">
                    <span class="spinner is-active"></span> <?php _e('Chargement des templates...', 'postal-warmup'); ?>
                </div>
            </div>

            <!-- Empty State -->
            <div class="pw-empty-state" style="display:none;">
                <div class="pw-empty-content">
                    <img src="<?php echo PW_PLUGIN_URL . 'admin/assets/img/empty-templates.svg'; ?>" alt="Empty" style="width: 150px; opacity: 0.5;">
                    <h3><?php _e('Aucun template trouvé', 'postal-warmup'); ?></h3>
                    <p><?php _e('Commencez par créer votre premier template d\'emailing.', 'postal-warmup'); ?></p>
                    <button id="pw-new-template-empty-btn" class="pw-btn pw-btn-primary">
                        <?php _e('Créer un template', 'postal-warmup'); ?>
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modals & Templates (Include partials) -->
<?php
require_once PW_ADMIN_DIR . 'partials/template-editor-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-category-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-preview-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-duplicate-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-move-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-versions-modal.php';
require_once PW_ADMIN_DIR . 'partials/template-stats-widget.php';
?>

<!-- JS Templates for Underscore/Mustache style rendering in JS -->
<script type="text/html" id="pw-template-card-template">
    <!-- Defined in JS logic, but structure is: -->
    <div class="pw-template-card" data-template-id="<%= id %>" draggable="true">
        <div class="pw-card-preview">...</div>
        <div class="pw-card-meta">...</div>
    </div>
</script>
