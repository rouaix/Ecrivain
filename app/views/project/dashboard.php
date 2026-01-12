<?php
/* Dashboard view. Variables: $projects (array), $user (array) */
?>
<h2>Bonjour <?php echo htmlspecialchars($user['username']); ?>!</h2>
<p>Voici la liste de vos projets d’écriture. Vous pouvez créer un nouveau projet ou gérer les projets existants.</p>
<p><a class="button" href="<?php echo $base; ?>/project/create">Nouveau projet</a></p>
<?php if (empty($projects)): ?>
    <p>Vous n’avez aucun projet pour le moment.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Objectif (mots)</th>
                <th>Pages estimées</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $proj): ?>
                <tr>
                    <td><?php echo htmlspecialchars($proj['title']); ?></td>
                    <td><?php echo (int) $proj['target_words']; ?></td>
                    <td><?php echo ceil($proj['target_words'] / ($proj['words_per_page'] ?: 350)); ?></td>
                    <td>
                        <a class="button" href="<?php echo $base; ?>/project/<?php echo $proj['id']; ?>">Ouvrir</a>
                        <a class="button" href="<?php echo $base; ?>/project/<?php echo $proj['id']; ?>/edit">Modifier</a>
                        <a class="button delete" href="<?php echo $base; ?>/project/<?php echo $proj['id']; ?>/delete"
                            onclick="return confirm('Supprimer ce projet ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>