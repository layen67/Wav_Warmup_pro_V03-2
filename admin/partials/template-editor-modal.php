<?php
/**
 * admin/partials/template-editor-modal.php
 * Template Editor Modal v3.1
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pw-template-editor-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content pw-modal-lg">
        <div class="pw-modal-header">
            <h2 id="pw-editor-title">Nouveau Template</h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        
        <div class="pw-modal-body">
            <form id="pw-template-editor-form">
                <input type="hidden" id="pw-editor-template-id" name="id">
                
                <div class="pw-editor-tabs">
                    <button type="button" class="pw-tab-btn active" data-tab="general">📌 Général</button>
                    <button type="button" class="pw-tab-btn" data-tab="postal">📧 Postal</button>
                    <button type="button" class="pw-tab-btn" data-tab="mailto">🔗 Mailto</button>
                    <button type="button" class="pw-tab-btn" data-tab="stats">📊 Stats</button>
                </div>
                
                <!-- Tab: General -->
                <div class="pw-tab-content active" id="pw-tab-general">
                    <div class="pw-form-group">
                        <label for="pw-editor-name">Nom du Template (identifiant unique)</label>
                        <input type="text" id="pw-editor-name" name="name" class="large-text" placeholder="ex: support_client" required style="display: block; width: 100%; height: 40px; margin-top: 5px;">
                    </div>

                    <div id="pw-system-template-info" class="pw-info-box" style="display:none; margin-bottom: 20px;">
                        <span class="dashicons dashicons-info"></span>
                        <div class="pw-info-content">
                            <strong><?php _e('Template Système', 'postal-warmup'); ?></strong><br>
                            <?php _e('Ce template ("null") sert de fallback universel. Si un prefix d\'email demandé n\'a pas de template correspondant, c\'est celui-ci qui est utilisé.', 'postal-warmup'); ?>
                        </div>
                    </div>
                    
                    <div class="pw-form-row">
                        <div class="pw-form-group">
                            <label for="pw-editor-folder">Dossier</label>
                            <select id="pw-editor-folder" name="folder_id" class="large-text">
                                <option value="">Aucun dossier</option>
                                <?php PW_Template_Manager::render_folder_options_html($folders); ?>
                            </select>
                        </div>
                        <div class="pw-form-group">
                            <label for="pw-editor-status">Statut</label>
                            <select id="pw-editor-status" name="status" class="large-text">
                                <option value="active">🟢 Actif</option>
                                <option value="draft">🟡 Brouillon</option>
                                <option value="archived">🔴 Archivé</option>
                                <option value="test">🔵 Test</option>
                            </select>
                        </div>
                        <div class="pw-form-group">
                            <label for="pw-editor-timezone">Fuseau Horaire</label>
                            <select id="pw-editor-timezone" name="timezone" class="large-text">
                                <option value="">Par défaut (Aucun)</option>
                                <?php foreach (timezone_identifiers_list() as $tz) { 
                                    echo '<option value="' . esc_attr($tz) . '">' . esc_html($tz) . '</option>';
                                } ?>
                            </select>
                            <p class="description">Si défini, ce template sera envoyé uniquement sur les horaires définis dans les paramètres selon le Fuseau Horaire du template.</p>
                        </div>
                    </div>
                    
                    <div class="pw-form-group">
                        <label for="pw-editor-tags">Tags (séparés par des virgules)</label>
                        <input type="text" id="pw-editor-tags" name="tags" class="large-text" placeholder="urgent, client, promo">
                    </div>

                    <div class="pw-form-group">
                        <label for="pw-editor-default-label">Libellé par défaut (Shortcode)</label>
                        <input type="text" id="pw-editor-default-label" name="default_label" class="large-text" placeholder="ex: Nous contacter">
                        <p class="description">Ce libellé sera affiché si le shortcode <code>[warmup_mailto]</code> est utilisé sans contenu.</p>
                    </div>

                    <div class="pw-form-group">
                        <label for="pw-editor-comment">Commentaire de version</label>
                        <textarea id="pw-editor-comment" name="comment" class="large-text" placeholder="Décrivez vos changements..."></textarea>
                    </div>
                </div>
                
                <!-- Tab: Postal -->
                <div class="pw-tab-content" id="pw-tab-postal">
                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Sujets d'email</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="subject" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="subject" title="Ajouter plusieurs lignes à la fois">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-subject" class="pw-variants-container"></div>
                    </div>
                    
                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Noms d'expéditeur</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="from_name" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="from_name" title="Ajouter plusieurs lignes à la fois">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-from_name" class="pw-variants-container"></div>
                    </div>

                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Reply-To (Optionnel)</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="reply_to" title="Ajouter une variante">+ Ajouter</button>
                            </div>
                        </div>
                        <div id="pw-variants-reply_to" class="pw-variants-container"></div>
                        <p class="description">Si vide, l'adresse de réponse sera la même que l'expéditeur.</p>
                    </div>

                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Contenu Texte</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="text" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="text" title="Ajouter plusieurs variantes avec séparateur ---">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-text" class="pw-variants-container"></div>
                    </div>

                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Contenu HTML</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="html" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="html" title="Ajouter plusieurs variantes avec séparateur ---">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-html" class="pw-variants-container"></div>
                    </div>
                </div>
                
                <!-- Tab: Mailto -->
                <div class="pw-tab-content" id="pw-tab-mailto">
                    <p class="description">Ces champs sont utilisés par le shortcode [warmup_mailto] pour pré-remplir l'email du visiteur.</p>
                    
                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Noms d'expéditeur Mailto</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="mailto_from_name" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="mailto_from_name" title="Ajouter plusieurs lignes à la fois">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-mailto_from_name" class="pw-variants-container"></div>
                    </div>

                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Sujets Mailto</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="mailto_subject" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="mailto_subject" title="Ajouter plusieurs lignes à la fois">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-mailto_subject" class="pw-variants-container"></div>
                    </div>

                    <div class="pw-editor-section">
                        <div class="pw-section-header">
                            <h3>Corps Mailto</h3>
                            <div class="pw-header-btns">
                                <button type="button" class="pw-add-variant" data-type="mailto_body" title="Ajouter une variante">+ Ajouter</button>
                                <button type="button" class="pw-bulk-add-btn" data-type="mailto_body" title="Ajouter plusieurs lignes à la fois">📂 Bulk</button>
                            </div>
                        </div>
                        <div id="pw-variants-mailto_body" class="pw-variants-container"></div>
                    </div>
                </div>
                
                <!-- Tab: Stats -->
                <div class="pw-tab-content" id="pw-tab-stats">
                    <div id="pw-template-stats-content">
                        <p>Les statistiques de performance pour ce template s'afficheront ici après les premiers envois.</p>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Annuler</button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-save-template-btn">💾 Sauvegarder</button>
        </div>
    </div>
</div>

<div id="pw-bulk-add-modal" class="pw-modal" style="display:none; z-index: 100001;">
    <div class="pw-modal-content pw-modal-sm">
        <div class="pw-modal-header">
            <h3>Bulk Add</h3>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <p id="pw-bulk-add-desc">Ajoutez chaque variante sur une nouvelle ligne :</p>
            <textarea id="pw-bulk-add-textarea" rows="10" class="large-text" placeholder="Variante 1&#10;Variante 2&#10;Variante 3"></textarea>
            <p class="description pw-bulk-info-text" style="display:none;">Pour le contenu HTML ou texte long, séparez chaque variante par une ligne contenant uniquement <code>---</code>.</p>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Annuler</button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-bulk-add-confirm">Ajouter</button>
        </div>
    </div>
</div>

<script type="text/template" id="pw-variant-item-template">
    <div class="pw-variant-item" data-type="<%- type %>">
        <div class="pw-variant-toolbar">
            <div class="pw-toolbar-group">
                <select class="pw-var-select" style="max-width: 120px;">
                    <option value="">Variables...</option>
                    <option value="{{prenom}}">Prénom</option>
                    <option value="{{civilite}}">Civilité</option>
                    <option value="{{email}}">Email</option>
                    <option value="{{heure_fr}}">Heure</option>
                    <option value="{{date}}">Date</option>
                    <option value="{{ref}}">Réf</option>
                    <option value="{{site_url}}">Site URL</option>
                    <option value="{{site_name}}">Site Name</option>
                    <option value="{{admin_email}}">Admin Email</option>
                </select>
                <button type="button" class="pw-insert-var-btn" title="Insérer à la position du curseur" style="margin-right:2px;"><span class="dashicons dashicons-plus-alt2" style="font-size:14px; width:14px; height:14px; margin-top:3px;"></span></button>
                <button type="button" class="pw-copy-var-btn" title="Copier dans le presse-papier"><span class="dashicons dashicons-clipboard" style="font-size:14px; width:14px; height:14px; margin-top:3px;"></span></button>
                <div style="width: 1px; background: #ddd; margin: 0 5px;"></div>
                <button type="button" class="pw-spintax-btn" title="Insérer Spintax { | }">Spintax</button>
                <div style="width: 1px; background: #ddd; margin: 0 8px;"></div>
                <button type="button" class="pw-toggle-btn active" data-mode="code">Code</button>
                <button type="button" class="pw-toggle-btn" data-mode="preview">Preview</button>
            </div>
            <div class="pw-toolbar-group">
                <button type="button" class="pw-expand-btn" title="Mode Focus (Plein écran)"><span class="dashicons dashicons-editor-expand"></span></button>
                <div style="width: 1px; background: #ddd; margin: 0 5px;"></div>
                <button type="button" class="pw-base64-btn" title="Encoder le contenu en Base64">Base64</button>
                <button type="button" class="pw-base64-decode-btn" title="Décoder le contenu Base64">Décoder</button>
            </div>
        </div>
        <div class="pw-variant-editor">
            <textarea name="variants[<%- type %>][]" class="pw-variant-input"><%- value %></textarea>
            <div class="pw-variant-preview" style="display:none;"></div>
        </div>
        <button type="button" class="pw-remove-variant">&times;</button>
    </div>
</script>

<style>
.pw-variant-item {
    position: relative;
    margin-bottom: 20px;
}
.pw-variant-toolbar {
    margin-bottom: 5px;
    display: flex;
    flex-wrap: wrap; /* Allow wrapping on small screens */
    justify-content: space-between;
    align-items: center;
    background: #f6f7f7; /* Slight background for toolbar */
    padding: 5px;
    border: 1px solid #c3c4c7;
    border-bottom: none; /* Connect to textarea */
    border-radius: 4px 4px 0 0;
}
.pw-toolbar-group {
    display: flex;
    align-items: center;
    gap: 5px; /* Spacing between items */
}
.pw-toggle-btn {
    padding: 3px 10px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    cursor: pointer;
    font-size: 11px;
}
.pw-toggle-btn:first-child { border-radius: 3px 0 0 3px; border-right: none; }
.pw-toggle-btn:last-child { border-radius: 0 3px 3px 0; }
.pw-toggle-btn.active {
    background: #fff;
    border-bottom-color: #fff;
    font-weight: 600;
}
.pw-var-select {
    font-size: 11px;
    height: 24px;
    min-height: 24px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    margin-right: 5px;
    padding: 0 5px;
    cursor: pointer;
}
.pw-spintax-btn {
    padding: 0 8px;
    font-size: 11px;
    height: 24px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #f0f0f1;
    cursor: pointer;
    color: #1d2327;
}
.pw-spintax-btn:hover {
    background: #fff;
    border-color: #2271b1;
}
.pw-insert-var-btn, .pw-copy-var-btn {
    padding: 0 5px;
    height: 24px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #fff;
    cursor: pointer;
    color: #2271b1;
}
.pw-insert-var-btn:hover, .pw-copy-var-btn:hover {
    border-color: #2271b1;
    background: #f0f0f1;
}
.pw-base64-btn {
    padding: 3px 10px;
    font-size: 10px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #fff;
    cursor: pointer;
    color: #2271b1;
}
.pw-base64-btn:hover {
    border-color: #2271b1;
}
.pw-base64-decode-btn {
    padding: 3px 10px;
    font-size: 10px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #fff;
    cursor: pointer;
    color: #4f46e5;
    margin-left: 5px;
}
.pw-base64-decode-btn:hover {
    border-color: #4f46e5;
}
.pw-expand-btn {
    padding: 3px 5px;
    font-size: 14px;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    background: #fff;
    cursor: pointer;
    color: #50575e;
    line-height: 1;
}
.pw-expand-btn:hover {
    border-color: #2271b1;
    color: #2271b1;
}
.pw-variant-preview {
    border: 1px solid #c3c4c7;
    padding: 10px;
    background: #fff;
    min-height: 40px;
    max-height: 300px;
    overflow-y: auto;
    font-family: inherit;
    white-space: pre-wrap;
    word-break: break-all;
}
.pw-variant-item[data-type="html"] .pw-variant-preview {
    font-family: initial;
    white-space: normal;
    word-break: normal;
}
/* Focus Mode Styles */
.pw-focus-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.85);
    z-index: 999999;
    display: flex;
    justify-content: center;
    align-items: center;
}
.pw-focus-container {
    background: #fff;
    width: 90%;
    height: 90%;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
}
.pw-focus-header {
    padding: 10px 15px;
    background: #f0f0f1;
    border-bottom: 1px solid #c3c4c7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.pw-focus-header h3 { margin: 0; }
.pw-focus-close {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #d63638;
}
.pw-focus-textarea {
    flex: 1;
    width: 100%;
    padding: 20px;
    font-family: monospace;
    font-size: 14px;
    line-height: 1.6;
    border: none;
    resize: none;
    outline: none;
}
</style>

<style>
.pw-header-btns {
    display: flex;
    gap: 5px;
}
.pw-header-btns button {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
    background: #fff;
    cursor: pointer;
}
.pw-header-btns button:hover {
    background: #f0f0f1;
    border-color: #8c8f94;
}
</style>
