<div id="pw-category-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h3 id="pw-category-modal-title">Gérer les catégories</h3>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <form id="pw-category-form">
                <input type="hidden" name="id" id="pw-category-id">

                <div class="pw-form-group">
                    <label>Nom du dossier</label>
                    <input type="text" name="name" id="pw-category-name" class="widefat" required>
                </div>

                <div class="pw-form-group">
                    <label>Dossier parent</label>
                    <select name="parent_id" id="pw-category-parent" class="widefat">
                        <option value="">Aucun (Racine)</option>
                        <!-- Populated via JS -->
                    </select>
                </div>

                <div class="pw-form-group">
                    <label>Couleur</label>
                    <input type="color" name="color" id="pw-category-color" value="#2271b1" style="width: 100%; height: 40px;">
                </div>
            </form>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button pw-modal-cancel">Annuler</button>
            <button type="button" class="button button-primary" id="pw-save-category-btn">Enregistrer</button>
        </div>
    </div>
</div>
