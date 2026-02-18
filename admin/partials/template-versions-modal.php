<div id="pw-template-versions-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h3>Historique des versions</h3>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <ul id="pw-versions-list" class="pw-versions-list">
                <!-- Populated via JS -->
            </ul>
        </div>
    </div>
</div>

<script type="text/html" id="pw-version-item-template">
    <li class="pw-version-item">
        <div class="pw-version-meta">
            <strong>v<%- version_number %></strong> - <%- created_at %>
            <span class="pw-version-author">par <%- author_name %></span>
        </div>
        <% if (comment) { %>
        <div class="pw-version-comment"><em><%- comment %></em></div>
        <% } %>
        <button type="button" class="button button-small pw-restore-version-btn" data-id="<%- id %>">Restaurer</button>
    </li>
</script>
