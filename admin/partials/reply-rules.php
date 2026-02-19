<?php
/**
 * Vue Règles de Réponse
 */

if (!defined('ABSPATH')) exit;

use PostalWarmup\Models\ReplyTemplateRule;
use PostalWarmup\Admin\TemplateManager;

$rules = ReplyTemplateRule::get_all();
$templates = TemplateManager::get_all_with_meta();
?>

<div class="wrap pw-dashboard" id="pw-reply-rules-page">
    <div class="pw-header">
        <h1>⚡ Règles de Réponse Automatique</h1>
        <button id="pw-new-rule-btn" class="button button-primary">Nouvelle Règle</button>
    </div>

    <div class="pw-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Condition (Préfixe)</th>
                    <th>Réponse (Template)</th>
                    <th>Statut</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)): ?>
                    <tr><td colspan="5">Aucune règle configurée.</td></tr>
                <?php else: ?>
                    <?php foreach ($rules as $r): ?>
                        <tr data-rule="<?php echo esc_attr(json_encode($r)); ?>">
                            <td><strong><?php echo esc_html($r['name']); ?></strong></td>
                            <td><code><?php echo esc_html($r['match_prefix']); ?></code></td>
                            <td><?php echo esc_html($r['response_template_name']); ?></td>
                            <td>
                                <span class="pw-badge <?php echo $r['active'] ? 'success' : 'warning'; ?>">
                                    <?php echo $r['active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small pw-edit-rule-btn">Modifier</button>
                                <button class="button button-small button-link-delete pw-delete-rule-btn" data-id="<?php echo $r['id']; ?>">Supprimer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Editor -->
<div id="pw-rule-editor-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content">
        <span class="pw-modal-close">&times;</span>
        <h2 id="pw-rule-modal-title">Éditeur de Règle</h2>

        <form id="pw-rule-form">
            <input type="hidden" id="pw-rule-id">

            <div class="pw-form-group">
                <label>Nom de la règle</label>
                <input type="text" id="pw-rule-name" class="widefat" required>
            </div>

            <div class="pw-form-group">
                <label>Si le préfixe de l'email reçu est :</label>
                <input type="text" id="pw-rule-prefix" class="widefat" placeholder="ex: contact">
                <p class="description">Laisser vide pour matcher tous les emails.</p>
            </div>

            <div class="pw-form-group">
                <label>Alors répondre avec le template :</label>
                <select id="pw-rule-template" class="widefat">
                    <?php foreach ($templates as $t): ?>
                        <option value="<?php echo esc_attr($t['name']); ?>"><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pw-form-group">
                <label><input type="checkbox" id="pw-rule-active" value="1"> Activer cette règle</label>
            </div>

            <div class="pw-modal-actions">
                <button type="button" class="button pw-modal-cancel">Annuler</button>
                <button type="button" id="pw-save-rule-btn" class="button button-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
