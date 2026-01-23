<h2>Édition du chapitre «<?= ($chapter['title']) ?>»</h2>
<p>Projet : <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>"><?= ($project['title']) ?></a>
    <?php if ($currentAct): ?>
        &nbsp;&raquo;&nbsp; Acte : <strong>
            <?= ($currentAct['title'])."
" ?>
        </strong>
    <?php endif; ?>
    <?php if ($parentChapter): ?>
        &nbsp;&raquo;&nbsp; Chapitre : <strong>
            <?= ($parentChapter['title'])."
" ?>
        </strong>
    <?php endif; ?>
</p>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li><?= ($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= ($success)."
" ?>
    </div>
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>/chapter/create" class="button">Nouveau chapitre</a>
<?php endif; ?>
<form method="post" action="<?= ($base) ?>/chapter/<?= ($chapter['id']) ?>/save" id="editorForm">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="title">Titre</label>
        <input type="text" id="title" name="title" value="<?= ($chapter['title']) ?>" required>
    </div>
    <div class="form-group">
        <label for="act_id">Appartient à l'acte</label>
        <select id="act_id" name="act_id">
            <option value="">-- Aucun (hors actes) --</option>
            <?php foreach (($acts?:[]) as $act): ?>
                <option value="<?= ($act['id']) ?>" <?= (($chapter['act_id']==$act['id']) ? 'selected' : '') ?>>
                    <?= ($act['title'])."
" ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="parent_id">Est un sous-chapitre de</label>
        <select id="parent_id" name="parent_id">
            <option value="">-- Aucun (chapitre principal) --</option>
            <?php foreach (($topChapters?:[]) as $top): ?>
                <?php if ($top['id'] != $chapter['id']): ?>
                    <option value="<?= ($top['id']) ?>" data-act-id="<?= ($top['act_id']) ?>" <?= (($chapter['parent_id']==$top['id'])
                        ? 'selected' : '') ?>>
                        <?= ($top['title'])."
" ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="editor-wrapper" class="editor-wrapper">
        <div class="form-group editor-column">
            <label for="content">Contenu</label>
            <div id="editor" class="editor-surface editor-height-600">
                <?= ($this->raw($chapter['content']))."
" ?>
            </div>
            <input type="hidden" name="content" value="<?= ($this->esc($chapter['content'])) ?>">
            <p>Compteur de mots : <span id="wordCount">0</span></p>
        </div>

    </div>
    </div>
    <div class="form-group editor-tools-wrapper">
        <div id="status" class="status-label status--ok"></div>
        <div id="synonymsBox" class="ai-box"></div>
        <div id="analysisBox" class="ai-box"></div>
    </div>
    <input type="submit" value="Enregistrer">
    <?php if (!empty($chapter['parent_id'])): ?>
        <a href="<?= ($base) ?>/chapter/<?= ($chapter['parent_id']) ?>" class="button secondary">Retour à « <?= ($parentChapter['title']) ?> »</a>
    <?php endif; ?>
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>" class="button secondary">Retour au projet</a>
    <div><br /><br /></div>
    <?php if (!$chapter['parent_id']): ?>
        <div class="form-group">
            <label for="resume">Résumé (IA ou Manuel)</label>
            <div id="resume-editor" class="editor-surface editor-height-200">
                <?= ($this->raw($chapter['resume']))."
" ?>
            </div>
            <input type="hidden" name="resume" value="<?= ($this->esc($chapter['resume'])) ?>">
        </div>
    <?php endif; ?>
    <div class="form-group">
        <label for="comment">Commentaire</label>
        <div id="comment-editor" class="editor-surface editor-height-200">
            <?= ($this->raw($chapter['comment']))."
" ?>
        </div>
        <input type="hidden" name="comment" value="<?= ($this->esc($chapter['comment'])) ?>">
    </div>

</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editorEl = document.getElementById('editor');
        var initialContentHtml = editorEl ? (editorEl.innerHTML || '') : '';

        // Init Main Editor (QuillTools)
        QuillTools.init('#editor', {
            inputSelector: 'input[name="content"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            contextId: '<?= ($chapter['id']) ?>',
            contextType: 'chapter'
        });

        var chapterId = <?= ($chapter['id']) ?>;

    // Init Resume Editor (if valid)
    var resumeEl = document.getElementById('resume-editor');
    var resumeQuill = null;
    function decodeHtml(html) {
        var txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }
    function decodeHtmlDeep(html, depth) {
        var current = html;
        for (var i = 0; i < depth; i++) {
            var decoded = decodeHtml(current);
            if (decoded === current) break;
            current = decoded;
        }
        return current;
    }

    var decodedContent = decodeHtmlDeep(initialContentHtml, 2);
    if (decodedContent && decodedContent !== QuillTools.quill.root.innerHTML) {
        QuillTools.quill.clipboard.dangerouslyPasteHTML(decodedContent);
    }
    if (resumeEl) {
        var initialResumeHtml = resumeEl.innerHTML || '';
        resumeQuill = new Quill('#resume-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: QuillTools.toolbarOptions,
                    handlers: {
                        'undo': function () { this.quill.history.undo(); },
                        'redo': function () { this.quill.history.redo(); },
                        'emdash': function () { QuillTools.handleEmDash(this.quill); },
                        'group_lines': function () { QuillTools.handleGroupLines(this.quill); },
                        'remove_doublespaces': function () { QuillTools.handleRemoveDoubleSpaces(this.quill); }
                    }
                },
                history: {
                    delay: 2000,
                    maxStack: 500,
                    userOnly: true
                }
            }
        });

        // Track selection for tools
        resumeQuill.on('selection-change', function (range) {
            if (range) QuillTools.activeQuill = resumeQuill;
        });

        // Sync Input
        resumeQuill.on('text-change', function () {
            var html = resumeQuill.root.innerHTML;
            document.querySelector('input[name="resume"]').value = html;
        });

        var decodedResume = decodeHtmlDeep(initialResumeHtml, 2);
        if (decodedResume) {
            resumeQuill.clipboard.dangerouslyPasteHTML(decodedResume);
        }
    }

    // Init Comment Editor (Full)
    var commentEl = document.getElementById('comment-editor');
    if (commentEl) {
        var initialCommentHtml = commentEl.innerHTML || '';
        var commentQuill = new Quill('#comment-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: QuillTools.toolbarOptions,
                    handlers: {
                        'undo': function () { this.quill.history.undo(); },
                        'redo': function () { this.quill.history.redo(); },
                        'emdash': function () { QuillTools.handleEmDash(this.quill); },
                        'group_lines': function () { QuillTools.handleGroupLines(this.quill); },
                        'remove_doublespaces': function () { QuillTools.handleRemoveDoubleSpaces(this.quill); }
                    }
                },
                history: {
                    delay: 2000,
                    maxStack: 500,
                    userOnly: true
                }
            }
        });

        commentQuill.on('selection-change', function (range) {
            if (range) QuillTools.activeQuill = commentQuill;
        });

        commentQuill.on('text-change', function () {
            var html = commentQuill.root.innerHTML;
            document.querySelector('input[name="comment"]').value = html;
        });

        var decodedComment = decodeHtmlDeep(initialCommentHtml, 2);
        if (decodedComment) {
            commentQuill.clipboard.dangerouslyPasteHTML(decodedComment);
        }
    }

    // Add comment functionality (Quill Adapted)
    var commentButton = document.getElementById('commentButton');
    commentButton.addEventListener('click', function () {
        var range = QuillTools.quill.getSelection();
        if (!range || range.length === 0) {
            alert('Sélectionnez une portion de texte pour commenter.');
            return;
        }

        var userContent = prompt('Saisissez votre commentaire :');
        if (!userContent) return;

        // Use range index as offsets
        var start = range.index;
        var end = range.index + range.length;

        fetch('<?= ($base) ?>/chapter/' + chapterId + '/comment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify({ start: start, end: end, content: userContent })
        }).then(function (resp) { return resp.json().catch(function () { return null; }); }).then(function (data) {
            // Reload comments list
            fetch('<?= ($base) ?>/chapter/' + chapterId + '/comments')
                .then(function (resp) { return resp.json(); })
                .then(function (comments) {
                    var listDiv = document.getElementById('commentsList');
                    if (comments.length === 0) {
                        listDiv.innerHTML = '<p>Aucun commentaire pour l’instant.</p>';
                        return;
                    }
                    var html = '<ul>';
                    comments.forEach(function (com) {
                        // Note: Snippet display might be off if we don't have server-side text content
                        // But we just display what server sends
                        // We escape HTML in comments for safety
                        var snippet = (com.snippet || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        html += '<li><strong>« ' + snippet + ' »</strong> : ' + com.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</li>';
                    });
                    html += '</ul>';
                    listDiv.innerHTML = html;
                });
        });
    });

    // Act Filters (Preserved)
    const actSelect = document.getElementById('act_id');
    const parentSelect = document.getElementById('parent_id');

    if (actSelect && parentSelect) {
        // Cache original options functionality
        const originalOptions = Array.from(parentSelect.options).map(opt => {
            return {
                value: opt.value,
                text: opt.text,
                actId: opt.getAttribute('data-act-id'),
                selected: opt.selected
            };
        });

        function filterChapters() {
            const selectedActId = actSelect.value;
            const currentParentValue = parentSelect.value;

            // Clear current options
            parentSelect.innerHTML = '';

            originalOptions.forEach(data => {
                // Always show the placeholder/default option (empty value)
                if (data.value === "") {
                    addOption(data);
                    return;
                }

                // Filter Logic
                // If Act selected -> Show chapters from that Act
                // If ID is empty (Aucun) -> Show chapters with no Act
                const show = (selectedActId === "" && !data.actId) || (selectedActId !== "" && data.actId == selectedActId);

                if (show) {
                    addOption(data);
                }
            });

            // Restore selection if possible
            if (currentParentValue) {
                parentSelect.value = currentParentValue;
                if (parentSelect.value !== currentParentValue) {
                    parentSelect.value = "";
                }
            }
        }

        function addOption(data) {
            const opt = document.createElement('option');
            opt.value = data.value;
            opt.text = data.text;
            if (data.actId) opt.setAttribute('data-act-id', data.actId);
            parentSelect.appendChild(opt);
        }

        actSelect.addEventListener('change', filterChapters);

        // Initial run
        filterChapters();
    }

    });
</script>
