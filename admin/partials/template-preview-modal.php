<div id="pw-template-preview-modal" class="pw-modal">
    <div class="pw-modal-content pw-modal-large" style="height: 80vh; display: flex; flex-direction: column;">
        <div class="pw-modal-header">
            <h3 id="pw-preview-title">Aperçu</h3>
            <button class="pw-modal-close">&times;</button>
        </div>
        
        <div class="pw-preview-toolbar" style="padding: 10px; background: #f0f0f1; border-bottom: 1px solid #ddd; display: flex; gap: 15px; align-items: center;">
            <label>Contexte de simulation : </label>
            <select id="pw-preview-context">
                <option value="male">Homme (Jean Dupont)</option>
                <option value="female">Femme (Marie Curie)</option>
                <option value="company">Entreprise (Contact)</option>
            </select>
        </div>

        <div class="pw-modal-body" style="flex: 1; overflow: hidden; display: flex; flex-direction: column; padding: 0;">
            <div style="padding: 15px; background: #fff; border-bottom: 1px solid #eee;">
                <strong>De :</strong> <span id="pw-preview-from"></span><br>
                <strong>Sujet :</strong> <span id="pw-preview-subject"></span>
            </div>
            <iframe id="pw-preview-frame" style="flex: 1; width: 100%; border: none;"></iframe>
        </div>
    </div>
</div>
