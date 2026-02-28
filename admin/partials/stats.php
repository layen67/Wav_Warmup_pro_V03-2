<?php
/**
 * Vue des Statistiques
 */

use PostalWarmup\Models\Stats;
use PostalWarmup\Models\Database;

if (!defined('ABSPATH')) {
    exit;
}

$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$global = Stats::get_global_stats($days);
$servers = Database::get_servers();
$top_templates = Stats::get_top_templates($days, 10);
?>

<div class="wrap pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-chart-pie"></span>
            <?php _e('Statistiques Globales', 'postal-warmup'); ?>
        </h1>
        <div class="pw-actions">
            <select id="pw-stats-range" onchange="location.href='?page=postal-warmup-stats&days='+this.value">
                <option value="7" <?php selected($days, 7); ?>>7 derniers jours</option>
                <option value="30" <?php selected($days, 30); ?>>30 derniers jours</option>
                <option value="90" <?php selected($days, 90); ?>>3 mois</option>
            </select>
            <button id="pw-export-csv" class="pw-btn pw-btn-secondary">
                <span class="dashicons dashicons-download"></span> CSV
            </button>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="pw-stats-grid">
        <div class="pw-card" style="grid-column: span 2;">
            <div class="pw-card-header">
                <h3>Volume & Taux de Succès</h3>
            </div>
            <div class="pw-card-body">
                <canvas id="pw-main-chart" style="width:100%; height:300px;"></canvas>
            </div>
        </div>
        
        <div class="pw-card">
            <div class="pw-card-header">
                <h3>Performance par Heure (Heatmap)</h3>
            </div>
            <div class="pw-card-body">
                <canvas id="pw-hourly-chart" style="width:100%; height:300px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Details Row -->
    <div class="pw-dashboard-grid">
        <!-- Top Templates -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3>Top Templates</h3>
            </div>
            <div class="pw-card-body" style="padding:0;">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Envois</th>
                            <th>Succès</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_templates as $tpl):
                            $rate = $tpl['usage_count'] > 0 ? round(($tpl['success_count'] / $tpl['usage_count']) * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($tpl['template_used']); ?></td>
                            <td><?php echo number_format_i18n($tpl['usage_count']); ?></td>
                            <td>
                                <div class="pw-progress-wrapper" style="width: 60px;">
                                    <div class="pw-progress-bar success" style="width: <?php echo $rate; ?>%"></div>
                                </div>
                                <span style="font-size:11px;"><?php echo $rate; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Server Performance -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3>Performance Serveurs</h3>
            </div>
            <div class="pw-card-body" style="padding:0;">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th>Serveur</th>
                            <th>Latence Moy.</th>
                            <th>Erreurs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $s):
                            $stats_s = Stats::get_server_stats((int)$s['id'], $days);
                            $avg_time = 0;
                            $total_err = 0;
                            if (!empty($stats_s)) {
                                // Simple aggregation for display
                                $count = 0;
                                foreach($stats_s as $day) {
                                    $avg_time += $day['avg_time'];
                                    $total_err += $day['total_errors'];
                                    $count++;
                                }
                                if ($count > 0) $avg_time /= $count;
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html($s['domain']); ?></td>
                            <td><?php echo round($avg_time * 1000); ?> ms</td>
                            <td><?php echo number_format_i18n($total_err); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load Advanced Stats via AJAX to populate charts
    $.post(ajaxurl, {
        action: 'pw_get_advanced_stats',
        nonce: '<?php echo wp_create_nonce("pw_admin_nonce"); ?>',
        days: <?php echo $days; ?>
    }, function(res) {
        if(res.success) {
            const data = res.data.charts;

            // Main Chart
            new Chart(document.getElementById('pw-main-chart'), {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [
                        {
                            label: 'Envoyés',
                            data: data.sent,
                            borderColor: '#2271b1',
                            fill: false
                        },
                        {
                            label: 'Délivrabilité (%)',
                            data: data.deliverability,
                            borderColor: '#00a32a',
                            yAxisID: 'y1',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true },
                        y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false } }
                    }
                }
            });

            // Hourly/Heatmap Chart (Simplified as Bar for now)
            // Real heatmap requires plugin or complex config.
            // Let's use simple bar chart of volume per day as placeholder for "Hourly" if we don't process hourly data in JS yet.
            // Backend sends 'heatmap' data: { template: { hour: count } }
            // Let's visualize open rate instead?

            new Chart(document.getElementById('pw-hourly-chart'), {
                type: 'bar',
                data: {
                    labels: data.dates,
                    datasets: [
                        {
                            label: 'Erreurs',
                            data: data.errors,
                            backgroundColor: '#d63638'
                        },
                        {
                            label: 'Taux Ouverture (%)',
                            data: data.open_rate,
                            backgroundColor: '#dba617',
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true },
                        y1: { position: 'right', min: 0, max: 100 }
                    }
                }
            });
        }
    });

    $('#pw-export-csv').on('click', function() {
        window.open(ajaxurl + '?action=pw_export_stats&nonce=<?php echo wp_create_nonce("pw_admin_nonce"); ?>');
    });
});
</script>
