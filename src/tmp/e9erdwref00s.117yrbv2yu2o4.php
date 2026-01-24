<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($this->esc($title)) ?></title>
    <link rel="stylesheet" href="<?= ($base) ?>/public/style.css?v=4">

    <?php $selectedTheme=isset($COOKIE['theme']) ? $COOKIE['theme'] : 'default'; ?>
    <link rel="stylesheet" href="<?= ($base) ?>/public/theme-<?= ($selectedTheme) ?>.css">
</head>

<body class="reading-mode theme-<?= ($selectedTheme) ?>">
    <div class="reading-container">
        <!-- Table of Contents -->
        <aside class="reading-toc" id="readingToc">
            <h2>Table des matières</h2>
            <div class="toc-list">
                <?php foreach (($tocItems?:[]) as $item): ?>
                    <div class="toc-item level-<?= ($item['level']) ?>" data-page="<?= ($item['page']) ?>">
                        <span class="toc-page">p. <?= ($item['page']) ?></span>
                        <?= ($this->esc($item['title']))."
" ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Reading Content -->
        <main class="reading-content" id="readingContent">
            <!-- Toolbar -->
            <div class="reading-toolbar">
                <div class="toolbar-title"><?= ($this->esc($project['title'])) ?></div>
                <button class="toolbar-btn secondary" id="toggleToc">Table des matières</button>
                <button class="toolbar-btn secondary" id="toggleFullscreen">Plein écran</button>
                <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>" class="toolbar-btn button">Retour au projet</a>
            </div>

            <!-- Cover Page -->
            <div class="reading-cover-page">
                <?php if ($coverImage): ?>
                    <img src="<?= ($coverImage) ?>" alt="Couverture" class="cover-image">
                <?php endif; ?>
                <h1 class="cover-title"><?= ($this->esc($project['title'])) ?></h1>
                <?php if ($project['description']): ?>
                    <div class="cover-description"><?= ($this->esc($project['description'])) ?></div>
                <?php endif; ?>
                <div class="cover-author"><?= ($this->esc($authorName)) ?></div>
            </div>

            <!-- Content Sections -->
            <?php foreach (($readingContent?:[]) as $section): ?>
                <article class="reading-section" id="page-<?= ($section['page_start']) ?>"
                    data-page-start="<?= ($section['page_start']) ?>" data-page-end="<?= ($section['page_end']) ?>"
                    data-content-type="<?= ($section['type']) ?>" data-content-id="<?= ($section['id']) ?>">
                    <?php if ($section['type'] == 'act'): ?>
                        <h1><?= ($this->esc($section['title'])) ?></h1>
                    <?php endif; ?>
                    <?php if ($section['type'] == 'chapter'): ?>
                        <h2><?= ($this->esc($section['title'])) ?></h2>
                    <?php endif; ?>
                    <?php if ($section['type'] == 'subchapter'): ?>
                        <h3><?= ($this->esc($section['title'])) ?></h3>
                    <?php endif; ?>
                    <?php if ($section['type'] == 'section'): ?>
                        <h2><?= ($this->esc($section['title'])) ?></h2>
                    <?php endif; ?>
                    <?php if ($section['type'] == 'note'): ?>
                        <h2><?= ($this->esc($section['title'])) ?></h2>
                    <?php endif; ?>

                    <div class="typography">
                        <?= ($this->raw($section['content']))."
" ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <!-- Selection Popup -->
            <div id="selectionPopup" class="selection-popup" style="display: none;">
                <button id="copyBtn" class="popup-btn">Copier</button>
                <button id="commentBtn" class="popup-btn">Ajouter une remarque</button>
            </div>

            <!-- Comment Form Popup -->
            <div id="commentFormPopup" class="comment-form-popup" style="display: none;">
                <div class="comment-form-content">
                    <h3>Ajouter une remarque</h3>
                    <p id="selectedTextPreview" class="selected-text-preview"></p>
                    <textarea id="commentTextarea" placeholder="Votre remarque..." rows="4"></textarea>
                    <div class="comment-form-actions">
                        <button id="cancelCommentBtn" class="popup-btn secondary">Annuler</button>
                        <button id="submitCommentBtn" class="popup-btn primary">Valider</button>
                    </div>
                </div>
            </div>

            <!-- Page Number Footer -->
            <div class="reading-footer">
                <div class="page-number">
                    Page <span id="currentPage">1</span> / <?= ($totalPages)."
" ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toc = document.getElementById('readingToc');
            const content = document.getElementById('readingContent');
            const toggleTocBtn = document.getElementById('toggleToc');
            const toggleFullscreenBtn = document.getElementById('toggleFullscreen');
            const currentPageSpan = document.getElementById('currentPage');
            const sections = document.querySelectorAll('.reading-section');

            // Toggle TOC
            toggleTocBtn.addEventListener('click', function () {
                toc.classList.toggle('hidden');
            });

            // Toggle Fullscreen
            toggleFullscreenBtn.addEventListener('click', function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                    document.body.classList.add('fullscreen-mode');
                    toggleFullscreenBtn.textContent = 'Quitter plein écran';
                } else {
                    document.exitFullscreen();
                    document.body.classList.remove('fullscreen-mode');
                    toggleFullscreenBtn.textContent = 'Plein écran';
                }
            });

            // Handle fullscreen change
            document.addEventListener('fullscreenchange', function () {
                if (!document.fullscreenElement) {
                    document.body.classList.remove('fullscreen-mode');
                    toggleFullscreenBtn.textContent = 'Plein écran';
                }
            });

            // TOC Click Navigation
            document.querySelectorAll('.toc-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    const page = parseInt(this.dataset.page);
                    const targetSection = document.querySelector(`[data-page-start="${page}"]`);
                    if (targetSection) {
                        targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });

            // Update current page on scroll
            function updateCurrentPage() {
                const scrollTop = content.scrollTop;
                const viewportMiddle = scrollTop + (content.clientHeight / 2);

                let currentPage = 1;
                sections.forEach(function (section) {
                    const sectionTop = section.offsetTop;
                    const sectionBottom = sectionTop + section.offsetHeight;

                    if (viewportMiddle >= sectionTop && viewportMiddle < sectionBottom) {
                        const pageStart = parseInt(section.dataset.pageStart);
                        const pageEnd = parseInt(section.dataset.pageEnd);
                        const sectionProgress = (viewportMiddle - sectionTop) / section.offsetHeight;
                        currentPage = Math.floor(pageStart + (pageEnd - pageStart) * sectionProgress);
                    }
                });

                currentPageSpan.textContent = currentPage;
            }

            content.addEventListener('scroll', updateCurrentPage);
            updateCurrentPage();

            // Text Selection Popup Logic
            const selectionPopup = document.getElementById('selectionPopup');
            const commentFormPopup = document.getElementById('commentFormPopup');
            const copyBtn = document.getElementById('copyBtn');
            const commentBtn = document.getElementById('commentBtn');
            const cancelCommentBtn = document.getElementById('cancelCommentBtn');
            const submitCommentBtn = document.getElementById('submitCommentBtn');
            const commentTextarea = document.getElementById('commentTextarea');
            const selectedTextPreview = document.getElementById('selectedTextPreview');

            let selectedText = '';
            let currentContentType = '';
            let currentContentId = '';

            // Show selection popup on text selection
            document.addEventListener('mouseup', function (e) {
                const selection = window.getSelection();
                const text = selection.toString().trim();

                if (text.length > 0) {
                    // Find the closest reading-section
                    let target = e.target;
                    while (target && !target.classList.contains('reading-section')) {
                        target = target.parentElement;
                    }

                    if (target) {
                        selectedText = text;
                        currentContentType = target.dataset.contentType;
                        currentContentId = target.dataset.contentId;

                        // Position popup near selection
                        const range = selection.getRangeAt(0);
                        const rect = range.getBoundingClientRect();

                        selectionPopup.style.display = 'flex';
                        selectionPopup.style.left = rect.left + 'px';
                        selectionPopup.style.top = (rect.bottom + 5) + 'px';
                    }
                } else {
                    selectionPopup.style.display = 'none';
                }
            });

            // Hide popup when clicking outside
            document.addEventListener('mousedown', function (e) {
                if (!selectionPopup.contains(e.target) && !commentFormPopup.contains(e.target)) {
                    selectionPopup.style.display = 'none';
                }
            });

            // Copy button
            copyBtn.addEventListener('click', function () {
                navigator.clipboard.writeText(selectedText).then(function () {
                    alert('Texte copié !');
                    selectionPopup.style.display = 'none';
                });
            });

            // Comment button
            commentBtn.addEventListener('click', function () {
                selectedTextPreview.textContent = '"' + selectedText + '"';
                commentTextarea.value = '';
                selectionPopup.style.display = 'none';
                commentFormPopup.style.display = 'flex';
                commentTextarea.focus();
            });

            // Cancel comment
            cancelCommentBtn.addEventListener('click', function () {
                commentFormPopup.style.display = 'none';
            });

            // Submit comment
            submitCommentBtn.addEventListener('click', function () {
                const comment = commentTextarea.value.trim();
                if (!comment) {
                    alert('Veuillez entrer une remarque.');
                    return;
                }

                const remarkText = `[${new Date().toLocaleString('fr-FR')}] "${selectedText}"\n${comment}\n\n`;

                // Send to backend
                fetch('<?= ($base) ?>/lecture/add-comment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        type: currentContentType,
                        id: currentContentId,
                        comment: remarkText
                    })
                })
                    .then(function (resp) {
                        if (resp.ok) {
                            alert('Remarque ajoutée avec succès !');
                            commentFormPopup.style.display = 'none';
                        } else {
                            alert('Erreur lors de l\'ajout de la remarque.');
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        alert('Erreur de communication.');
                    });
            });
        });
    </script>
</body>

</html>