<h2>Modifier le personnage « <?= ($character['name']) ?>»</h2>
<p><a class="button" href="<?= ($base) ?>/project/<?= ($project['id']) ?>/characters">Retour à la liste des
        personnages</a></p>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li><?= ($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post"
    action="<?= ($character['id'] ? ($base . '/character/' . $character['id'] . '/edit') : ($base . '/project/' . $project['id'] . '/character/create')) ?>">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="name">Nom *</label>
        <input type="text" id="name" name="name" value="<?= ($character['name']) ?>" required>
    </div>
    <div id="editor-wrapper" class="editor-wrapper">
        <div class="form-group editor-column">
            <label for="description">Description (enrichie)</label>
            <div id="editor" class="editor-surface editor-height-300">
                <?= ($this->raw($character['description']))."
" ?>
            </div>
            <input type="hidden" name="description" value="<?= ($this->esc($character['description'])) ?>">
            <p class="word-count">Compteur de mots : <span id="wordCount">0</span></p>
        </div>

    </div>
    </div>
    <div class="form-group">
        <label for="comment">Commentaire</label>
        <div id="comment-editor" class="editor-surface editor-height-200">
            <?= ($this->raw($character['comment']))."
" ?>
        </div>
        <input type="hidden" name="comment" value="<?= ($this->esc($character['comment'])) ?>">
    </div>

    <input type="submit" value="Enregistrer">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editorEl = document.getElementById('editor');
        var initialContentHtml = editorEl ? (editorEl.innerHTML || '') : '';

        QuillTools.init('#editor', {
            inputSelector: 'input[name="description"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            contextId: '<?= ($character['id']) ?>',
            contextType: 'character'
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
