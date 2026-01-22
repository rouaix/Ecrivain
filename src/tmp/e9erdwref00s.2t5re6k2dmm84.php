<h2>
    <?php if ($note['id']): ?>
        Modifier
        <?php else: ?>Créer
    <?php endif; ?> - Note
</h2>
<p>Projet : <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>">
        <?= ($project['title'])."
" ?>
    </a></p>

<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li>
                    <?= ($err)."
" ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= ($base) ?>/project/<?= ($project['id']) ?>/note/save<?= ($note['id'] ? '?id=' . $note['id'] : '') ?>"
    enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="title">Titre (optionnel)</label>
        <input type="text" id="title" name="title" value="<?= ($note['title']) ?>">
    </div>

    <div id="editor-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
        <div class="form-group" style="flex: 1;">
            <label for="content">Contenu</label>
            <div id="editor" style="height: 300px; background: white; color: black;">
                <?= ($this->raw($note['content']))."
" ?>
            </div>
            <input type="hidden" name="content" value="<?= ($this->esc($note['content'])) ?>">
            <p style="margin-top: 10px;">Compteur de mots : <span id="wordCount">0</span></p>
            <div class="editor-tools-wrapper">
                <div id="status" style="display:inline-block; margin-left:10px; color:green;"></div>
                <div id="synonymsBox" class="ai-box"></div>
                <div id="analysisBox" class="ai-box"></div>
            </div>
        </div>

    </div>
    </div>

    <input type="submit" value="Enregistrer">
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>" class="button secondary">Annuler</a>
</form>

<!-- AI Modal Structure -->
<div id="aiModal" class="ai-modal">
    <h3>Proposition IA</h3>
    <textarea id="aiModalText"
        style="width:100%; height:300px; margin-bottom:10px; background:var(--input-bg); color:var(--input-text); border:1px solid var(--input-border);"></textarea>
    <div class="ai-modal-actions">
        <button id="aiBtnInsert" class="button-ai-green">Insérer au curseur</button>
        <button id="aiBtnReplace" class="button-ai-orange">Remplacer la sélection</button>
        <button id="aiBtnCopy">Copier</button>
        <button id="aiBtnClose">Fermer</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        QuillTools.init('#editor', {
            inputSelector: 'input[name="content"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            // Note has no ID context for AI in some cases? Or it does?
            // @note.id might be empty on create.
            contextId: '<?= ($note['id']) ?>',
            contextType: 'note'
        });
    });
</script>