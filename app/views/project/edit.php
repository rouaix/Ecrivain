<?php
/* Edit project form. Variables: $project, $errors */
?>
<h2>Modifier le projet</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/edit">
    <div class="form-group">
        <label for="title">Titre *</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title'] ?? ''); ?>"
            required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"
            rows="4"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
    </div>
    <div class="form-group" style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <label for="words_per_page">Mots par page (moyenne)</label>
            <input type="number" id="words_per_page" name="words_per_page"
                value="<?php echo (int) ($project['words_per_page'] ?? 350); ?>" min="1">
        </div>
        <div style="flex: 1;">
            <label for="target_pages">Nombre de pages total</label>
            <input type="number" id="target_pages" name="target_pages"
                value="<?php echo (int) ($project['target_pages'] ?? 0); ?>" min="0">
        </div>
    </div>
    <div class="form-group">
        <label for="target_words">Objectif de mots total</label>
        <input type="number" id="target_words" name="target_words" value="<?php echo (int) $project['target_words']; ?>"
            min="0">
        <small style="color: #666;">Ce champ est calculé automatiquement mais peut être modifié manuellement.</small>
    </div>
    <input type="submit" value="Enregistrer">
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