<h2>
    <?php if ($section['id']): ?>
        Modifier
        <?php else: ?>Créer
    <?php endif; ?> -
    <?= ($sectionTypeName)."
" ?>
</h2>
<p>Projet : <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>">
        <?= ($project['title'])."
" ?>
    </a></p>

<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li>
                    <?= ($err)."
" ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post"
    action="<?= ($base) ?>/project/<?= ($project['id']) ?>/section/<?= ($sectionType) ?><?= ($section['id'] ? '?id=' . $section['id'] : '') ?>"
    enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="title">Titre (optionnel)</label>
        <input type="text" id="title" name="title" value="<?= ($section['title']) ?>">
        <small>Laissez vide pour utiliser le nom par défaut :
            <?= ($sectionTypeName)."
" ?>
        </small>
    </div>

    <?php if ($sectionType === 'cover' || $sectionType === 'back_cover'): ?>
        <div class="form-group">
            <label for="image">Image</label>
            <?php if (!empty($section['image_path'])): ?>
                <div class="image-preview">
                    <img src="<?= ($section['image_path']) ?>" alt="Image actuelle" class="image-preview__img">
                    <p><small>Image actuelle</small></p>
                </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/*">
            <small>Formats acceptés : JPG, PNG, WEBP. Taille recommandée : 600x800 pixels</small>
        </div>
    <?php endif; ?>

    <div id="editor-wrapper" class="editor-wrapper">
        <div class="form-group editor-column">
            <label for="content">Contenu</label>
            <div id="editor" class="editor-surface editor-height-500">
                <?= ($this->raw($section['content']))."
" ?>
            </div>
            <input type="hidden" name="content" value="<?= ($this->esc($section['content'])) ?>">

            <?php if ($sectionType === 'cover' || $sectionType === 'back_cover'): ?>
                <small>Le contenu textuel sera affiché sous l'image de couverture.</small>
            <?php endif; ?>

            <p class="word-count">Compteur de mots : <span id="wordCount">0</span></p>

            <div class="editor-tools-wrapper">
                <div id="status" class="status-label status--ok"></div>
                <div id="synonymsBox" class="ai-box"></div>
                <div id="analysisBox" class="ai-box"></div>
            </div>
        </div>

    </div>
    </div>
    <div class="form-group">
        <label for="comment">Commentaire</label>
        <div id="comment-editor" class="editor-surface editor-height-200">
            <?= ($this->raw($section['comment']))."
" ?>
        </div>
        <input type="hidden" name="comment" value="<?= ($this->esc($section['comment'])) ?>">
    </div>

    <input type="submit" value="Enregistrer">
    <a href="<?= ($base) ?>/project/<?= ($project['id']) ?>" class="button secondary">Annuler</a>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        var editorEl = document.getElementById('editor');
        var initialContentHtml = editorEl ? (editorEl.innerHTML || '') : '';

        QuillTools.init('#editor', {
            inputSelector: 'input[name="content"]',
            baseUrl: '<?= ($base) ?>',
            csrfToken: '<?= ($csrfToken) ?>',
            contextId: '<?= ($section['id']) ?>',
            contextType: 'section'
        });

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

        var decodedContent = decodeHtmlDeep(initialContentHtml, 2);
        if (decodedContent && decodedContent !== QuillTools.quill.root.innerHTML) {
            QuillTools.quill.clipboard.dangerouslyPasteHTML(decodedContent);
        }
    });
</script>
