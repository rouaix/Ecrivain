<?php
/* Act creation form. Variables: $project, $errors (array), $old (array) */
?>
<h2>Nouvel acte</h2>
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

<form method="post" action="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/act/create">
    <div class="form-group">
        <label for="title">Titre de l'acte *</label>
        <input type="text" id="title" name="title" value="<?php echo $old['title'] ?? ''; ?>" required
            placeholder="Ex: Acte 1, Partie 1...">
    </div>
    <div class="form-group">
        <label for="description">Description / Objectif de l'acte</label>
        <textarea id="description" name="description" rows="4"><?php echo $old['description'] ?? ''; ?></textarea>
    </div>
    <input type="submit" value="Créer l'acte">
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button secondary">Annuler</a>
</form>