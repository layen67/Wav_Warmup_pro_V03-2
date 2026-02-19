<?php
/**
 * Vue Scénarios (Engagement Engine)
 */

if (!defined('ABSPATH')) exit;

use PostalWarmup\Models\Scenario;
use PostalWarmup\Admin\TemplateManager;

$scenarios = Scenario::get_all();
$templates = TemplateManager::get_all_with_meta();
?>

<div class="wrap pw-dashboard" id="pw-scenarios-page">
    <div class="pw-header">
        <h1>🚀 Scénarios d'Engagement</h1>
        <button id="pw-new-scenario-btn" class="button button-primary">Nouveau Scénario</button>
    </div>

    <div class="pw-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Déclencheur</th>
                    <th>Étapes</th>
                    <th>Statut</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scenarios)): ?>
                    <tr><td colspan="5">Aucun scénario configuré.</td></tr>
                <?php else: ?>
                    <?php foreach ($scenarios as $s): ?>
                        <tr data-scenario="<?php echo esc_attr(json_encode($s)); ?>">
                            <td><strong><?php echo esc_html($s['name']); ?></strong></td>
                            <td><code><?php echo esc_html($s['trigger_event']); ?></code></td>
                            <td><?php echo count($s['steps']); ?> étapes</td>
                            <td>
                                <span class="pw-badge <?php echo $s['active'] ? 'success' : 'warning'; ?>">
                                    <?php echo $s['active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small pw-edit-scenario-btn">Modifier</button>
                                <button class="button button-small button-link-delete pw-delete-scenario-btn" data-id="<?php echo $s['id']; ?>">Supprimer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Editor -->
<div id="pw-scenario-editor-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content large">
        <span class="pw-modal-close">&times;</span>
        <h2 id="pw-scenario-modal-title">Éditeur de Scénario</h2>

        <form id="pw-scenario-form">
            <input type="hidden" id="pw-scenario-id">

            <div class="pw-form-row">
                <div class="pw-col-6">
                    <label>Nom du Scénario</label>
                    <input type="text" id="pw-scenario-name" class="widefat" required>
                </div>
                <div class="pw-col-3">
                    <label>Déclencheur</label>
                    <select id="pw-scenario-trigger" class="widefat">
                        <option value="reply">Réponse reçue</option>
                        <option value="click">Clic mailto</option>
                    </select>
                </div>
                <div class="pw-col-3" style="padding-top: 25px;">
                    <label><input type="checkbox" id="pw-scenario-active" value="1"> Actif</label>
                </div>
            </div>

            <hr>

            <h3>Séquence</h3>
            <div id="pw-scenario-steps-container"></div>

            <button type="button" id="pw-add-step-btn" class="button button-secondary">+ Ajouter une étape</button>

            <div class="pw-modal-actions">
                <button type="button" class="button pw-modal-cancel">Annuler</button>
                <button type="button" id="pw-save-scenario-btn" class="button button-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Underscore Template for Step Item -->
<script type="text/html" id="pw-step-item-template">
    <div class="pw-scenario-step">
        <div class="pw-step-header">
            <span class="pw-step-handle dashicons dashicons-move"></span>
            <strong>Étape <span class="pw-step-number"><%- index %></span></strong>
            <button type="button" class="pw-remove-step-btn dashicons dashicons-no-alt"></button>
        </div>
        <div class="pw-step-body">
            <label>Délai (jours) :
                <input type="number" class="pw-step-delay" value="<%- delay %>" min="0" style="width: 60px;">
            </label>
            <label>Template :
                <select class="pw-step-template">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?php echo esc_attr($t['name']); ?>"
                            <% if (template_name === '<?php echo esc_js($t['name']); ?>') { %>selected<% } %>>
                            <?php echo esc_html($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>
</script>

<style>
.pw-scenario-step {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
}
.pw-step-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    cursor: move;
}
.pw-remove-step-btn {
    background: none;
    border: none;
    color: #d63638;
    cursor: pointer;
}
</style>
