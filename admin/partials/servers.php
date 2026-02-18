<?php
/**
 * Vue des serveurs (Modernized)
 */

use PostalWarmup\Models\Database;

if (!defined('ABSPATH')) {
    exit;
}

$search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle CRUD Logic here for simplicity, or move to Controller
if ($action === 'delete' && $id > 0 && check_admin_referer('pw_delete_server_' . $id)) {
    if (Database::delete_server($id)) {
        echo '<div class="notice notice-success is-dismissible"><p>Serveur supprimé.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Erreur lors de la suppression.</p></div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('pw_save_server')) {
    $data = [
        'domain' => sanitize_text_field($_POST['domain']),
        'api_url' => esc_url_raw($_POST['api_url']),
        'api_key' => sanitize_text_field($_POST['api_key']),
        'active' => isset($_POST['active']) ? 1 : 0,
        'daily_limit' => (int)$_POST['daily_limit'],
        'priority' => (int)$_POST['priority'],
    ];
    
    if ($id > 0) {
        // Update
        if (empty($data['api_key'])) unset($data['api_key']); // Don't overwrite if empty
        if (Database::update_server($id, $data)) {
            echo '<div class="notice notice-success is-dismissible"><p>Serveur mis à jour.</p></div>';
        }
    } else {
        // Create
        if (Database::insert_server($data)) {
            echo '<div class="notice notice-success is-dismissible"><p>Serveur ajouté.</p></div>';
            $action = ''; // Return to list
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Erreur lors de l\'ajout.</p></div>';
        }
    }
}

// EDIT / ADD VIEW
if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $server = ($id > 0) ? Database::get_server($id) : [];
    $title = ($id > 0) ? 'Modifier le serveur' : 'Ajouter un serveur';
?>
<div class="wrap pw-settings-page">
    <h1><?php echo esc_html($title); ?></h1>

    <div class="pw-card" style="max-width: 600px;">
        <div class="pw-card-body">
            <form method="post" action="">
                <?php wp_nonce_field('pw_save_server'); ?>

                <div class="pw-form-group">
                    <label>Domaine</label>
                    <input type="text" name="domain" value="<?php echo esc_attr($server['domain'] ?? ''); ?>" required class="large-text">
                    <p class="description">Le domaine configuré dans Postal.</p>
                </div>

                <div class="pw-form-group">
                    <label>API URL</label>
                    <input type="url" name="api_url" value="<?php echo esc_attr($server['api_url'] ?? ''); ?>" required class="large-text">
                    <p class="description">L'URL de votre instance Postal (ex: https://postal.example.com).</p>
                </div>

                <div class="pw-form-group">
                    <label>API Key</label>
                    <input type="password" name="api_key" value="" class="large-text" <?php echo ($id > 0) ? '' : 'required'; ?>>
                    <?php if($id > 0): ?><p class="description">Laissez vide pour ne pas changer.</p><?php endif; ?>
                </div>

                <div class="pw-form-group">
                    <label>Quota Journalier (Global)</label>
                    <input type="number" name="daily_limit" value="<?php echo esc_attr($server['daily_limit'] ?? 0); ?>" class="small-text">
                    <p class="description">0 = Illimité. Ce quota s'applique au serveur entier.</p>
                </div>

                <div class="pw-form-group">
                    <label>Priorité</label>
                    <input type="number" name="priority" value="<?php echo esc_attr($server['priority'] ?? 10); ?>" class="small-text">
                    <p class="description">Plus le chiffre est haut, plus le serveur est prioritaire.</p>
                </div>

                <div class="pw-form-group">
                    <label>
                        <input type="checkbox" name="active" value="1" <?php checked($server['active'] ?? 1, 1); ?>>
                        Actif
                    </label>
                </div>

                <div class="pw-actions">
                    <button type="submit" class="button button-primary">Enregistrer</button>
                    <a href="?page=postal-warmup-servers" class="button">Annuler</a>

                    <?php if($id > 0): ?>
                    <button type="button" class="button button-secondary" id="pw-test-connection" data-id="<?php echo $id; ?>" style="float:right;">
                        Tester la connexion
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery('#pw-test-connection').on('click', function() {
    var $btn = jQuery(this);
    var id = $btn.data('id');
    $btn.prop('disabled', true).text('Test en cours...');

    jQuery.post(ajaxurl, {
        action: 'pw_test_server',
        server_id: id,
        nonce: '<?php echo wp_create_nonce("pw_admin_nonce"); ?>'
    }, function(res) {
        $btn.prop('disabled', false).text('Tester la connexion');
        alert(res.data.message);
    });
});
</script>

<?php else:
// LIST VIEW
$servers = Database::get_servers(); // Get all (even inactive)?
// Database::get_servers defaults to limit 500. We might want pagination later.
?>
<div class="wrap pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Gestion des Serveurs', 'postal-warmup'); ?>
        </h1>
        <a href="?page=postal-warmup-servers&action=add" class="pw-btn pw-btn-primary">
            <span class="dashicons dashicons-plus"></span> Ajouter
        </a>
    </div>

    <div class="pw-card">
        <div class="pw-card-body" style="padding: 0;">
            <table class="pw-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Domaine</th>
                        <th>API URL</th>
                        <th>Status</th>
                        <th>Utilisation</th>
                        <th>Succès</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $server): ?>
                    <tr>
                        <td>#<?php echo $server['id']; ?></td>
                        <td><strong><?php echo esc_html($server['domain']); ?></strong></td>
                        <td><?php echo esc_html($server['api_url']); ?></td>
                        <td>
                            <?php if ($server['active']): ?>
                                <span class="pw-badge pw-badge-success">Actif</span>
                            <?php else: ?>
                                <span class="pw-badge pw-badge-danger">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format_i18n($server['sent_count']); ?></td>
                        <td><?php echo number_format_i18n($server['success_count']); ?></td>
                        <td>
                            <div class="pw-cell-actions">
                                <a href="?page=postal-warmup-servers&action=edit&id=<?php echo $server['id']; ?>" class="pw-btn pw-btn-secondary pw-btn-sm">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                <a href="<?php echo wp_nonce_url('?page=postal-warmup-servers&action=delete&id=' . $server['id'], 'pw_delete_server_' . $server['id']); ?>"
                                   class="pw-btn pw-btn-danger pw-btn-sm"
                                   onclick="return confirm('Supprimer ce serveur ?');">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($servers)): ?>
                    <tr><td colspan="7" style="text-align:center;">Aucun serveur trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
