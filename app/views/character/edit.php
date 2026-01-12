<?php
/* Edit character view. Variables: $project, $character, $errors */
?>
<h2>Modifier le personnage « <?php echo htmlspecialchars($character['name'] ?? ''); ?>»</h2>
<p><a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/characters">Retour à la liste des
        personnages</a></p>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/character/<?php echo $character['id']; ?>/edit">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
    <div class="form-group">
        <label for="name">Nom *</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($character['name'] ?? ''); ?>"
            required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"
            rows="4"><?php echo htmlspecialchars($character['description'] ?? ''); ?></textarea>
    </div>
    <input type="submit" value="Enregistrer">
</form>
