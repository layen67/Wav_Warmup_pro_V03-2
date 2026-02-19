/**
 * admin/assets/js/scenarios-manager.js
 * Gestionnaire UI pour les Scénarios et Règles de Réponse
 */

(function($) {
    'use strict';

    const ScenarioManager = {
        init() {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents() {
            // New Scenario
            $('#pw-new-scenario-btn').on('click', (e) => {
                e.preventDefault();
                this.openEditor();
            });

            // Edit Scenario
            $(document).on('click', '.pw-edit-scenario-btn', (e) => {
                e.preventDefault();
                const $row = $(e.currentTarget).closest('tr');
                const data = $row.data('scenario'); // Assumes data attribute is populated in PHP
                this.openEditor(data);
            });

            // Delete Scenario
            $(document).on('click', '.pw-delete-scenario-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                if (confirm('Êtes-vous sûr de vouloir supprimer ce scénario ?')) {
                    this.deleteScenario(id);
                }
            });

            // Save Scenario
            $('#pw-save-scenario-btn').on('click', (e) => {
                e.preventDefault();
                this.saveScenario();
            });

            // Add Step
            $('#pw-add-step-btn').on('click', (e) => {
                e.preventDefault();
                this.addStep();
            });

            // Remove Step
            $(document).on('click', '.pw-remove-step-btn', (e) => {
                e.preventDefault();
                $(e.currentTarget).closest('.pw-scenario-step').remove();
                this.updateStepNumbers();
            });
        },

        initSortable() {
            $('#pw-scenario-steps-container').sortable({
                handle: '.pw-step-handle',
                update: () => this.updateStepNumbers()
            });
        },

        openEditor(data = null) {
            const $modal = $('#pw-scenario-editor-modal');
            const $form = $('#pw-scenario-form');
            const $steps = $('#pw-scenario-steps-container');

            $form[0].reset();
            $steps.empty();
            $('#pw-scenario-id').val('');

            if (data) {
                $('#pw-scenario-modal-title').text('Modifier le Scénario');
                $('#pw-scenario-id').val(data.id);
                $('#pw-scenario-name').val(data.name);
                $('#pw-scenario-trigger').val(data.trigger_event);
                $('#pw-scenario-active').prop('checked', data.active == 1);

                if (data.steps && Array.isArray(data.steps)) {
                    data.steps.forEach(step => this.addStep(step));
                }
            } else {
                $('#pw-scenario-modal-title').text('Nouveau Scénario');
                this.addStep(); // Default empty step
            }

            $modal.show();
        },

        addStep(data = null) {
            const index = $('#pw-scenario-steps-container .pw-scenario-step').length + 1;
            const template = _.template($('#pw-step-item-template').html());

            const html = template({
                index: index,
                delay: data ? data.delay : 1,
                template_name: data ? data.template : ''
            });

            $('#pw-scenario-steps-container').append(html);
        },

        updateStepNumbers() {
            $('#pw-scenario-steps-container .pw-scenario-step').each(function(idx) {
                $(this).find('.pw-step-number').text(idx + 1);
            });
        },

        async saveScenario() {
            const $btn = $('#pw-save-scenario-btn');
            $btn.prop('disabled', true).text('Sauvegarde...');

            const steps = [];
            $('#pw-scenario-steps-container .pw-scenario-step').each(function() {
                steps.push({
                    delay: $(this).find('.pw-step-delay').val(),
                    template: $(this).find('.pw-step-template').val()
                });
            });

            const data = {
                action: 'pw_save_scenario',
                nonce: pwAdmin.nonce,
                id: $('#pw-scenario-id').val(),
                name: $('#pw-scenario-name').val(),
                trigger_event: $('#pw-scenario-trigger').val(),
                active: $('#pw-scenario-active').is(':checked') ? 1 : 0,
                steps: JSON.stringify(steps)
            };

            try {
                const response = await $.post(pwAdmin.ajaxurl, data);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Erreur lors de la sauvegarde');
                    $btn.prop('disabled', false).text('Enregistrer');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur réseau');
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
                if (response.success) location.reload();
                else alert(response.data.message);
            } catch (error) {
                console.error(error);
            }
        }
    };

    const ReplyRulesManager = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $('#pw-new-rule-btn').on('click', (e) => {
                e.preventDefault();
                this.openEditor();
            });

            $(document).on('click', '.pw-edit-rule-btn', (e) => {
                e.preventDefault();
                const data = $(e.currentTarget).closest('tr').data('rule');
                this.openEditor(data);
            });

            $(document).on('click', '.pw-delete-rule-btn', (e) => {
                e.preventDefault();
                const id = $(e.currentTarget).data('id');
                if (confirm('Supprimer cette règle ?')) this.deleteRule(id);
            });

            $('#pw-save-rule-btn').on('click', (e) => {
                e.preventDefault();
                this.saveRule();
            });
        },

        openEditor(data = null) {
            const $modal = $('#pw-rule-editor-modal');
            const $form = $('#pw-rule-form');

            $form[0].reset();
            $('#pw-rule-id').val('');

            if (data) {
                $('#pw-rule-modal-title').text('Modifier la Règle');
                $('#pw-rule-id').val(data.id);
                $('#pw-rule-name').val(data.name);
                $('#pw-rule-prefix').val(data.match_prefix);
                $('#pw-rule-template').val(data.response_template_name);
                $('#pw-rule-active').prop('checked', data.active == 1);
            } else {
                $('#pw-rule-modal-title').text('Nouvelle Règle');
            }

            $modal.show();
        },

        async saveRule() {
            const $btn = $('#pw-save-rule-btn');
            $btn.prop('disabled', true);

            const data = {
                action: 'pw_save_reply_rule',
                nonce: pwAdmin.nonce,
                id: $('#pw-rule-id').val(),
                name: $('#pw-rule-name').val(),
                match_prefix: $('#pw-rule-prefix').val(),
                response_template_name: $('#pw-rule-template').val(),
                active: $('#pw-rule-active').is(':checked') ? 1 : 0
            };

            try {
                const response = await $.post(pwAdmin.ajaxurl, data);
                if (response.success) location.reload();
                else {
                    alert(response.data.message);
                    $btn.prop('disabled', false);
                }
            } catch (error) {
                console.error(error);
                $btn.prop('disabled', false);
            }
        }
    };

    $(document).ready(() => {
        if ($('#pw-scenarios-page').length) {
            ScenarioManager.init();
        }
        if ($('#pw-reply-rules-page').length) {
            ReplyRulesManager.init();
        }

        // Generic modal close
        $('.pw-modal-close, .pw-modal-cancel').on('click', function() {
            $(this).closest('.pw-modal').hide();
        });
    });

})(jQuery);
