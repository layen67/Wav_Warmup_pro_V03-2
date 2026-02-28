<div id="pw-template-editor-modal" class="pw-modal">
    <div class="pw-modal-content pw-modal-large">
        <div class="pw-modal-header">
            <h2 id="pw-editor-title">Éditeur de Template</h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <form id="pw-template-editor-form">
                <input type="hidden" name="id" id="pw-editor-template-id">
                
                <div class="pw-editor-main">
                    <!-- Left: Configuration -->
                    <div class="pw-editor-sidebar">
                        <div class="pw-form-group">
                            <label>Nom du template</label>
                            <input type="text" name="name" id="pw-editor-name" class="large-text" required>
                            <p class="description" id="pw-system-template-info" style="display:none; color:orange;">
                                <span class="dashicons dashicons-warning"></span> Ce template système ne peut pas être renommé.
                            </p>
                        </div>

                        <div class="pw-form-group">
                            <label>Dossier</label>
                            <select name="folder_id" id="pw-editor-folder" class="widefat">
                                <!-- Populated via JS -->
                            </select>
                        </div>

                        <div class="pw-form-group">
                            <label>Statut</label>
                            <select name="status" id="pw-editor-status" class="widefat">
                                <option value="active">Actif</option>
                                <option value="draft">Brouillon</option>
                                <option value="archived">Archivé</option>
                            </select>
                        </div>

                        <div class="pw-form-group">
                            <label>Tags</label>
                            <input type="text" name="tags" id="pw-editor-tags" class="widefat" placeholder="tag1, tag2">
                        </div>

                        <div class="pw-form-group">
                            <label>Libellé par défaut (Bouton)</label>
                            <input type="text" name="default_label" id="pw-editor-default-label" class="widefat" placeholder="Ex: Contactez-nous">
                        </div>

                        <div class="pw-form-group">
                            <label>Fuseau horaire</label>
                            <select name="timezone" id="pw-editor-timezone" class="widefat">
                                <option value="">Global (<?php echo esc_html(wp_timezone_string()); ?>)</option>
                                <?php echo wp_timezone_choice(''); ?>
                            </select>
                        </div>
                    </div>

                    <!-- Right: Variants Editor -->
                    <div class="pw-editor-content">
                        <!-- Tabs -->
                        <div class="pw-tabs-nav">
                            <button type="button" class="pw-tab-btn active" data-tab="general">Contenu Email</button>
                            <button type="button" class="pw-tab-btn" data-tab="mailto">Configuration Mailto</button>
                        </div>

                        <!-- Tab: General -->
                        <div id="pw-tab-general" class="pw-tab-content active">
                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Objets (Rotation)</label>
                                    <div class="pw-section-actions">
                                        <button type="button" class="button pw-bulk-add-btn" data-type="subject">Bulk Add</button>
                                        <button type="button" class="button pw-add-variant" data-type="subject"><span class="dashicons dashicons-plus"></span></button>
                                    </div>
                                </div>
                                <div id="pw-variants-subject" class="pw-variants-container"></div>
                            </div>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Nom d'expéditeur (Rotation)</label>
                                    <div class="pw-section-actions">
                                        <button type="button" class="button pw-add-variant" data-type="from_name"><span class="dashicons dashicons-plus"></span></button>
                                    </div>
                                </div>
                                <div id="pw-variants-from_name" class="pw-variants-container"></div>
                            </div>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Reply-To (Optionnel)</label>
                                    <div class="pw-section-actions">
                                        <button type="button" class="button pw-add-variant" data-type="reply_to"><span class="dashicons dashicons-plus"></span></button>
                                    </div>
                                </div>
                                <div id="pw-variants-reply_to" class="pw-variants-container"></div>
                            </div>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Contenu HTML (Rotation)</label>
                                    <div class="pw-section-actions">
                                        <button type="button" class="button pw-bulk-add-btn" data-type="html">Bulk Add</button>
                                        <button type="button" class="button pw-add-variant" data-type="html"><span class="dashicons dashicons-plus"></span></button>
                                    </div>
                                </div>
                                <div id="pw-variants-html" class="pw-variants-container large"></div>
                            </div>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Version Texte (Rotation)</label>
                                    <div class="pw-section-actions">
                                        <button type="button" class="button pw-bulk-add-btn" data-type="text">Bulk Add</button>
                                        <button type="button" class="button pw-add-variant" data-type="text"><span class="dashicons dashicons-plus"></span></button>
                                    </div>
                                </div>
                                <div id="pw-variants-text" class="pw-variants-container"></div>
                            </div>
                        </div>

                        <!-- Tab: Mailto -->
                        <div id="pw-tab-mailto" class="pw-tab-content">
                            <p class="description">
                                Configuration spécifique pour le shortcode <code>[warmup_mailto]</code>.
                                Si vide, utilise le contenu général.
                            </p>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Sujets Mailto</label>
                                    <button type="button" class="button pw-add-variant" data-type="mailto_subject"><span class="dashicons dashicons-plus"></span></button>
                                </div>
                                <div id="pw-variants-mailto_subject" class="pw-variants-container"></div>
                            </div>

                            <div class="pw-field-section">
                                <div class="pw-section-header">
                                    <label>Corps Mailto</label>
                                    <button type="button" class="button pw-add-variant" data-type="mailto_body"><span class="dashicons dashicons-plus"></span></button>
                                </div>
                                <div id="pw-variants-mailto_body" class="pw-variants-container large"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-modal-cancel">Annuler</button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-save-template-btn">💾 Sauvegarder</button>
        </div>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="pw-bulk-add-modal" class="pw-modal" style="z-index: 100001;">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h3>Ajout en masse</h3>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <p>Entrez une variante par ligne.</p>
            <p class="description pw-bulk-info-text" style="display:none;">Pour HTML/Text, séparez par <code>---</code> sur une nouvelle ligne.</p>
            <textarea id="pw-bulk-add-textarea" rows="10" style="width:100%;"></textarea>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button" onclick="jQuery('#pw-bulk-add-modal').hide();">Annuler</button>
            <button type="button" class="button button-primary" id="pw-bulk-add-confirm">Ajouter</button>
        </div>
    </div>
</div>

<!-- Underscore Template for Variants -->
<script type="text/html" id="pw-variant-item-template">
    <div class="pw-variant-item">
        <div class="pw-variant-toolbar">
            <span class="pw-drag-handle dashicons dashicons-move"></span>

            <div class="pw-toolbar-group">
                <select class="pw-var-select">
                    <option value="">Insérer variable...</option>
                    <optgroup label="Standard">
                        <option value="{{email}}">Email destinataire</option>
                        <option value="{{domain}}">Domaine</option>
                        <option value="{{date}}">Date (JJ/MM/AAAA)</option>
                        <option value="{{time}}">Heure (HH:MM)</option>
                    </optgroup>
                    <optgroup label="Avancé">
                        <option value="{{prenom}}">Prénom (déduit)</option>
                        <option value="{{civilite}}">Civilité (Bonjour/Bonsoir)</option>
                        <option value="{{ref}}">Référence Unique</option>
                    </optgroup>
                </select>
                <button type="button" class="button button-small pw-insert-var-btn" title="Insérer au curseur">Insérer</button>
                <button type="button" class="button button-small pw-copy-var-btn" title="Copier dans le presse-papier">Copier</button>
            </div>

            <div class="pw-toolbar-group">
                <button type="button" class="button button-small pw-spintax-btn" title="Insérer Spintax {a|b}">Spintax</button>
                <button type="button" class="button button-small pw-base64-btn" title="Encoder Base64">B64 Enc</button>
                <button type="button" class="button button-small pw-base64-decode-btn" title="Décoder Base64">B64 Dec</button>
            </div>

            <div class="pw-toolbar-group pw-view-toggle">
                <button type="button" class="pw-toggle-btn active" data-mode="code" title="Code"><span class="dashicons dashicons-editor-code"></span></button>
                <button type="button" class="pw-toggle-btn" data-mode="preview" title="Aperçu rapide"><span class="dashicons dashicons-visibility"></span></button>
            </div>

            <button type="button" class="pw-expand-btn" title="Focus Mode"><span class="dashicons dashicons-editor-expand"></span></button>
            <button type="button" class="pw-remove-variant" title="Supprimer"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="pw-variant-editor">
            <textarea name="variants[<%- type %>][]" class="pw-variant-input" placeholder="Saisissez votre contenu ici..."><%- value %></textarea>
            <div class="pw-variant-preview" style="display:none;"></div>
        </div>
    </div>
</script>
