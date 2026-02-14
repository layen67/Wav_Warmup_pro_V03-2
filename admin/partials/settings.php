<?php
/**
 * Vue de la page des paramètres (Modernized Tabbed Interface)
 */

use PostalWarmup\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

$settings_handler = new Settings();
$tabs = $settings_handler->get_tabs_config();
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

if (!array_key_exists($active_tab, $tabs)) {
    $active_tab = 'general';
}
?>

<div class="wrap pw-settings-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab): ?>
            <a href="?page=postal-warmup-settings&tab=<?php echo esc_attr($tab_id); ?>" class="nav-tab <?php echo $active_tab == $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <?php settings_errors('postal-warmup-settings'); ?>
    
    <form method="post" action="options.php" class="pw-settings-form">
        <?php
        // Security fields for the registered setting
        settings_fields('postal-warmup-settings');

        // Output sections for the current tab
        // The page slug matches what we used in add_settings_section
        do_settings_sections('postal-warmup-settings-' . $active_tab);

        submit_button();
        ?>
    </form>
    
    <!-- System Info (Only on Advanced tab or General?) Let's put it on Advanced or keep it visible at bottom? -->
    <?php if ($active_tab === 'advanced' || $active_tab === 'general'): ?>
    <div class="pw-form-section" style="margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px;">
        <h3><?php _e('Informations Système', 'postal-warmup'); ?></h3>
        <table class="form-table" style="max-width: 600px;">
            <tr>
                <th scope="row"><?php _e('Version du plugin', 'postal-warmup'); ?></th>
                <td><code><?php echo PW_VERSION; ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('PHP', 'postal-warmup'); ?></th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
</div>
