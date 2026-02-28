<?php
/**
 * Vue de Gestion des ISPs
 */

if (!defined('ABSPATH')) {
    exit;
}

use PostalWarmup\Admin\ISPManager;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('pw_save_isp')) {
    $result = ISPManager::save($_POST);
    if (is_wp_error($result)) {
        echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>ISP sauvegardé.</p></div>';
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (check_admin_referer('pw_delete_isp_' . $_GET['id'])) {
        ISPManager::delete((int)$_GET['id']);
        echo '<div class="notice notice-success"><p>ISP supprimé.</p></div>';
    }
}

$isps = ISPManager::get_all();
$edit_id = isset($_GET['id']) && (!isset($_GET['action']) || $_GET['action'] === 'edit') ? (int)$_GET['id'] : 0;
$edit_isp = $edit_id ? ISPManager::get($edit_id) : [];
?>

<div class="wrap pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-groups"></span>
            <?php _e('Gestion des FAI (ISP)', 'postal-warmup'); ?>
        </h1>
    </div>

    <div class="pw-layout-container" style="display:grid; grid-template-columns: 1fr 2fr; gap: 20px;">

        <!-- Form -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3><?php echo $edit_id ? 'Modifier ISP' : 'Ajouter un ISP'; ?></h3>
            </div>
            <div class="pw-card-body">
                <form method="post" action="?page=postal-warmup-isps">
                    <?php wp_nonce_field('pw_save_isp'); ?>
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">

                    <div class="pw-form-group">
                        <label>Clé Unique (Slug)</label>
                        <input type="text" name="isp_key" value="<?php echo esc_attr($edit_isp['isp_key'] ?? ''); ?>" required class="widefat" <?php echo $edit_id ? 'readonly' : ''; ?>>
                        <p class="description">Ex: gmail, orange, laposte. Ne peut pas changer.</p>
                    </div>

                    <div class="pw-form-group">
                        <label>Nom Affiché</label>
                        <input type="text" name="isp_label" value="<?php echo esc_attr($edit_isp['isp_label'] ?? ''); ?>" required class="widefat">
                    </div>

                    <div class="pw-form-group">
                        <label>Domaines (un par ligne)</label>
                        <textarea name="domains" rows="5" class="widefat" required><?php echo esc_textarea($edit_isp['domains'] ?? ''); ?></textarea>
                    </div>

                    <div class="pw-form-group">
                        <label>Stratégie</label>
                        <select name="strategy" class="widefat">
                            <option value="slow_rise" <?php selected($edit_isp['strategy'] ?? '', 'slow_rise'); ?>>Montée Lente (Prudent)</option>
                            <option value="standard" <?php selected($edit_isp['strategy'] ?? '', 'standard'); ?>>Standard (Recommandé)</option>
                            <option value="aggressive" <?php selected($edit_isp['strategy'] ?? '', 'aggressive'); ?>>Agressif (Risqué)</option>
                        </select>
                    </div>

                    <div class="pw-form-group">
                        <label>
                            <input type="checkbox" name="active" value="1" <?php checked($edit_isp['active'] ?? 1, 1); ?>>
                            Actif
                        </label>
                    </div>

                    <div class="pw-actions">
                        <button type="submit" class="button button-primary">Enregistrer</button>
                        <?php if ($edit_id): ?>
                            <a href="?page=postal-warmup-isps" class="button">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- List -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3>Liste des FAI Configurés</h3>
            </div>
            <div class="pw-card-body" style="padding:0;">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th>Clé</th>
                            <th>Nom</th>
                            <th>Domaines</th>
                            <th>Stratégie</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($isps as $isp): ?>
                        <tr>
                            <td><code><?php echo esc_html($isp['isp_key']); ?></code></td>
                            <td><?php echo esc_html($isp['isp_label']); ?></td>
                            <td><?php echo count(explode("\n", trim($isp['domains']))); ?> domaines</td>
                            <td>
                                <?php
                                $badges = [
                                    'slow_rise' => 'success',
                                    'standard' => 'primary',
                                    'aggressive' => 'danger'
                                ];
                                $cls = $badges[$isp['strategy']] ?? 'neutral';
                                echo "<span class='pw-badge pw-badge-$cls'>{$isp['strategy']}</span>";
                                ?>
                            </td>
                            <td>
                                <div class="pw-cell-actions">
                                    <a href="?page=postal-warmup-isps&action=edit&id=<?php echo $isp['id']; ?>" class="button button-small">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <a href="<?php echo wp_nonce_url('?page=postal-warmup-isps&action=delete&id=' . $isp['id'], 'pw_delete_isp_' . $isp['id']); ?>"
                                       class="button button-small"
                                       onclick="return confirm('Supprimer cet ISP ?');"
                                       style="color: #d63638;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
