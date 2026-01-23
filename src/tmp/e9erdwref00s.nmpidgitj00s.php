<h2>Consommation IA</h2>

<div class="stats-row">
    <?php foreach (($stats?:[]) as $item): ?>
        <div class="stat-card">
            <h3><?= ($item['model_name']) ?></h3>
            <p class="stat-value"><?= (number_format($item['total_tokens'], 0,
                ',', ' '))."
" ?>
                <span class="stat-sub">tokens</span>
            </p>
            <div class="stat-details">
                <div>Requêtes : <?= ($item['request_count']) ?></div>
                <div>Prompt : <?= (number_format($item['total_prompt'], 0, ',', ' ')) ?></div>
                <div>Réponse : <?= (number_format($item['total_completion'], 0, ',', ' ')) ?></div>
                <div class="stat-cost">Coût estimé : ~ <?= (number_format($item['estimated_cost'], 4, ',', ' ')) ?> $</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3>Historique récent</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Modèle</th>
            <th>Fonctionnalité</th>
            <th class="text-right">Prompt</th>
            <th class="text-right">Réponse</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (($recent?:[]) as $entry): ?>
            <tr>
                <td><?= (date('d/m/Y H:i', strtotime($entry['created_at']))) ?></td>
                <td><?= ($entry['model_name']) ?></td>
                <td><?= ($entry['feature_name']) ?></td>
                <td class="text-right"><?= (number_format($entry['prompt_tokens'], 0, ',', ' '))."
" ?>
                </td>
                <td class="text-right"><?= (number_format($entry['completion_tokens'], 0, ',', ' '))."
" ?>
                </td>
                <td class="text-right text-bold"><?= (number_format($entry['total_tokens'],
                    0, ',', ' ')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
            <tr>
                <td colspan="6" class="table-empty">Aucun usage
                    enregistré pour le
                    moment.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="mt-20">
    <a href="<?= ($base) ?>/dashboard" class="button secondary">Retour au tableau de bord</a>
</div>
