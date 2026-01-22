<h2>Configuration IA</h2>

<div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
    <?php if ($success): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
            Configuration enregistrée avec succès.
        </div>
    <?php endif; ?>

    <form action="<?= ($base) ?>/ai/config" method="post">

        <div
            style="background: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #eee; margin-bottom: 20px;">
            <div class="form-group">
                <label for="provider" style="display:block; font-weight:bold; margin-bottom:5px;">Fournisseur IA</label>
                <select id="provider" name="provider"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"
                    onchange="updateModels()">
                    <option value="openai" <?= ($config['provider']=='openai' ?'selected':'') ?>>OpenAI</option>
                    <option value="gemini" <?= ($config['provider']=='gemini' ?'selected':'') ?>>Google Gemini</option>
                    <option value="mistral" <?= ($config['provider']=='mistral' ?'selected':'') ?>>Mistral AI</option>
                    <option value="anthropic" <?= ($config['provider']=='anthropic' ?'selected':'') ?>>Anthropic (Claude)
                    </option>
                </select>
            </div>

            <div class="form-group" style="margin-top:15px;">
                <label for="api_key" style="display:block; font-weight:bold; margin-bottom:5px;">Clé API</label>
                <input type="password" id="api_key" name="api_key" value="<?= ($config['api_key']) ?>"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;" placeholder="sk-...">
                <small style="color:#666;">La clé ne sera pas affichée après enregistrement pour sécurité.</small>
            </div>

            <div class="form-group" style="margin-top:15px;">
                <label for="model" style="display:block; font-weight:bold; margin-bottom:5px;">Modèle</label>
                <select id="model" name="model"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <!-- Populated by JS -->
                </select>
            </div>
        </div>

        <script>
            const models = <?= ($this->raw(json_encode($models))) ?>;
            const currentModel = "<?= ($config['model']) ?>";

            function updateModels() {
                const provider = document.getElementById('provider').value;
                const modelSelect = document.getElementById('model');
                modelSelect.innerHTML = '';

                const providerModels = models[provider] || [];
                providerModels.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    if (m === currentModel) opt.selected = true;
                    modelSelect.appendChild(opt);
                });
            }

            // Init
            document.addEventListener('DOMContentLoaded', updateModels);
        </script>

        <div class="form-group">
            <label for="system" style="display:block; font-weight:bold; margin-bottom:5px;">Rôle Système (Instructions
                Globales)</label>
            <textarea id="system" name="system" rows="3"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= ($config['system']) ?></textarea>
            <small style="color:#666;">Définit la personnalité et le comportement général de l'IA.</small>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="continue" style="display:block; font-weight:bold; margin-bottom:5px;">Consigne
                "Continuer"</label>
            <textarea id="continue" name="continue" rows="3"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= ($config['continue']) ?></textarea>
            <small style="color:#666;">Instructions ajoutées lors de la demande de continuation de texte.</small>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="rephrase" style="display:block; font-weight:bold; margin-bottom:5px;">Consigne
                "Reformuler"</label>
            <textarea id="rephrase" name="rephrase" rows="3"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= ($config['rephrase']) ?></textarea>
            <small style="color:#666;">Instructions ajoutées lors de la demande de reformulation.</small>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="summarize_chapter" style="display:block; font-weight:bold; margin-bottom:5px;">Consigne "Résumé
                Chapitre"</label>
            <textarea id="summarize_chapter" name="summarize_chapter" rows="3"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= ($config['summarize_chapter']) ?></textarea>
            <small style="color:#666;">Instructions pour la génération du résumé d'un chapitre (basé sur ses
                sous-chapitres).</small>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="summarize_act" style="display:block; font-weight:bold; margin-bottom:5px;">Consigne "Résumé
                Acte"</label>
            <textarea id="summarize_act" name="summarize_act" rows="3"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= ($config['summarize_act']) ?></textarea>
            <small style="color:#666;">Instructions pour la génération du résumé d'un acte (basé sur les résumés de ses
                chapitres).</small>
        </div>

        <div style="margin-top: 30px; display:flex; gap:10px;">
            <button type="submit" class="button primary"
                style="background-color: #673AB7; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Enregistrer</button>
            <a href="<?= ($base) ?>/dashboard" class="button secondary"
                style="padding: 10px 20px; text-decoration: none; color: #333; border: 1px solid #ccc; border-radius: 4px;">Retour</a>
        </div>
    </form>
</div>