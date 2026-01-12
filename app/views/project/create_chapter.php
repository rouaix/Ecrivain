<?php
/* Create chapter form. Variables: $project, $errors, $old */
?>
<h2>Ajouter un chapitre au projet « <?php echo htmlspecialchars($project['title']); ?>»</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/chapter/create">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
    <div class="form-group">
        <label for="title">Titre du chapitre *</label>
        <input type="text" id="title" name="title" value="<?php echo $old['title'] ?? ''; ?>" required>
    </div>
    <div class="form-group">
        <label for="act_id">Appartient à l'acte</label>
        <select id="act_id" name="act_id">
            <option value="">-- Aucun (hors actes) --</option>
            <?php foreach ($acts as $act): ?>
                <option value="<?php echo $act['id']; ?>" <?php echo (isset($old['act_id']) && $old['act_id'] == $act['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($act['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="parent_id">Est un sous-chapitre de</label>
        <select id="parent_id" name="parent_id">
            <option value="">-- Aucun (chapitre principal) --</option>
            <?php foreach ($chapters as $ch): ?>
                <option value="<?php echo $ch['id']; ?>" <?php echo (isset($old['parent_id']) && $old['parent_id'] == $ch['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ch['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="submit" value="Créer le chapitre">
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button secondary">Annuler</a>
</form>
