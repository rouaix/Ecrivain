<?php
/* Create character form. Variables: $project, $errors, $old */
?>
<h2>Ajouter un personnage au projet « <?php echo htmlspecialchars($project['title']); ?>»</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/character/create">
    <div class="form-group">
        <label for="name">Nom *</label>
        <input type="text" id="name" name="name" value="<?php echo $old['name'] ?? ''; ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?php echo $old['description'] ?? ''; ?></textarea>
    </div>
    <input type="submit" value="Créer le personnage">
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button secondary">Annuler</a>
</form>