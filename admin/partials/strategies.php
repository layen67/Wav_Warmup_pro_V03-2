<?php
/**
 * Vue de Gestion des Stratégies
 */

if (!defined('ABSPATH')) {
    exit;
}

use PostalWarmup\Admin\StrategyManager;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('pw_save_strategy')) {
    $result = StrategyManager::save($_POST);
    if (is_wp_error($result)) {
        echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Stratégie sauvegardée.</p></div>';
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (check_admin_referer('pw_delete_strategy_' . $_GET['id'])) {
        StrategyManager::delete((int)$_GET['id']);
        echo '<div class="notice notice-success"><p>Stratégie supprimée.</p></div>';
    }
}

$strategies = StrategyManager::get_all();
$edit_id = isset($_GET['id']) && (!isset($_GET['action']) || $_GET['action'] === 'edit') ? (int)$_GET['id'] : 0;
$edit_strat = $edit_id ? StrategyManager::get($edit_id) : [];
$config = $edit_strat['config'] ?? [
    'start_volume' => 10,
    'growth_type' => 'linear',
    'growth_value' => 5,
    'max_volume' => 500,
    'safety_rules' => [
        'max_hard_bounce' => 5,
        'max_complaint' => 0.1
    ]
];
?>

<div class="wrap pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-chart-line"></span>
            <?php _e('Stratégies de Montée en Charge', 'postal-warmup'); ?>
        </h1>
    </div>

    <div class="pw-layout-container" style="display:grid; grid-template-columns: 1fr 2fr; gap: 20px;">

        <!-- Form -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3><?php echo $edit_id ? 'Modifier Stratégie' : 'Nouvelle Stratégie'; ?></h3>
            </div>
            <div class="pw-card-body">
                <form method="post" action="?page=postal-warmup-strategies">
                    <?php wp_nonce_field('pw_save_strategy'); ?>
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">

                    <div class="pw-form-group">
                        <label>Nom</label>
                        <input type="text" name="name" value="<?php echo esc_attr($edit_strat['name'] ?? ''); ?>" required class="widefat">
                    </div>

                    <div class="pw-form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" class="widefat"><?php echo esc_textarea($edit_strat['description'] ?? ''); ?></textarea>
                    </div>

                    <hr>

                    <h4>Configuration de Montée</h4>
                    <div class="pw-form-group">
                        <label>Volume de Départ</label>
                        <input type="number" name="config[start_volume]" value="<?php echo (int)$config['start_volume']; ?>" class="small-text">
                    </div>

                    <div class="pw-form-group">
                        <label>Type de Croissance</label>
                        <select name="config[growth_type]" class="widefat">
                            <option value="linear" <?php selected($config['growth_type'], 'linear'); ?>>Linéaire (+X par jour)</option>
                            <option value="exponential" <?php selected($config['growth_type'], 'exponential'); ?>>Exponentielle (+X% par jour)</option>
                            <option value="mixed" <?php selected($config['growth_type'], 'mixed'); ?>>Mixte (Linéaire puis Exponentielle)</option>
                        </select>
                    </div>

                    <div class="pw-form-group">
                        <label>Valeur de Croissance (X)</label>
                        <input type="number" name="config[growth_value]" value="<?php echo (float)$config['growth_value']; ?>" step="0.1" class="small-text">
                        <p class="description">Si Linéaire: nombre d'emails. Si Exponentielle: pourcentage (ex: 20 pour 20%).</p>
                    </div>

                    <div class="pw-form-group">
                        <label>Volume Maximum</label>
                        <input type="number" name="config[max_volume]" value="<?php echo (int)$config['max_volume']; ?>" class="small-text">
                    </div>

                    <hr>

                    <h4>Sécurité (Pauses)</h4>
                    <div class="pw-form-group">
                        <label>Max Hard Bounce (%)</label>
                        <input type="number" name="config[safety_rules][max_hard_bounce]" value="<?php echo (float)$config['safety_rules']['max_hard_bounce']; ?>" step="0.1" class="small-text">
                    </div>
                    <div class="pw-form-group">
                        <label>Max Plaintes (%)</label>
                        <input type="number" name="config[safety_rules][max_complaint]" value="<?php echo (float)$config['safety_rules']['max_complaint']; ?>" step="0.01" class="small-text">
                    </div>

                    <div class="pw-actions">
                        <button type="submit" class="button button-primary">Enregistrer</button>
                        <?php if ($edit_id): ?>
                            <a href="?page=postal-warmup-strategies" class="button">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- List -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3>Stratégies Disponibles</h3>
            </div>
            <div class="pw-card-body" style="padding:0;">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Départ -> Max</th>
                            <th>Règles Sécu</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($strategies as $strat):
                            $conf = $strat['config'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($strat['name']); ?></strong><br>
                                <span class="description"><?php echo esc_html($strat['description']); ?></span>
                            </td>
                            <td><?php echo $conf['growth_type']; ?></td>
                            <td><?php echo "{$conf['start_volume']} -> {$conf['max_volume']}"; ?></td>
                            <td>
                                <span class="pw-badge pw-badge-warning">Bounce > <?php echo $conf['safety_rules']['max_hard_bounce']; ?>%</span>
                            </td>
                            <td>
                                <div class="pw-cell-actions">
                                    <a href="?page=postal-warmup-strategies&action=edit&id=<?php echo $strat['id']; ?>" class="button button-small">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <a href="<?php echo wp_nonce_url('?page=postal-warmup-strategies&action=delete&id=' . $strat['id'], 'pw_delete_strategy_' . $strat['id']); ?>"
                                       class="button button-small"
                                       onclick="return confirm('Supprimer cette stratégie ?');"
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
