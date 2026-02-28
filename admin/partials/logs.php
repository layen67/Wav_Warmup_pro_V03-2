<?php
/**
 * Vue des logs (Modernized)
 */

use PostalWarmup\Models\Database;

if (!defined('ABSPATH')) {
    exit;
}

$page_num = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$per_page = 50;
$filters = [];

if (isset($_GET['server_id']) && $_GET['server_id'] > 0) {
    $filters['server_id'] = (int)$_GET['server_id'];
}
if (isset($_GET['level']) && !empty($_GET['level'])) {
    $filters['level'] = sanitize_text_field($_GET['level']);
}

$logs = Database::get_logs($filters, $per_page, $page_num);
$servers = Database::get_servers();

// Simple pagination logic (Assuming total count is costly, we just check if we got full page)
// Better: Add count method in Database.
// For now, simpler prev/next buttons.
?>

<div class="wrap pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-list-view"></span>
            <?php _e('Logs d\'activité', 'postal-warmup'); ?>
        </h1>
        <div class="pw-actions">
            <button id="pw-clear-logs" class="pw-btn pw-btn-danger">
                <span class="dashicons dashicons-trash"></span> Vider les logs
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="pw-card" style="margin-bottom: 20px;">
        <div class="pw-card-body">
            <form method="get" style="display: flex; gap: 15px; align-items: center;">
                <input type="hidden" name="page" value="postal-warmup-logs">

                <select name="server_id">
                    <option value="">Tous les serveurs</option>
                    <?php foreach ($servers as $server): ?>
                        <option value="<?php echo $server['id']; ?>" <?php selected($filters['server_id'] ?? '', $server['id']); ?>>
                            <?php echo esc_html($server['domain']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="level">
                    <option value="">Tous les niveaux</option>
                    <option value="info" <?php selected($filters['level'] ?? '', 'info'); ?>>INFO</option>
                    <option value="warning" <?php selected($filters['level'] ?? '', 'warning'); ?>>WARNING</option>
                    <option value="error" <?php selected($filters['level'] ?? '', 'error'); ?>>ERROR</option>
                    <option value="critical" <?php selected($filters['level'] ?? '', 'critical'); ?>>CRITICAL</option>
                </select>

                <button type="submit" class="button">Filtrer</button>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="pw-card">
        <div class="pw-card-body" style="padding: 0;">
            <table class="pw-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Niveau</th>
                        <th>Message</th>
                        <th>Contexte</th>
                        <th>Serveur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Aucun log trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log):
                            $badge_class = 'pw-badge-neutral';
                            $level = strtolower($log['level']);
                            if ($level === 'error' || $level === 'critical') $badge_class = 'pw-badge-error';
                            elseif ($level === 'warning') $badge_class = 'pw-badge-warning';
                            elseif ($level === 'success') $badge_class = 'pw-badge-success';
                        ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--pw-text-muted);">
                                <?php echo $log['created_at']; ?>
                            </td>
                            <td>
                                <span class="pw-badge <?php echo $badge_class; ?>"><?php echo esc_html($log['level']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($log['message']); ?></strong>
                                <?php if ($log['response_time'] > 0): ?>
                                    <span style="font-size: 0.8em; color: #888;">(<?php echo $log['response_time']; ?>s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($log['context'])) {
                                    $ctx = json_decode($log['context'], true);
                                    if ($ctx) {
                                        echo '<code style="display:block; max-height: 60px; overflow:auto; font-size:11px;">';
                                        echo esc_html(print_r($ctx, true));
                                        echo '</code>';
                                    } else {
                                        echo esc_html($log['context']);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo $log['server_id']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php if ($page_num > 1): ?>
                <a class="prev-page button" href="?page=postal-warmup-logs&paged=<?php echo $page_num - 1; ?>">‹ Précédent</a>
            <?php endif; ?>

            <span class="paging-input">Page <?php echo $page_num; ?></span>

            <?php if (count($logs) >= $per_page): ?>
                <a class="next-page button" href="?page=postal-warmup-logs&paged=<?php echo $page_num + 1; ?>">Suivant ›</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery('#pw-clear-logs').on('click', function() {
    if (!confirm('Voulez-vous vraiment supprimer tous les logs ?')) return;

    jQuery.post(ajaxurl, {
        action: 'pw_clear_logs',
        nonce: '<?php echo wp_create_nonce("pw_admin_nonce"); ?>'
    }, function(res) {
        alert(res.data.message);
        location.reload();
    });
});
</script>
