<div class="wrap postal-warmup-wrap">
	<h1 class="wp-heading-inline"><?php _e( 'Scénarios de Conversation', 'postal-warmup' ); ?></h1>
	<button id="pw-add-scenario" class="page-title-action"><?php _e( 'Ajouter un Scénario', 'postal-warmup' ); ?></button>
	<hr class="wp-header-end">

	<div class="pw-container">
		<!-- List View -->
		<div id="pw-scenarios-list" class="pw-card">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th width="50"><?php _e( 'ID', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Nom', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Trigger', 'postal-warmup' ); ?></th>
						<th><?php _e( 'Étapes', 'postal-warmup' ); ?></th>
						<th width="80"><?php _e( 'Priorité', 'postal-warmup' ); ?></th>
						<th width="80"><?php _e( 'Actif', 'postal-warmup' ); ?></th>
						<th width="150"><?php _e( 'Actions', 'postal-warmup' ); ?></th>
					</tr>
				</thead>
				<tbody id="pw-scenarios-tbody">
					<tr><td colspan="7"><?php _e( 'Chargement...', 'postal-warmup' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<!-- Edit View (Hidden) -->
		<div id="pw-scenario-editor" class="pw-card" style="display:none;">
			<h2 id="pw-editor-title"><?php _e( 'Éditer le Scénario', 'postal-warmup' ); ?></h2>
			<form id="pw-scenario-form">
				<input type="hidden" name="id" id="scenario_id" value="">

				<div class="pw-form-row">
					<label><?php _e( 'Nom', 'postal-warmup' ); ?></label>
					<input type="text" name="name" id="scenario_name" required class="regular-text">
				</div>

				<div class="pw-form-row">
					<label><?php _e( 'Description', 'postal-warmup' ); ?></label>
					<textarea name="description" id="scenario_description" class="large-text" rows="2"></textarea>
				</div>

				<div class="pw-grid-2">
					<div class="pw-form-row">
						<label><?php _e( 'Priorité', 'postal-warmup' ); ?></label>
						<input type="number" name="priority" id="scenario_priority" value="10">
					</div>
					<div class="pw-form-row">
						<label><?php _e( 'Statut', 'postal-warmup' ); ?></label>
						<select name="active" id="scenario_active">
							<option value="1"><?php _e( 'Actif', 'postal-warmup' ); ?></option>
							<option value="0"><?php _e( 'Inactif', 'postal-warmup' ); ?></option>
						</select>
					</div>
				</div>

				<h3><?php _e( 'Étapes du Scénario', 'postal-warmup' ); ?></h3>
				<div id="pw-steps-container">
					<!-- Steps rendered via JS -->
				</div>
				<button type="button" class="button" id="pw-add-step"><?php _e( '+ Ajouter une étape', 'postal-warmup' ); ?></button>

				<div class="pw-actions-bar">
					<button type="submit" class="button button-primary"><?php _e( 'Enregistrer', 'postal-warmup' ); ?></button>
					<button type="button" class="button" id="pw-cancel-edit"><?php _e( 'Annuler', 'postal-warmup' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/template" id="tmpl-pw-step">
	<div class="pw-step-item" data-index="{{index}}">
		<div class="pw-step-header">
			<span class="pw-step-title"><?php _e( 'Étape', 'postal-warmup' ); ?> #{{displayIndex}}</span>
			<button type="button" class="pw-remove-step dashicons dashicons-trash"></button>
		</div>
		<div class="pw-step-body">
			<div class="pw-form-row">
				<label><?php _e( 'Action', 'postal-warmup' ); ?></label>
				<select name="steps[{{index}}][action]" class="pw-step-action">
					<option value="send_email" {{action_send_email}}><?php _e( 'Envoyer Email', 'postal-warmup' ); ?></option>
					<option value="wait" {{action_wait}}><?php _e( 'Attendre', 'postal-warmup' ); ?></option>
				</select>
			</div>
			<div class="pw-form-row pw-step-template">
				<label><?php _e( 'Template', 'postal-warmup' ); ?></label>
				<input type="text" name="steps[{{index}}][template]" value="{{template}}" placeholder="template-slug">
			</div>
			<div class="pw-form-row">
				<label><?php _e( 'Délai (heures après étape préc.)', 'postal-warmup' ); ?></label>
				<input type="number" name="steps[{{index}}][delay]" value="{{delay}}">
			</div>
		</div>
	</div>
</script>
