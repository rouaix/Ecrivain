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
<!--    <div class="form-group editor-tools-wrapper">-->
<!--        <div id="status" class="status-label status&#45;&#45;ok"></div>-->
<!--    </div>-->
    <input type="submit" value="Enregistrer">
    <a href="<?= ($base) ?>/project/<?= ($act['project_id']) ?>" class="button secondary">Annuler</a>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editorEl = document.getElementById('editor');
        var initialContentHtml = editorEl ? (editorEl.innerHTML || '') : '';

        // Init Main Editor with Tools
        QuillTools.init('#editor', {
            inputSelector: 'input[name="content"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            contextId: '<?= ($act['id']) ?>',
            contextType: 'act'
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

        var decodedContent = decodeHtmlDeep(initialContentHtml, 2);
        if (decodedContent && decodedContent !== QuillTools.quill.root.innerHTML) {
            QuillTools.quill.clipboard.dangerouslyPasteHTML(decodedContent);
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
        });

        var decodedResume = decodeHtmlDeep(initialResumeHtml, 2);
        if (decodedResume) {
            resumeQuill.clipboard.dangerouslyPasteHTML(decodedResume);
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
    });
</script>
