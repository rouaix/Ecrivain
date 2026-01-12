<?php
/* Act edit form. Variables: $project, $act, $errors (array) */
?>
<h2>Modifier l'acte «
    <?php echo htmlspecialchars($act['title']); ?> »
</h2>
<p>Projet : <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>">
        <?php echo htmlspecialchars($project['title']); ?>
    </a></p>

<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li>
                    <?php echo htmlspecialchars($err); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo $base; ?>/act/<?php echo $act['id']; ?>/edit">
    <div class="form-group">
        <label for="title">Titre de l'acte *</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($act['title']); ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Description / Objectif de l'acte</label>
        <textarea id="description" name="description"
            rows="4"><?php echo htmlspecialchars($act['description'] ?? ''); ?></textarea>
    </div>
    <input type="submit" value="Enregistrer les modifications">
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button-secondary">Annuler</a>
</form>