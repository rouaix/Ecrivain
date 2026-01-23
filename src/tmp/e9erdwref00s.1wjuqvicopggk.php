<h2>Personnages : <?= ($project['title']) ?></h2>

<p>
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>" class="button">← Retour au projet</a>
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>/character/create" class="button">Nouveau personnage</a>
</p>

<?php if (empty($characters)): ?>
    
        <div class="panel-empty">
            <p>Aucun personnage créé pour ce projet.</p>
        </div>
    
    <?php else: ?>
        <div class="characters-grid">
            <?php foreach (($characters?:[]) as $char): ?>
                <div class="character-card">
                    <div class="character-header">
                        <h3 class="character-name"><?= ($char['name']) ?></h3>
                    </div>
                    <div class="character-description">
                        <?= ($this->raw($char['description']))."
" ?>
                    </div>
                    <div class="character-actions">
                        <a class="button small" href="<?= ($base) ?>/character/<?= ($char['id']) ?>/edit">Modifier</a>
                        <a class="button small delete" href="<?= ($base) ?>/character/<?= ($char['id']) ?>/delete"
                            onclick="return confirm('Confirmer la suppression de ce personnage ?');">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    
<?php endif; ?>