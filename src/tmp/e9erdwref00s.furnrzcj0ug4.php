<h2>Modifier l'acte</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li><?= ($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?= ($base) ?>/act/<?= ($act['id']) ?>/edit">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="title">Titre de l'acte *</label>
        <input type="text" id="title" name="title" value="<?= ($act['title']) ?>" required>
    </div>
    <div class="form-group">
        <label for="content">Contenu (Introduction)</label>
        <div id="editor" class="editor-surface editor-height-400">
            <?= ($this->raw($act['content']))."
" ?>
        </div>
        <input type="hidden" name="content" value="<?= ($this->esc($act['content'])) ?>">
    </div>
    <div class="form-group">
        <label for="resume">Résumé (Bref)</label>
        <div id="resume-editor" class="editor-surface editor-height-150">
            <?= ($this->raw($act['resume']))."
" ?>
        </div>
        <input type="hidden" name="resume" value="<?= ($this->esc($act['resume'])) ?>">
    </div>
    <div class="form-group">
        <label for="comment">Commentaire</label>
        <div id="comment-editor" class="editor-surface editor-height-150">
            <?= ($this->raw($act['comment']))."
" ?>
        </div>
        <input type="hidden" name="comment" value="<?= ($this->esc($act['comment'])) ?>">
    </div>
    <div class="form-group editor-tools-wrapper">
        <div id="status" class="status-label status--ok"></div>
    </div>
    <input type="submit" value="Enregistrer">
    <a href="<?= ($base) ?>/project/<?= ($act['project_id']) ?>" class="button secondary">Annuler</a>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Init Main Editor with Tools
        QuillTools.init('#editor', {
            inputSelector: 'input[name="content"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            contextId: '<?= ($act['id']) ?>',
            contextType: 'act'
        });

        var actId = '<?= ($act['id']) ?>';
        var storageKey = 'act_' + (actId || 'new') + '_draft';

        // Load Draft Logic for Main Content
        var savedContent = localStorage.getItem(storageKey);
        if (savedContent && savedContent !== QuillTools.quill.root.innerHTML) {
            QuillTools.quill.clipboard.dangerouslyPasteHTML(savedContent);
        }

        // Save Draft Logic for Main Content
        QuillTools.quill.on('text-change', function () {
            localStorage.setItem(storageKey, QuillTools.quill.root.innerHTML);
        });

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

        var resumeEl = document.getElementById('resume-editor');
        var initialResumeHtml = resumeEl ? (resumeEl.innerHTML || '') : '';

        var resumeQuill = new Quill('#resume-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: QuillTools.toolbarOptions, // Reuse full toolbar
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

        // Sync Resume
        resumeQuill.on('text-change', function () {
            var html = resumeQuill.root.innerHTML;
            document.querySelector('input[name="resume"]').value = html;
            localStorage.setItem(storageKey + '_resume', html);
        });

        // Load Draft Resume
        var savedResume = localStorage.getItem(storageKey + '_resume');
        if (savedResume && savedResume !== resumeQuill.root.innerHTML) {
            resumeQuill.clipboard.dangerouslyPasteHTML(savedResume);
        } else {
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
                localStorage.setItem(storageKey + '_comment', html);
            });

            var savedComment = localStorage.getItem(storageKey + '_comment');
            if (savedComment) {
                commentQuill.clipboard.dangerouslyPasteHTML(savedComment);
            } else {
                var decodedComment = decodeHtmlDeep(initialCommentHtml, 2);
                if (decodedComment) {
                    commentQuill.clipboard.dangerouslyPasteHTML(decodedComment);
                }
            }
        }

        // Offline auto‑save and draft handling
        var statusLabel = document.getElementById('status');
        function updateStatus(text, stateClass) {
            if (!statusLabel) return;
            statusLabel.textContent = text;
            statusLabel.classList.remove('status--ok', 'status--warn', 'status--error');
            if (stateClass) statusLabel.classList.add(stateClass);
        }

        function autoSave() {
            if (!actId) return;
            if (!navigator.onLine) {
                updateStatus('Mode hors ligne', 'status--warn');
                return;
            }
            var title = document.getElementById('title').value;
            var content = document.querySelector('input[name="content"]').value;
            var resume = document.querySelector('input[name="resume"]').value;
            var comment = document.querySelector('input[name="comment"]').value;

            var formData = new FormData();
            formData.append('title', title);
            formData.append('content', content);
            formData.append('resume', resume);
            formData.append('comment', comment);

            fetch('<?= ($base) ?>/act/' + actId + '/edit', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: formData
            }).then(function (resp) {
                if (resp.ok) {
                    updateStatus('Enregistré', 'status--ok');
                    localStorage.removeItem(storageKey);
                    localStorage.removeItem(storageKey + '_resume');
                    localStorage.removeItem(storageKey + '_comment');
                } else {
                    updateStatus('Erreur de sauvegarde', 'status--error');
                }
            }).catch(function () {
                updateStatus('Erreur réseau', 'status--error');
            });
        }
        setInterval(autoSave, 15000);
    });
</script>
