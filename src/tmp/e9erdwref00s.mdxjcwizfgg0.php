<h2>Nouveau projet</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li><?= ($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?= ($base) ?>/project/create">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="title">Titre du projet *</label>
        <input type="text" id="title" name="title" value="<?= ($old['title']) ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= ($old['description']) ?></textarea>
    </div>
    <div class="form-group form-row">
        <div class="form-row__col">
            <label for="words_per_page">Mots par page (moyenne)</label>
            <input type="number" id="words_per_page" name="words_per_page" value="<?= ($old['words_per_page'] ?: 350) ?>"
                min="1">
        </div>
        <div class="form-row__col">
            <label for="target_pages">Nombre de pages total</label>
            <input type="number" id="target_pages" name="target_pages" value="<?= ($old['target_pages'] ?: 0) ?>" min="0">
        </div>
    </div>
    <div class="form-group">
        <label for="target_words">Objectif de mots total</label>
        <input type="number" id="target_words" name="target_words" value="<?= ($old['target_words'] ?: 0) ?>" min="0">
        <small class="text-muted">Ce champ est calculé automatiquement mais peut être modifié manuellement.</small>
    </div>
    <input type="submit" value="Créer le projet">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const wppInput = document.getElementById('words_per_page');
        const tpInput = document.getElementById('target_pages');
        const twInput = document.getElementById('target_words');

        function calculateWords() {
            const wpp = parseInt(wppInput.value) || 0;
            const tp = parseInt(tpInput.value) || 0;
            if (tp > 0) {
                twInput.value = wpp * tp;
            }
        }

        wppInput.addEventListener('input', calculateWords);
        tpInput.addEventListener('input', calculateWords);
    });
</script>
