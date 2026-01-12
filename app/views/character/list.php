<?php
/* Characters list view. Variables: $project, $characters */
?>
<h2>Personnages du projet « <?php echo htmlspecialchars($project['title'] ?? ''); ?>»</h2>
<p><a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>">Retour au projet</a></p>
<p><a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/character/create">Ajouter un
        personnage</a></p>
<?php if (empty($characters)): ?>
    <p>Aucun personnage pour ce projet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($characters as $char): ?>
                <tr>
                    <td><?php echo htmlspecialchars($char['name'] ?? ''); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($char['description'] ?? '')); ?></td>
                    <td>
                        <a class="button" href="<?php echo $base; ?>/character/<?php echo $char['id']; ?>/edit">Modifier</a>
                        <a class="button delete" href="<?php echo $base; ?>/character/<?php echo $char['id']; ?>/delete"
                            onclick="return confirm('Supprimer ce personnage ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>