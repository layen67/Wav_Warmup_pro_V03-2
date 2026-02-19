/**
 * admin/assets/js/scenarios-manager.js
 * Gestionnaire UI pour les Scénarios et Règles de Réponse
 */

(function($) {
    'use strict';

    const ScenarioManager = {
        init() {
            if ($('#pw-scenarios-list').length === 0) return;

            this.loadScenarios();
            this.bindEvents();
            this.initSortable();
        },

        bindEvents() {
            // Toggle Views
            $('#pw-add-scenario').on('click', (e) => {
                e.preventDefault();
                this.openEditor();
            });

            $('#pw-cancel-edit').on('click', (e) => {
                e.preventDefault();
                this.closeEditor();
            });

            // Edit Scenario
            $(document).on('click', '.pw-edit-scenario-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                this.loadScenarioDetails(id);
            });

            // Delete Scenario
            $(document).on('click', '.pw-delete-scenario-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                if (confirm(pwAdmin.confirm_delete)) {
                    this.deleteScenario(id);
                }
            });

            // Save Scenario
            $('#pw-scenario-form').on('submit', (e) => {
                e.preventDefault();
                this.saveScenario();
            });

            // Add Step
            $('#pw-add-step').on('click', (e) => {
                e.preventDefault();
                this.addStep();
            });

            // Remove Step
            $(document).on('click', '.pw-remove-step', (e) => {
                e.preventDefault();
                if (confirm('Supprimer cette étape ?')) {
                    $(e.currentTarget).closest('.pw-step-item').remove();
                    this.updateStepNumbers();
                }
            });

            // Conditional Logic for Step Action
            $(document).on('change', '.pw-step-action', (e) => {
                const action = $(e.target).val();
                const $item = $(e.target).closest('.pw-step-item');
                if (action === 'send_email') {
                    $item.find('.pw-step-template').show();
                } else {
                    $item.find('.pw-step-template').hide();
                }
            });
        },

        initSortable() {
            $('#pw-steps-container').sortable({
                handle: '.pw-step-header',
                placeholder: 'pw-step-placeholder',
                update: () => this.updateStepNumbers()
            });
        },

        loadScenarios() {
            const $tbody = $('#pw-scenarios-tbody');
            $tbody.html('<tr><td colspan="7">Chargement...</td></tr>');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_all_scenarios',
                nonce: pwAdmin.nonce
            }, (response) => {
                if (response.success) {
                    this.renderList(response.data.scenarios);
                } else {
                    $tbody.html('<tr><td colspan="7">Erreur de chargement.</td></tr>');
                }
            });
        },

        renderList(scenarios) {
            const $tbody = $('#pw-scenarios-tbody');
            $tbody.empty();

            if (!scenarios || scenarios.length === 0) {
                $tbody.html('<tr><td colspan="7">Aucun scénario trouvé.</td></tr>');
                return;
            }

            scenarios.forEach(item => {
                const statusBadge = item.active == 1
                    ? '<span class="pw-badge pw-badge-success">Actif</span>'
                    : '<span class="pw-badge pw-badge-neutral">Inactif</span>';

                const stepsCount = item.steps ? item.steps.length : 0;

                const html = `
                    <tr>
                        <td>${item.id}</td>
                        <td><strong>${item.name}</strong><br><small>${item.description || ''}</small></td>
                        <td><code>${item.trigger_event}</code></td>
                        <td>${stepsCount}</td>
                        <td>${item.priority}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="button pw-edit-scenario-btn" data-id="${item.id}">Modifier</button>
                            <button class="button pw-delete-scenario-btn" data-id="${item.id}">Supprimer</button>
                        </td>
                    </tr>
                `;
                $tbody.append(html);
            });
        },

        loadScenarioDetails(id) {
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_scenario',
                nonce: pwAdmin.nonce,
                id: id
            }, (response) => {
                if (response.success) {
                    this.openEditor(response.data);
                } else {
                    alert('Erreur: ' + response.data.message);
                }
            });
        },

        openEditor(data = null) {
            $('#pw-scenarios-list').hide();
            $('#pw-add-scenario').hide();
            $('#pw-scenario-editor').show();

            // Reset Form
            $('#pw-scenario-form')[0].reset();
            $('#pw-steps-container').empty();
            $('#scenario_id').val('');

            if (data) {
                $('#pw-editor-title').text('Modifier le Scénario #' + data.id);
                $('#scenario_id').val(data.id);
                $('#scenario_name').val(data.name);
                $('#scenario_description').val(data.description);
                $('#scenario_priority').val(data.priority);
                $('#scenario_active').val(data.active);

                if (data.steps && Array.isArray(data.steps)) {
                    data.steps.forEach(step => this.addStep(step));
                }
            } else {
                $('#pw-editor-title').text('Nouveau Scénario');
                this.addStep(); // Default one step
            }
        },

        closeEditor() {
            $('#pw-scenario-editor').hide();
            $('#pw-scenarios-list').show();
            $('#pw-add-scenario').show();
        },

        addStep(data = null) {
            const index = $('#pw-steps-container .pw-step-item').length;
            const template = _.template($('#tmpl-pw-step').html());

            const compiled = template({
                index: index,
                displayIndex: index + 1,
                template: data ? data.template : '',
                delay: data ? data.delay : (index === 0 ? 0 : 24),
                action_send_email: (data && data.action === 'send_email') ? 'selected' : '',
                action_wait: (data && data.action === 'wait') ? 'selected' : ''
            });

            const $el = $(compiled);
            $('#pw-steps-container').append($el);

            // Initial state trigger
            if (data && data.action === 'wait') {
                $el.find('.pw-step-template').hide();
            }
        },

        updateStepNumbers() {
            $('#pw-steps-container .pw-step-item').each(function(idx) {
                $(this).find('.pw-step-title').text('Étape #' + (idx + 1));
                $(this).attr('data-index', idx);
                // Update input names if needed, but we rely on simple serialization or re-indexing on save
                // For robustness, we'll re-index names
                $(this).find('select, input').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/steps\[\d+\]/, `steps[${idx}]`));
                    }
                });
            });
        },

        async saveScenario() {
            const $btn = $('#pw-scenario-form button[type="submit"]');
            $btn.prop('disabled', true).text('Enregistrement...');

            const formData = $('#pw-scenario-form').serializeArray();
            // Process steps into JSON if needed, or let PHP handle the array
            // PHP handles array directly via $_POST['steps']

            // Add action/nonce
            formData.push({ name: 'action', value: 'pw_save_scenario' });
            formData.push({ name: 'nonce', value: pwAdmin.nonce });

            try {
                const response = await $.post(pwAdmin.ajaxurl, formData);
                if (response.success) {
                    this.closeEditor();
                    this.loadScenarios();
                } else {
                    alert(response.data.message || 'Erreur lors de la sauvegarde');
                }
            } catch (error) {
                console.error(error);
                alert('Erreur réseau');
            } finally {
                $btn.prop('disabled', false).text('Enregistrer');
            }
        },

        async deleteScenario(id) {
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_delete_scenario',
                    nonce: pwAdmin.nonce,
                    id: id
                });
                if (response.success) {
                    this.loadScenarios();
                } else {
                    alert(response.data.message);
                }
            } catch (error) {
                console.error(error);
            }
        }
    };

    const ReplyRulesManager = {
        init() {
            if ($('#pw-rules-list').length === 0) return;

            this.loadRules();
            this.bindEvents();
        },

        bindEvents() {
            $('#pw-add-rule').on('click', (e) => {
                e.preventDefault();
                this.openEditor();
            });

            $('#pw-cancel-rule-edit').on('click', (e) => {
                e.preventDefault();
                this.closeEditor();
            });

            $(document).on('click', '.pw-edit-rule-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                this.loadRuleDetails(id);
            });

            $(document).on('click', '.pw-delete-rule-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                if (confirm(pwAdmin.confirm_delete)) this.deleteRule(id);
            });

            $('#pw-rule-form').on('submit', (e) => {
                e.preventDefault();
                this.saveRule();
            });
        },

        loadRules() {
            const $tbody = $('#pw-rules-tbody');
            $tbody.html('<tr><td colspan="8">Chargement...</td></tr>');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_all_reply_rules',
                nonce: pwAdmin.nonce
            }, (response) => {
                if (response.success) {
                    this.renderList(response.data.rules);
                } else {
                    $tbody.html('<tr><td colspan="8">Erreur.</td></tr>');
                }
            });
        },

        renderList(rules) {
            const $tbody = $('#pw-rules-tbody');
            $tbody.empty();

            if (!rules || rules.length === 0) {
                $tbody.html('<tr><td colspan="8">Aucune règle trouvée.</td></tr>');
                return;
            }

            rules.forEach(item => {
                const statusBadge = item.active == 1
                    ? '<span class="pw-badge pw-badge-success">Actif</span>'
                    : '<span class="pw-badge pw-badge-neutral">Inactif</span>';

                let conditions = [];
                if (item.match_server_id) conditions.push(`Server: ${item.match_server_id}`);
                if (item.match_prefix) conditions.push(`Prefix: ${item.match_prefix}`);
                if (item.match_subject_contains) conditions.push(`Subject: ${item.match_subject_contains}`);

                const html = `
                    <tr>
                        <td>${item.id}</td>
                        <td><strong>${item.name}</strong></td>
                        <td><small>${conditions.join('<br>') || 'Toutes les réponses'}</small></td>
                        <td>${item.response_template_name}</td>
                        <td>${item.scenario_id || '-'}</td>
                        <td>${item.priority}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="button pw-edit-rule-btn" data-id="${item.id}">Modifier</button>
                            <button class="button pw-delete-rule-btn" data-id="${item.id}">Supprimer</button>
                        </td>
                    </tr>
                `;
                $tbody.append(html);
            });
        },

        loadRuleDetails(id) {
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_reply_rule',
                nonce: pwAdmin.nonce,
                id: id
            }, (response) => {
                if (response.success) {
                    this.openEditor(response.data);
                } else {
                    alert('Erreur: ' + response.data.message);
                }
            });
        },

        openEditor(data = null) {
            $('#pw-rules-list').hide();
            $('#pw-add-rule').hide();
            $('#pw-rule-editor').show();

            $('#pw-rule-form')[0].reset();
            $('#rule_id').val('');

            if (data) {
                $('#pw-rule-editor-title').text('Modifier la Règle #' + data.id);
                $('#rule_id').val(data.id);
                $('#rule_name').val(data.name);
                $('#rule_match_server_id').val(data.match_server_id);
                $('#rule_match_prefix').val(data.match_prefix);
                $('#rule_match_subject_contains').val(data.match_subject_contains);
                $('#rule_match_body_contains').val(data.match_body_contains);
                $('#rule_response_template_name').val(data.response_template_name);
                $('#rule_scenario_id').val(data.scenario_id);
                $('#rule_priority').val(data.priority);
                $('#rule_active').val(data.active);
            } else {
                $('#pw-rule-editor-title').text('Nouvelle Règle');
            }
        },

        closeEditor() {
            $('#pw-rule-editor').hide();
            $('#pw-rules-list').show();
            $('#pw-add-rule').show();
        },

        async saveRule() {
            const $btn = $('#pw-rule-form button[type="submit"]');
            $btn.prop('disabled', true);

            const formData = $('#pw-rule-form').serializeArray();
            formData.push({ name: 'action', value: 'pw_save_reply_rule' });
            formData.push({ name: 'nonce', value: pwAdmin.nonce });

            try {
                const response = await $.post(pwAdmin.ajaxurl, formData);
                if (response.success) {
                    this.closeEditor();
                    this.loadRules();
                } else {
                    alert(response.data.message);
                }
            } catch (error) {
                console.error(error);
            } finally {
                $btn.prop('disabled', false);
            }
        }
    };

    $(document).ready(() => {
        ScenarioManager.init();
        ReplyRulesManager.init();
    });

})(jQuery);
