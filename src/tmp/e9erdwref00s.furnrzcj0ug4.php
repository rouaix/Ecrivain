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
        <div id="editor" style="height: 400px; background: white; color: black;">
            <?= ($this->raw($act['content']))."
" ?>
        </div>
        <input type="hidden" name="content" value="<?= ($this->esc($act['content'])) ?>">
    </div>
    <div class="form-group">
        <label for="resume">Résumé (Bref)</label>
        <div id="resume-editor" style="height: 150px; background: white; color: black;">
            <?= ($this->raw($act['resume']))."
" ?>
        </div>
        <input type="hidden" name="resume" value="<?= ($this->esc($act['resume'])) ?>">
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

        // Init Resume Editor (Simple)
        // Init Resume Editor (Standard)
        // Init Resume Editor (Full)
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
            document.querySelector('input[name="resume"]').value = resumeQuill.root.innerHTML;
        });
    });
</script>