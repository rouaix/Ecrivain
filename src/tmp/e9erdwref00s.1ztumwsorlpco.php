<h2>Bonjour <?= ($user['username']) ?>!</h2>
<p>Voici la liste de vos projets d’écriture. Vous pouvez créer un nouveau projet ou gérer les projets existants.</p>
<a class="button" href="<?= ($base) ?>/project/create">Nouveau projet</a>
<a class="button" href="<?= ($base) ?>/profile">Mon Profil</a>
<a class="button" href="<?= ($base) ?>/ai/config" class="button-ai-purple">Configuration IA</a>
<a class="button" href="<?= ($base) ?>/ai/usage"
    style="background-color: var(--button-secondary-bg); color: var(--button-text);">Consommation IA</a>
</p>
<?php if (empty($projects)): ?>
    
        <p>Vous n’avez aucun projet pour le moment.</p>
    
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Objectif (mots)</th>
                    <th>Pages estimées</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($projects?:[]) as $proj): ?>
                    <tr>
                        <td><?= ($proj['title']) ?></td>
                        <td><?= ($proj['target_words']) ?></td>
                        <td><?= ($proj['pages_count']) ?></td>
                        <td>
                            <a class="button" href="<?= ($base) ?>/project/<?= ($proj['id']) ?>">Ouvrir</a>
                            <a class="button" href="<?= ($base) ?>/project/<?= ($proj['id']) ?>/edit">Modifier</a>
                            <a class="button delete" style="display: none;"
                                href="<?= ($base) ?>/project/<?= ($proj['id']) ?>/delete"
                                onclick="return confirm('Supprimer ce projet ?');">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    
<?php endif; ?>

<div class="card-panel">
    <h3>Accès rapide</h3>
    <p>Générer un lien d'auto-login pour votre compte.</p>
    <button id="btn-generate-token" class="button" onclick="generateToken()">Générer un lien</button>

    <div style="margin-top: 20px;">
        <h4>Vos liens actifs</h4>
        <div id="token-list" style="margin-top: 10px;">
            <p>Chargement...</p>
        </div>
    </div>
</div>

<div class="card-panel">
    <h3>Apparence</h3>
    <p>Choisissez le thème de l'interface.</p>
    <form action="<?= ($base) ?>/theme" method="post" style="display: flex; flex-wrap: wrap; gap: 10px;">
        <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">

        <!-- Light Themes -->
        <button type="submit" name="theme" value="default" class="button"
            style="background-color: #3f51b5; color: white;">Défaut (Bleu)</button>
        <button type="submit" name="theme" value="modern" class="button"
            style="background-color: #009688; color: white;">Moderne (Vert)</button>
        <button type="submit" name="theme" value="paper" class="button"
            style="background-color: #8d6e63; color: white;">Papier (Sépia)</button>

        <!-- Dark Themes -->
        <button type="submit" name="theme" value="dark" class="button"
            style="background-color: #333; color: white; border: 1px solid #555;">Sombre (Gris)</button>
        <button type="submit" name="theme" value="midnight" class="button"
            style="background-color: #0f172a; color: white; border: 1px solid #334155;">Minuit (Bleu)</button>
        <button type="submit" name="theme" value="deep" class="button"
            style="background-color: #000; color: white; border: 1px solid #333;">Profond (Noir)</button>
    </form>
</div>

<script>
    async function loadTokens() {
        const listDiv = document.getElementById('token-list');
        try {
            const response = await fetch('<?= ($base) ?>/auth/tokens');
            const data = await response.json();

            if (!data.tokens || data.tokens.length === 0) {
                listDiv.innerHTML = '<p style="color: #666;">Aucun lien actif.</p>';
                return;
            }

            let html = '<ul style="list-style: none; padding: 0;">';
            data.tokens.forEach(t => {
                const link = window.location.origin + '<?= ($base) ?>/?token=' + t.token;
                html += `
                <li class="token-item">
                    <div style="flex-grow: 1; margin-right: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <input type="text" value="${link}" readonly onclick="this.select()" class="token-input">
                        <div style="color: var(--text-muted); font-size: 0.8em;">Créé le ${t.created_at}</div>
                    </div>
                    <div style="display:flex; gap: 5px;">
                        <button onclick="navigator.clipboard.writeText('${link}')" class="button" style="padding: 4px 10px; font-size: 0.8em; margin: 0;">Copier</button>
                        <button onclick="revokeToken('${t.token}')" class="button delete" style="padding: 4px 10px; font-size: 0.8em; margin: 0;">Supprimer</button>
                    </div>
                </li>
            `;
            });
            html += '</ul>';
            listDiv.innerHTML = html;

        } catch (e) {
            listDiv.innerHTML = '<p style="color: red;">Erreur de chargement.</p>';
            console.error(e);
        }
    }

    async function generateToken() {
        const btn = document.getElementById('btn-generate-token');
        btn.disabled = true;
        btn.textContent = 'Génération...';

        try {
            const response = await fetch('<?= ($base) ?>/auth/token/generate', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '<?= ($csrfToken) ?>' }
            });
            if (!response.ok) throw new Error('Erreur');

            await loadTokens(); // Refresh list

        } catch (e) {
            alert('Erreur: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Générer un lien';
        }
    }

    async function revokeToken(token) {
        if (!confirm('Supprimer ce lien ?')) return;

        try {
            const response = await fetch('<?= ($base) ?>/auth/token/revoke', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?= ($csrfToken) ?>'
                },
                body: JSON.stringify({ token: token })
            });

            if (!response.ok) throw new Error('Erreur');
            await loadTokens(); // Refresh list

        } catch (e) {
            alert('Impossible de supprimer: ' + e.message);
        }
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', loadTokens);
</script>