<?php
$coverImage = null;
foreach ($sectionsBeforeChapters as $sec) {
    if ($sec['type'] === 'cover' && !empty($sec['image_path'])) {
        $coverImage = $sec['image_path'];
        break;
    }
}
?>

<div style="display: flex; gap: 40px; align-items: flex-start; margin-bottom: 20px;">
    <div style="flex: 1;">
        <h2><?php echo htmlspecialchars($project['title']); ?></h2>
        <p><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></p>
        <p>Objectif : <?php echo (int) $project['target_words']; ?> mots (environ
            <?php echo ceil($project['target_words'] / ($project['words_per_page'] ?: 350)); ?> pages)
        </p>

        <p>
            <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/mindmap">Voir la carte
                mentale</a>
            <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export">Exporter en
                texte</a>
            <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export/epub">Exporter en
                EPUB</a>
            <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export/html">Exporter en
                HTML</a>
        </p>
    </div>
    <?php if ($coverImage): ?>
        <div style="width: 150px; flex-shrink: 0;">
            <img src="<?php echo $base . $coverImage; ?>" alt="Couverture"
                style="max-height: 175px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        </div>
    <?php endif; ?>
</div>

<?php
// Calculate total word count across all chapters and sections
$totalWords = 0;
foreach ($allChapters as $ch) {
    $totalWords += str_word_count($ch['content'] ?? '');
}
foreach ($sectionsBeforeChapters as $sec) {
    $totalWords += str_word_count($sec['content'] ?? '');
}
foreach ($sectionsAfterChapters as $sec) {
    $totalWords += str_word_count($sec['content'] ?? '');
}
$target = (int) $project['target_words'];
$progress = $target > 0 ? min(100, round($totalWords / $target * 100)) : 0;
?>
<div class="progress-container" style="background: #f0f0f0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <?php $wpp = $project['words_per_page'] ?: 350; ?>
    <strong>Progression globale : <?php echo $totalWords; ?> / <?php echo $target ?: '0'; ?> mots
        (<?php echo $progress; ?>%) — environ <?php echo ceil($totalWords / $wpp); ?> /
        <?php echo ceil($target / $wpp); ?> pages</strong>
    <div style="background: #ddd; height: 10px; border-radius: 5px; margin-top: 10px; overflow: hidden;">
        <div style="background: #3f51b5; width: <?php echo $progress; ?>%; height: 100%;"></div>
    </div>
</div>

<h3>Sections avant les chapitres</h3>
<p><small>Couverture, Préface, Introduction, Prologue</small></p>
<?php
$beforeSectionTypes = ['cover', 'preface', 'introduction', 'prologue'];
$existingSectionsBeforeByType = [];
foreach ($sectionsBeforeChapters as $sec) {
    $existingSectionsBeforeByType[$sec['type']] = $sec;
}
?>
<div class="sortable-groups" id="beforeChaptersGroups">
    <?php
    $beforeSectionTypes = ['cover', 'preface', 'introduction', 'prologue'];
    $sectionsByType = [];
    foreach ($sectionsBeforeChapters as $section) {
        $sectionsByType[$section['type']][] = $section;
    }

    // Determine the order of types
    $orderedTypes = [];
    $seenTypes = [];
    foreach ($sectionsBeforeChapters as $section) {
        if (!in_array($section['type'], $seenTypes)) {
            $orderedTypes[] = $section['type'];
            $seenTypes[] = $section['type'];
        }
    }
    foreach ($beforeSectionTypes as $type) {
        if (!in_array($type, $seenTypes)) {
            $orderedTypes[] = $type;
        }
    }

    foreach ($orderedTypes as $type):
        $items = $sectionsByType[$type] ?? [];
        $sectionTypeName = \Section::getTypeName($type);
        ?>
        <div class="section-group-block" data-type="<?php echo $type; ?>"
            style="margin-bottom: 20px; border: 1px solid #eee; padding: 10px; border-radius: 8px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #f5f5f5;">
                <h4 style="margin: 0;">
                    <span class="group-drag-handle" style="cursor: move; color: #ccc; margin-right: 10px;">⠿</span>
                    <?php echo htmlspecialchars($sectionTypeName); ?>
                </h4>
                <?php if (empty($items)): ?>
                    <a class="button small"
                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">Créer</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($items)): ?>
                <table style="margin-bottom: 0;">
                    <tbody class="sortable-items" data-type="<?php echo $type; ?>">
                        <?php foreach ($items as $section): ?>
                            <tr data-id="<?php echo $section['id']; ?>">
                                <td class="item-drag-handle" style="width: 30px; cursor: move; color: #ddd;">☰</td>
                                <td>
                                    <div class="preview-trigger"
                                        data-preview-content="<?php echo htmlspecialchars($section['content'] ?? ''); ?>">
                                        <input type="checkbox" class="export-toggle" data-type="section"
                                            data-id="<?php echo $section['id']; ?>" <?php echo ($section['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                        <?php echo htmlspecialchars($section['title'] ?: $sectionTypeName); ?>
                                    </div>
                                </td>
                                <td style="width: 100px;"><?php echo str_word_count($section['content'] ?? ''); ?> mots</td>
                                <td style="width: 220px; text-align: right; white-space: nowrap;">
                                    <a class="button small"
                                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>?id=<?php echo $section['id']; ?>">Modifier</a>
                                    <a class="button small delete"
                                        href="<?php echo $base; ?>/section/<?php echo $section['id']; ?>/delete"
                                        onclick="return confirm('Supprimer cette section ?');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin: 0; padding: 10px; font-style: italic; color: #999; font-size: 0.9em;">Aucun contenu créé.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<h3>Actes et Chapitres</h3>
<p>
    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/act/create">Ajouter un acte</a>
    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/chapter/create">Ajouter un
        chapitre</a>
</p>

<?php if (empty($acts) && empty($chaptersWithoutAct)): ?>
    <p>Aucun chapitre ou acte pour ce projet.</p>
<?php else: ?>
    <?php foreach ($acts as $act): ?>
        <div class="act-container" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">
                <h4 style="margin: 0;"><?php echo htmlspecialchars($act['title']); ?></h4>
                <div>
                    <a class="button small" href="<?php echo $base; ?>/act/<?php echo $act['id']; ?>/edit">Modifier l'acte</a>
                    <a class="button small delete" href="<?php echo $base; ?>/act/<?php echo $act['id']; ?>/delete"
                        onclick="return confirm('Supprimer cet acte et tous ses chapitres ?');">Supprimer</a>
                </div>
            </div>
            <?php if (!empty($act['description'])): ?>
                <p style="font-size: 0.9em; color: #666; font-style: italic;">
                    <?php echo nl2br(htmlspecialchars($act['description'])); ?>
                </p>
            <?php endif; ?>

            <?php $actChapters = $chaptersByAct[$act['id']] ?? []; ?>
            <?php if (empty($actChapters)): ?>
                <p>Aucun chapitre dans cet acte.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Mots / Pages</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="sortable-chapters">
                        <?php foreach ($actChapters as $ch): ?>
                            <?php
                            $subs = $subChaptersByParent[$ch['id']] ?? [];
                            $wcParent = str_word_count($ch['content'] ?? '');
                            $wcSubs = 0;
                            foreach ($subs as $sub) {
                                $wcSubs += str_word_count($sub['content'] ?? '');
                            }
                            $totalWcChapter = $wcParent + $wcSubs;
                            ?>
                            <tr data-id="<?php echo $ch['id']; ?>">
                                <td>
                                    <span class="sortable-handle">☰</span>
                                    <input type="checkbox" class="export-toggle" data-type="chapter" data-id="<?php echo $ch['id']; ?>"
                                        <?php echo ($ch['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                    <strong class="preview-trigger"
                                        data-preview-content="<?php echo htmlspecialchars($ch['content'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($ch['title']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo $totalWcChapter; ?> mots
                                    <br><small style="color: #666; font-weight: bold;"><?php echo ceil($totalWcChapter / $wpp); ?>
                                        pages</small>
                                    <?php if ($wcSubs > 0): ?>
                                        <br><small style="color: #999;">(<?php echo $wcParent; ?> + <?php echo $wcSubs; ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button small" href="<?php echo $base; ?>/chapter/<?php echo $ch['id']; ?>">Éditer</a>
                                    <a class="button small"
                                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/chapter/create?parent_id=<?php echo $ch['id']; ?>&act_id=<?php echo $ch['act_id'] ?? ''; ?>">+
                                        Sous-chapitre</a>
                                    <a class="button small delete" href="<?php echo $base; ?>/chapter/<?php echo $ch['id']; ?>/delete"
                                        onclick="return confirm('Supprimer ce chapitre ?');">Supprimer</a>
                                </td>
                            </tr>
                            <?php $subs = $subChaptersByParent[$ch['id']] ?? []; ?>
                            <?php foreach ($subs as $sub): ?>
                                <?php $swc = str_word_count($sub['content'] ?? ''); ?>
                                <tr data-id="<?php echo $sub['id']; ?>">
                                    <td style="padding-left: 30px;">
                                        <span class="sortable-handle">☰</span>
                                        <input type="checkbox" class="export-toggle" data-type="chapter" data-id="<?php echo $sub['id']; ?>"
                                            <?php echo ($sub['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                        <span class="preview-trigger"
                                            data-preview-content="<?php echo htmlspecialchars($sub['content'] ?? ''); ?>">
                                            └─ <?php echo htmlspecialchars($sub['title']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $swc; ?> mots
                                        <br><small style="color: #999;"><?php echo ceil($swc / $wpp); ?> pages</small>
                                    </td>
                                    <td>
                                        <a class="button small" href="<?php echo $base; ?>/chapter/<?php echo $sub['id']; ?>">Éditer</a>
                                        <a class="button small delete" href="<?php echo $base; ?>/chapter/<?php echo $sub['id']; ?>/delete"
                                            onclick="return confirm('Supprimer ce sous-chapitre ?');">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($chaptersWithoutAct)): ?>
        <h4>Chapitres hors actes</h4>
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Mots / Pages</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="sortable-chapters">
                <?php foreach ($chaptersWithoutAct as $ch): ?>
                    <?php
                    $subs = $subChaptersByParent[$ch['id']] ?? [];
                    $wcParent = str_word_count($ch['content'] ?? '');
                    $wcSubs = 0;
                    foreach ($subs as $sub) {
                        $wcSubs += str_word_count($sub['content'] ?? '');
                    }
                    $totalWcChapter = $wcParent + $wcSubs;
                    ?>
                    <tr data-id="<?php echo $ch['id']; ?>">
                        <td>
                            <span class="sortable-handle">☰</span>
                            <input type="checkbox" class="export-toggle" data-type="chapter" data-id="<?php echo $ch['id']; ?>"
                                <?php echo ($ch['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                            <strong class="preview-trigger"
                                data-preview-content="<?php echo htmlspecialchars($ch['content'] ?? ''); ?>">
                                <?php echo htmlspecialchars($ch['title']); ?>
                            </strong>
                        </td>
                        <td>
                            <?php echo $totalWcChapter; ?> mots
                            <br><small style="color: #666; font-weight: bold;"><?php echo ceil($totalWcChapter / $wpp); ?>
                                pages</small>
                            <?php if ($wcSubs > 0): ?>
                                <br><small style="color: #999;">(<?php echo $wcParent; ?> + <?php echo $wcSubs; ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="button small" href="<?php echo $base; ?>/chapter/<?php echo $ch['id']; ?>">Éditer</a>
                            <a class="button small"
                                href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/chapter/create?parent_id=<?php echo $ch['id']; ?>&act_id=<?php echo $ch['act_id'] ?? ''; ?>">+
                                Sous-chapitre</a>
                            <a class="button small delete" href="<?php echo $base; ?>/chapter/<?php echo $ch['id']; ?>/delete"
                                onclick="return confirm('Supprimer ce chapitre ?');">Supprimer</a>
                        </td>
                    </tr>
                    <?php $subs = $subChaptersByParent[$ch['id']] ?? []; ?>
                    <?php foreach ($subs as $sub): ?>
                        <?php $swc = str_word_count($sub['content'] ?? ''); ?>
                        <tr data-id="<?php echo $sub['id']; ?>">
                            <td style="padding-left: 30px;">
                                <span class="sortable-handle">☰</span>
                                <input type="checkbox" class="export-toggle" data-type="chapter" data-id="<?php echo $sub['id']; ?>"
                                    <?php echo ($sub['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                <span class="preview-trigger"
                                    data-preview-content="<?php echo htmlspecialchars($sub['content'] ?? ''); ?>">
                                    └─ <?php echo htmlspecialchars($sub['title']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $swc; ?> mots
                                <br><small style="color: #999;"><?php echo ceil($swc / $wpp); ?> pages</small>
                            </td>
                            <td>
                                <a class="button small" href="<?php echo $base; ?>/chapter/<?php echo $sub['id']; ?>">Éditer</a>
                                <a class="button small delete" href="<?php echo $base; ?>/chapter/<?php echo $sub['id']; ?>/delete"
                                    onclick="return confirm('Supprimer ce sous-chapitre ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>

<h3>Sections après les chapitres</h3>
<p><small>Postface, Annexes, Notes, Dos du livre</small></p>

<div class="sortable-groups" id="afterChaptersGroups">
    <?php
    $afterSectionTypes = ['postface', 'appendices', 'notes', 'back_cover'];
    $sectionsByType = [];
    foreach ($sectionsAfterChapters as $section) {
        $sectionsByType[$section['type']][] = $section;
    }

    // Determine the order of types based on the first occurrence in sectionsAfterChapters
    // or the default order if not present.
    $orderedTypes = [];
    $seenTypes = [];
    foreach ($sectionsAfterChapters as $section) {
        if (!in_array($section['type'], $seenTypes)) {
            $orderedTypes[] = $section['type'];
            $seenTypes[] = $section['type'];
        }
    }
    foreach ($afterSectionTypes as $type) {
        if (!in_array($type, $seenTypes)) {
            $orderedTypes[] = $type;
        }
    }

    foreach ($orderedTypes as $type):
        $isMulti = ($type === 'notes' || $type === 'appendices');
        $items = $sectionsByType[$type] ?? [];
        $sectionTypeName = \Section::getTypeName($type);

        // Skip display of single-entry types if not created yet (they will be handled below or shown as empty)
        // Actually, let's always show the block header if it's a multi type or if it has items.
        if (!$isMulti && empty($items) && $type !== 'postface' && $type !== 'back_cover')
            continue;
        ?>
        <div class="section-group-block" data-type="<?php echo $type; ?>"
            style="margin-bottom: 20px; border: 1px solid #eee; padding: 10px; border-radius: 8px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #f5f5f5;">
                <h4 style="margin: 0;">
                    <span class="group-drag-handle" style="cursor: move; color: #ccc; margin-right: 10px;">⠿</span>
                    <?php echo htmlspecialchars($sectionTypeName); ?>
                </h4>
                <?php if ($isMulti): ?>
                    <a class="button small"
                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">+ Ajouter
                        <?php echo ($type === 'notes' ? 'une note' : 'une annexe'); ?></a>
                <?php elseif (empty($items)): ?>
                    <a class="button small"
                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">Créer</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($items)): ?>
                <table style="margin-bottom: 0;">
                    <tbody class="sortable-items" data-type="<?php echo $type; ?>">
                        <?php foreach ($items as $section): ?>
                            <tr data-id="<?php echo $section['id']; ?>">
                                <td class="item-drag-handle" style="width: 30px; cursor: move; color: #ddd;">☰</td>
                                <td>
                                    <div class="preview-trigger"
                                        data-preview-content="<?php echo htmlspecialchars($section['content'] ?? ''); ?>">
                                        <input type="checkbox" class="export-toggle" data-type="section"
                                            data-id="<?php echo $section['id']; ?>" <?php echo ($section['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                        <?php echo htmlspecialchars($section['title'] ?: $sectionTypeName); ?>
                                    </div>
                                </td>
                                <td style="width: 100px;"><?php echo str_word_count($section['content'] ?? ''); ?> mots</td>
                                <td style="width: 220px; text-align: right; white-space: nowrap;">
                                    <a class="button small"
                                        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>?id=<?php echo $section['id']; ?>">Modifier</a>
                                    <a class="button small delete"
                                        href="<?php echo $base; ?>/section/<?php echo $section['id']; ?>/delete"
                                        onclick="return confirm('Supprimer cette section ?');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin: 0; padding: 10px; font-style: italic; color: #999; font-size: 0.9em;">Aucun contenu créé.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<h3>Personnages</h3>
<p><a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/characters">Gérer les
        personnages</a></p>
<?php if (empty($characters)): ?>
    <p>Aucun personnage défini.</p>
<?php else: ?>
    <ul>
        <?php foreach ($characters as $char): ?>
            <li><?php echo htmlspecialchars($char['name']); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
    .sortable-handle {
        cursor: move;
        color: #999;
        padding-right: 10px;
        display: inline-block;
        width: 20px;
    }

    .sortable-ghost {
        opacity: 0.4;
        background-color: #e1f5fe !important;
    }

    .save-status {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 10px 20px;
        background: #4caf50;
        color: white;
        border-radius: 4px;
        display: none;
        z-index: 10000;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    /* Preview styles */
    .preview-trigger {
        position: relative;
        cursor: help;
        border-bottom: 1px dotted #ccc;
        display: inline-block;
    }

    .preview-box {
        position: absolute;
        display: none;
        width: 50vw;
        max-height: 400px;
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        z-index: 10001;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 1.1rem;
        line-height: 1.6;
        color: #222;
        pointer-events: auto;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .preview-box.visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    .preview-box h1,
    .preview-box h2,
    .preview-box h3 {
        margin-top: 0;
        color: #111;
        font-size: 1.2rem;
    }

    .preview-box p {
        margin-bottom: 0.8rem;
    }
</style>

<div id="saveStatus" class="save-status">Ordre enregistré !</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const containers = document.querySelectorAll('.sortable-chapters');
        containers.forEach(container => {
            new Sortable(container, {
                animation: 150,
                handle: '.sortable-handle',
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const rows = evt.to.querySelectorAll('tr[data-id]');
                    const order = Array.from(rows).map(row => row.getAttribute('data-id'));
                    const projectId = <?php echo (int) $project['id']; ?>;

                    fetch('<?php echo $base; ?>/project/' + projectId + '/chapters/reorder', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ order: order })
                    })
                        .then(response => {
                            if (response.ok) {
                                showStatus();
                            } else {
                                alert('Erreur lors de la sauvegarde de l\'ordre.');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Erreur réseau.');
                        });
                }
            });
        });

    });

    function showStatus(message) {
        const status = document.getElementById('saveStatus');
        if (status) {
            if (message) status.textContent = message;
            else status.textContent = 'Ordre enregistré !';
            status.style.display = 'block';
            setTimeout(() => {
                status.style.display = 'none';
            }, 2000);
        }
    }

    // Sortable for section groups (Before and After blocks)
    ['beforeChaptersGroups', 'afterChaptersGroups'].forEach(id => {
        const container = document.getElementById(id);
        if (container) {
            new Sortable(container, {
                animation: 150,
                handle: '.group-drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    const order = [];
                    document.querySelectorAll('tr[data-id]').forEach(function (row) {
                        order.push(row.getAttribute('data-id'));
                    });

                    if (order.length > 0) {
                        saveSectionsOrder(order);
                    }
                }
            });
        }
    });

    // Sortable for individual items within each group
    document.querySelectorAll('.sortable-items').forEach(function (el) {
        new Sortable(el, {
            animation: 150,
            handle: '.item-drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const order = [];
                document.querySelectorAll('tr[data-id]').forEach(function (row) {
                    order.push(row.getAttribute('data-id'));
                });

                if (order.length > 0) {
                    saveSectionsOrder(order);
                }
            }
        });
    });

    function saveSectionsOrder(order) {
        fetch('<?php echo $base; ?>/project/<?php echo $project['id']; ?>/sections/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order: order })
        }).then(function (resp) {
            if (resp.ok) {
                showStatus('Ordre des sections enregistré !');
            }
        });
    }

    // Export Toggle Logic
    document.querySelectorAll('.export-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const type = this.getAttribute('data-type');
            const id = this.getAttribute('data-id');
            const isExported = this.checked ? 1 : 0;
            const projectId = <?php echo (int) $project['id']; ?>;

            fetch('<?php echo $base; ?>/project/' + projectId + '/export-toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: type,
                    id: id,
                    is_exported: isExported
                })
            }).then(function (resp) {
                if (resp.ok) {
                    showStatus('Choix d\'export enregistré !');
                } else {
                    alert('Erreur lors de l\'enregistrement du choix d\'export.');
                    this.checked = !this.checked; // Revert
                }
            }.bind(this));
        });
    });

    // Hover Preview Logic
    const previewBox = document.createElement('div');
    previewBox.className = 'preview-box';
    document.body.appendChild(previewBox);

    let hideTimeout;

    const showPreview = (e) => {
        const trigger = e.target.closest('.preview-trigger');
        if (!trigger) return;

        clearTimeout(hideTimeout);

        const content = trigger.getAttribute('data-preview-content');
        previewBox.innerHTML = (content && content.trim() !== '') ? content : '<p style="font-style:italic; color:#999;">Aucun contenu.</p>';

        previewBox.classList.add('visible');

        const rect = trigger.getBoundingClientRect();
        let top = rect.top + window.scrollY - 10;
        let left = rect.right + 8;

        // Viewport constraints
        // if (left + 620 > window.innerWidth) {
        //     left = rect.left - 610;
        // }
        // Viewport constraints
        if (left + (window.innerWidth / 2) > window.innerWidth) {
            left = rect.left - (window.innerWidth / 2);
        }

        // Adjust for vertical overflow
        const boxHeight = Math.min(400, previewBox.scrollHeight);
        if (rect.top + boxHeight > window.innerHeight) {
            top = rect.bottom + window.scrollY - boxHeight;
        }

        if (top < window.scrollY + 10) top = window.scrollY + 10;

        previewBox.style.top = top + 'px';
        previewBox.style.left = left + 'px';
    };

    const hidePreview = (e) => {
        hideTimeout = setTimeout(() => {
            previewBox.classList.remove('visible');
        }, 300);
    };

    // Event Delegation
    document.addEventListener('mouseover', (e) => {
        if (e.target.closest('.preview-trigger')) {
            showPreview(e);
        }
    });

    document.addEventListener('mouseout', (e) => {
        if (e.target.closest('.preview-trigger')) {
            hidePreview(e);
        }
    });

    // Keeping box open when mouse is over it
    previewBox.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
    previewBox.addEventListener('mouseleave', hidePreview);
</script>