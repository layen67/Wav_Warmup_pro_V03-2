<div class="wrap postal-warmup-wrap">
	<h1 class="wp-heading-inline"><?php _e( 'Règles de Réponse Automatique', 'postal-warmup' ); ?></h1>
	<button id="pw-add-rule" class="page-title-action"><?php _e( 'Ajouter une Règle', 'postal-warmup' ); ?></button>
	<hr class="wp-header-end">

	<div class="pw-container">
		<!-- List View -->
		<div id="pw-rules-list" class="pw-card">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th width="50"><?php _e( 'ID', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Nom', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Conditions', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Réponse (Template)', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Scénario Déclenché', 'postal-warmup' ); ?></th>
						<th width="80"><?php _e( 'Priorité', 'postal-warmup' ); ?></th>
						<th width="80"><?php _e( 'Actif', 'postal-warmup' ); ?></th>
						<th width="150"><?php _e( 'Actions', 'postal-warmup' ); ?></th>
					</tr>
				</thead>
				<tbody id="pw-rules-tbody">
					<tr><td colspan="8"><?php _e( 'Chargement...', 'postal-warmup' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<!-- Edit View (Hidden) -->
		<div id="pw-rule-editor" class="pw-card" style="display:none;">
			<h2 id="pw-rule-editor-title"><?php _e( 'Éditer la Règle', 'postal-warmup' ); ?></h2>
			<form id="pw-rule-form">
				<input type="hidden" name="id" id="rule_id" value="">

				<div class="pw-form-row">
					<label><?php _e( 'Nom de la règle', 'postal-warmup' ); ?></label>
					<input type="text" name="name" id="rule_name" required class="regular-text">
				</div>

				<div class="pw-section-title"><?php _e( 'Conditions de Déclenchement (ET)', 'postal-warmup' ); ?></div>

				<div class="pw-grid-2">
					<div class="pw-form-row">
						<label><?php _e( 'Serveur ID (Optionnel)', 'postal-warmup' ); ?></label>
						<input type="number" name="match_server_id" id="rule_match_server_id" placeholder="Ex: 1">
						<p class="description"><?php _e( 'Laisser vide pour tous les serveurs.', 'postal-warmup' ); ?></p>
					</div>
					<div class="pw-form-row">
						<label><?php _e( 'Préfixe Email (Optionnel)', 'postal-warmup' ); ?></label>
						<input type="text" name="match_prefix" id="rule_match_prefix" placeholder="Ex: contact">
					</div>
				</div>

				<div class="pw-form-row">
					<label><?php _e( 'Sujet contient (Optionnel)', 'postal-warmup' ); ?></label>
					<input type="text" name="match_subject_contains" id="rule_match_subject_contains" class="large-text">
				</div>

				<div class="pw-form-row">
					<label><?php _e( 'Corps contient (Optionnel)', 'postal-warmup' ); ?></label>
					<input type="text" name="match_body_contains" id="rule_match_body_contains" class="large-text">
				</div>

				<div class="pw-section-title"><?php _e( 'Actions', 'postal-warmup' ); ?></div>

				<div class="pw-form-row">
					<label><?php _e( 'Template de Réponse', 'postal-warmup' ); ?></label>
					<input type="text" name="response_template_name" id="rule_response_template_name" required placeholder="template-slug">
				</div>

				<div class="pw-form-row">
					<label><?php _e( 'Démarrer Scénario (ID)', 'postal-warmup' ); ?></label>
					<input type="number" name="scenario_id" id="rule_scenario_id" placeholder="Ex: 5">
				</div>

				<div class="pw-grid-2">
					<div class="pw-form-row">
						<label><?php _e( 'Priorité', 'postal-warmup' ); ?></label>
						<input type="number" name="priority" id="rule_priority" value="10">
					</div>
					<div class="pw-form-row">
						<label><?php _e( 'Statut', 'postal-warmup' ); ?></label>
						<select name="active" id="rule_active">
							<option value="1"><?php _e( 'Actif', 'postal-warmup' ); ?></option>
							<option value="0"><?php _e( 'Inactif', 'postal-warmup' ); ?></option>
						</select>
					</div>
				</div>

				<div class="pw-actions-bar">
					<button type="submit" class="button button-primary"><?php _e( 'Enregistrer', 'postal-warmup' ); ?></button>
					<button type="button" class="button" id="pw-cancel-rule-edit"><?php _e( 'Annuler', 'postal-warmup' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
