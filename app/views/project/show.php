<?php
$coverImage = null;
foreach ($sectionsBeforeChapters as $sec) {
    if ($sec['type'] === 'cover' && !empty($sec['image_path'])) {
        $coverImage = $sec['image_path'];
        break;
    }
}

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
$wpp = $project['words_per_page'] ?: 350;
$beforeCount = count($sectionsBeforeChapters);
$afterCount = count($sectionsAfterChapters);
$characterCount = count($characters);
?>

<div class="project-page">
    <header class="project-header">
        <div class="project-header__content">
            <div class="project-title-row">
                <h2><?php echo htmlspecialchars($project['title']); ?></h2>
                <div class="project-actions">
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/mindmap">Carte mentale</a>
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export">Exporter texte</a>
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export/epub">Exporter EPUB</a>
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/export/html">Exporter HTML</a>
                </div>
            </div>
            <?php if (!empty($project['description'])): ?>
                <p class="project-description"><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></p>
            <?php endif; ?>
            <div class="project-meta">
                <div class="meta-card">
                    <span class="meta-label">Objectif</span>
                    <strong><?php echo (int) $project['target_words']; ?> mots</strong>
                    <span class="meta-sub">≈ <?php echo ceil($project['target_words'] / $wpp); ?> pages</span>
                </div>
                <div class="meta-card">
                    <span class="meta-label">Progression</span>
                    <strong><?php echo $totalWords; ?> mots</strong>
                    <span class="meta-sub"><?php echo $progress; ?>%</span>
                    <div class="progress-track">
                        <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                </div>
                <div class="meta-card">
                    <span class="meta-label">Pages estimées</span>
                    <strong><?php echo ceil($totalWords / $wpp); ?> / <?php echo ceil($target / $wpp); ?></strong>
                    <span class="meta-sub">mots/page : <?php echo $wpp; ?></span>
                </div>
            </div>
        </div>
        <?php if ($coverImage): ?>
            <div class="project-cover">
                <img src="<?php echo $base . $coverImage; ?>" alt="Couverture">
            </div>
        <?php endif; ?>
    </header>
</div>

<div class="progress-container">
    <strong>Progression globale : <?php echo $totalWords; ?> / <?php echo $target ?: '0'; ?> mots
        (<?php echo $progress; ?>%) — environ <?php echo ceil($totalWords / $wpp); ?> /
        <?php echo ceil($target / $wpp); ?> pages</strong>
    <div class="progress-shell">
        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
    </div>
</div>

<div class="project-grid">
    <main class="project-main">
        <section class="panel panel-open">
            <div class="panel-heading">
                <div>
                    <h3>Actes et Chapitres</h3>
                    <p class="panel-subtitle">Structure principale du récit.</p>
                </div>
                <div class="panel-actions">
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/act/create">Ajouter un acte</a>
                    <a class="button" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/chapter/create">Ajouter un chapitre</a>
                </div>
            </div>
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
        </section>
    </main>

    <aside class="project-sidebar">
        <details class="panel" <?php echo $beforeCount > 0 ? 'open' : ''; ?>>
            <summary>Sections avant les chapitres <span class="panel-count"><?php echo $beforeCount; ?></span></summary>
            <p class="panel-subtitle">Couverture, Préface, Introduction, Prologue.</p>
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
                    <div class="section-group-block" data-type="<?php echo $type; ?>">
                        <div class="section-group-heading">
                            <h4>
                                <span class="group-drag-handle">⠿</span>
                                <?php echo htmlspecialchars($sectionTypeName); ?>
                            </h4>
                            <?php if (empty($items)): ?>
                                <a class="button small"
                                    href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">Créer</a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($items)): ?>
                            <table class="compact-table">
                                <tbody class="sortable-items" data-type="<?php echo $type; ?>">
                                    <?php foreach ($items as $section): ?>
                                        <tr data-id="<?php echo $section['id']; ?>">
                                            <td class="item-drag-handle">☰</td>
                                            <td>
                                                <div class="preview-trigger"
                                                    data-preview-content="<?php echo htmlspecialchars($section['content'] ?? ''); ?>">
                                                    <input type="checkbox" class="export-toggle" data-type="section"
                                                        data-id="<?php echo $section['id']; ?>" <?php echo ($section['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                                    <?php echo htmlspecialchars($section['title'] ?: $sectionTypeName); ?>
                                                </div>
                                            </td>
                                            <td class="compact-meta"><?php echo str_word_count($section['content'] ?? ''); ?> mots</td>
                                            <td class="compact-actions">
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
                            <p class="panel-empty">Aucun contenu créé.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="panel" <?php echo $afterCount > 0 ? 'open' : ''; ?>>
            <summary>Sections après les chapitres <span class="panel-count"><?php echo $afterCount; ?></span></summary>
            <p class="panel-subtitle">Postface, Annexes, Notes, Dos du livre.</p>
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
                    <div class="section-group-block" data-type="<?php echo $type; ?>">
                        <div class="section-group-heading">
                            <h4>
                                <span class="group-drag-handle">⠿</span>
                                <?php echo htmlspecialchars($sectionTypeName); ?>
                            </h4>
                            <?php if ($isMulti): ?>
                                <a class="button small"
                                    href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">+
                                    <?php echo ($type === 'notes' ? 'Ajouter une note' : 'Ajouter une annexe'); ?></a>
                            <?php elseif (empty($items)): ?>
                                <a class="button small"
                                    href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $type; ?>">Créer</a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($items)): ?>
                            <table class="compact-table">
                                <tbody class="sortable-items" data-type="<?php echo $type; ?>">
                                    <?php foreach ($items as $section): ?>
                                        <tr data-id="<?php echo $section['id']; ?>">
                                            <td class="item-drag-handle">☰</td>
                                            <td>
                                                <div class="preview-trigger"
                                                    data-preview-content="<?php echo htmlspecialchars($section['content'] ?? ''); ?>">
                                                    <input type="checkbox" class="export-toggle" data-type="section"
                                                        data-id="<?php echo $section['id']; ?>" <?php echo ($section['is_exported'] ?? 1) ? 'checked' : ''; ?> title="Inclure dans l'export">
                                                    <?php echo htmlspecialchars($section['title'] ?: $sectionTypeName); ?>
                                                </div>
                                            </td>
                                            <td class="compact-meta"><?php echo str_word_count($section['content'] ?? ''); ?> mots</td>
                                            <td class="compact-actions">
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
                            <p class="panel-empty">Aucun contenu créé.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="panel" <?php echo $characterCount > 0 ? 'open' : ''; ?>>
            <summary>Personnages <span class="panel-count"><?php echo $characterCount; ?></span></summary>
            <div class="panel-actions panel-actions-row">
                <a class="button small" href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/characters">Gérer les personnages</a>
            </div>
            <?php if (empty($characters)): ?>
                <p class="panel-empty">Aucun personnage défini.</p>
            <?php else: ?>
                <ul class="character-list">
                    <?php foreach ($characters as $char): ?>
                        <li><?php echo htmlspecialchars($char['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
    </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
    .project-page {
        margin-bottom: 20px;
    }

    .project-header {
        display: flex;
        gap: 24px;
        align-items: flex-start;
        justify-content: space-between;
        background: #f8f9fc;
        padding: 20px;
        border-radius: 14px;
        border: 1px solid #eef0f4;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
    }

    .project-header__content {
        flex: 1;
        min-width: 0;
    }

    .project-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .project-title-row h2 {
        margin: 0;
    }

    .project-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .project-description {
        margin: 10px 0 16px;
        color: #555;
    }

    .project-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }

    .meta-card {
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }

    .meta-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a94a6;
    }

    .meta-sub {
        font-size: 0.85rem;
        color: #6b7280;
    }

    .progress-track {
        background: #eef1f6;
        height: 8px;
        border-radius: 999px;
        margin-top: 6px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #3f51b5, #6c7ff2);
    }

    .project-cover img {
        max-height: 190px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    }

    .progress-container {
        background: #f6f7fb;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid #eef0f4;
    }

    .progress-shell {
        background: #e2e8f0;
        height: 10px;
        border-radius: 999px;
        margin-top: 10px;
        overflow: hidden;
    }

    .progress-fill {
        background: linear-gradient(90deg, #3f51b5, #6c7ff2);
        height: 100%;
    }

    .project-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
        gap: 24px;
        align-items: start;
    }

    .panel {
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        margin-bottom: 16px;
    }

    .panel summary {
        font-weight: 600;
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .panel summary::-webkit-details-marker {
        display: none;
    }

    .panel summary::after {
        content: '▾';
        color: #94a3b8;
        transition: transform 0.2s ease;
    }

    details[open] summary::after {
        transform: rotate(180deg);
    }

    .panel-count {
        background: #eef2ff;
        color: #3f51b5;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .panel-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
    }

    .panel-subtitle {
        margin: 4px 0 0;
        font-size: 0.9rem;
        color: #7a8194;
    }

    .panel-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .panel-actions-row {
        margin: 12px 0;
    }

    .panel-empty {
        margin: 10px 0 0;
        padding: 10px 12px;
        font-style: italic;
        color: #9aa0ab;
        background: #f8f9fb;
        border-radius: 10px;
    }

    .section-group-block {
        margin-top: 12px;
        border: 1px solid #eef0f4;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fbfcff;
    }

    .section-group-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        border-bottom: 1px solid #eef0f4;
        padding-bottom: 6px;
    }

    .section-group-heading h4 {
        margin: 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .group-drag-handle {
        cursor: move;
        color: #c4cad6;
        margin-right: 4px;
    }

    .item-drag-handle {
        width: 24px;
        cursor: move;
        color: #c4cad6;
    }

    .compact-table {
        margin-bottom: 0;
    }

    .compact-meta {
        width: 70px;
        text-align: right;
        font-size: 0.8rem;
        color: #7a8194;
        white-space: nowrap;
    }

    .compact-actions {
        width: 160px;
        text-align: right;
        white-space: nowrap;
    }

    .character-list {
        list-style: none;
        padding-left: 0;
        column-count: 2;
        column-gap: 12px;
        margin: 0;
    }

    .character-list li {
        padding: 6px 0;
        break-inside: avoid;
    }

    @media (max-width: 1024px) {
        .project-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .project-header {
            flex-direction: column;
        }

        .character-list {
            column-count: 1;
        }
    }

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
