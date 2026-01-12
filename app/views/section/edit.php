<?php
/* Section editor view. Variables: $section, $project, $sectionType, $sectionTypeName, $errors */
?>
<h2>
    <?php echo $section ? 'Modifier' : 'Créer'; ?> -
    <?php echo htmlspecialchars($sectionTypeName ?? ''); ?>
</h2>
<p>Projet : <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>">
        <?php echo htmlspecialchars($project['title'] ?? ''); ?>
    </a></p>

<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li>
                    <?php echo htmlspecialchars($err); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post"
    action="<?php echo $base; ?>/project/<?php echo $project['id']; ?>/section/<?php echo $sectionType; ?><?php echo $section ? '?id=' . $section['id'] : ''; ?>"
    enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
    <div class="form-group">
        <label for="title">Titre (optionnel)</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($section['title'] ?? ''); ?>">
        <small>Laissez vide pour utiliser le nom par défaut :
            <?php echo htmlspecialchars($sectionTypeName); ?>
        </small>
    </div>

    <?php if ($sectionType === 'cover' || $sectionType === 'back_cover'): ?>
        <div class="form-group">
            <label for="image">Image</label>
            <?php if (!empty($section['image_path'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="<?php echo htmlspecialchars($section['image_path']); ?>" alt="Image actuelle"
                        style="max-width: 300px; max-height: 400px;">
                    <p><small>Image actuelle</small></p>
                </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/*">
            <small>Formats acceptés : JPG, PNG, WEBP. Taille recommandée : 600x800 pixels</small>
        </div>
    <?php endif; ?>

    <div id="editor-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
        <div class="form-group" style="flex: 1;">
            <label for="content">Contenu</label>
            <textarea id="content" name="content" rows="15"
                style="height:300px;"><?php echo htmlspecialchars($section['content'] ?? ''); ?></textarea>
            <?php if ($sectionType === 'cover' || $sectionType === 'back_cover'): ?>
                <small>Le contenu textuel sera affiché sous l'image de couverture.</small>
            <?php endif; ?>
            <p style="margin-top: 10px;">Compteur de mots : <span id="wordCount">0</span></p>
        </div>

        <!-- Grammar Sidebar -->
        <div id="grammar-panel"
            style="width: 320px; display: none; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-height: 700px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 1.1rem; color: #333;">Correction Grammaticale</h3>
                <button type="button" onclick="closeGrammarPanel()"
                    style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #aaa; line-height: 1;">&times;</button>
            </div>
            <div id="grammar-results">
                <p style="font-style: italic; color: #777; font-size: 0.9em;">Cliquez sur le bouton "Grammaire" dans la
                    barre d'outils pour lancer l'analyse.</p>
            </div>
        </div>
    </div>

    <input type="submit" value="Enregistrer">
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button secondary">Annuler</a>
</form>

<script>
    // Initialize TinyMCE
    tinymce.init({
        selector: '#content',
        language: 'fr_FR',
        plugins: 'wordcount autosave lists advlist charmap help link preview nonbreaking searchreplace visualchars visualblocks',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | grammarcheck | help',
        menubar: false,
        height: 500,
        promotion: false,
        branding: false,
        browser_spellcheck: true,
        setup: function (editor) {
            editor.ui.registry.addButton('grammarcheck', {
                text: 'Grammaire',
                icon: 'spell-check',
                onAction: function () {
                    runGrammarCheck();
                }
            });

            editor.on('init', function () {
                updateWordCount();
            });
            editor.on('input change keyup', function () {
                updateWordCount();
            });
        }
    });

    function runGrammarCheck() {
        const editor = tinymce.get('content');
        const text = editor.getContent({ format: 'text' });
        const resultsDiv = document.getElementById('grammar-results');
        const panel = document.getElementById('grammar-panel');

        panel.style.display = 'block';
        resultsDiv.innerHTML = '<p style="font-style: italic; color: #666;">Analyse en cours...</p>';

        fetch('https://api.languagetool.org/v2/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                text: text,
                language: 'fr'
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.matches.length === 0) {
                    resultsDiv.innerHTML = '<p style="color: green; font-weight: bold;">Aucune faute détectée ! Félicitations.</p>';
                    return;
                }

                resultsDiv.innerHTML = '';
                data.matches.forEach((match, index) => {
                    const errorCard = document.createElement('div');
                    errorCard.className = 'grammar-error-card';
                    errorCard.style = 'background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 12px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.03);';

                    let suggestionsHtml = '';
                    match.replacements.slice(0, 3).forEach(rep => {
                        suggestionsHtml += `<button type="button" class="button small" style="margin-right: 5px; margin-top: 5px;" onclick="applyCorrection(${match.offset}, ${match.length}, '${rep.value.replace(/'/g, "\\'")}')">${rep.value}</button>`;
                    });

                    errorCard.innerHTML = `
                    <div style="font-weight: bold; color: #d32f2f; margin-bottom: 5px; font-size: 0.9em;">${match.rule.description}</div>
                    <div style="background: #fff8f8; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-style: italic; font-size: 0.9em; border-left: 3px solid #ffcdd2;">"...${text.substring(Math.max(0, match.offset - 15), match.offset)}<span style="background: #ffcdd2; color: #b71c1c; font-weight: bold;">${text.substring(match.offset, match.offset + match.length)}</span>${text.substring(match.offset + match.length, Math.min(text.length, match.offset + match.length + 15))}..."</div>
                    <div style="font-size: 0.85em; color: #444; margin-bottom: 10px; line-height: 1.4;">${match.message}</div>
                    <div class="suggestions">${suggestionsHtml}</div>
                `;
                    resultsDiv.appendChild(errorCard);
                });
            })
            .catch(err => {
                console.error(err);
                resultsDiv.innerHTML = '<p style="color: red;">Erreur lors de l\'analyse. Vérifiez votre connexion.</p>';
            });
    }

    function applyCorrection(offset, length, replacement) {
        const editor = tinymce.get('content');
        editor.focus();

        const walker = new tinymce.dom.TreeWalker(editor.getBody(), editor.getBody());
        let currentOffset = 0;
        let found = false;

        while (walker.next()) {
            if (walker.current().nodeType === 3) {
                const nodeText = walker.current().nodeValue;
                if (currentOffset <= offset && offset < currentOffset + nodeText.length) {
                    const relativeOffset = offset - currentOffset;
                    const rng = editor.dom.createRng();
                    rng.setStart(walker.current(), relativeOffset);

                    let endNode = walker.current();
                    let endOffset = Math.min(relativeOffset + length, nodeText.length);

                    rng.setEnd(endNode, endOffset);
                    editor.selection.setRng(rng);
                    editor.insertContent(replacement);
                    found = true;
                    break;
                }
                currentOffset += nodeText.length;
            }
        }

        if (found) {
            updateWordCount();
            runGrammarCheck();
        } else {
            alert("Impossible d'appliquer la correction automatiquement.");
        }
    }

    function closeGrammarPanel() {
        document.getElementById('grammar-panel').style.display = 'none';
    }

    // Word count functionality (stripping HTML)
    function updateWordCount() {
        var editor = tinymce.get('content');
        if (!editor) return;
        var text = editor.getContent({ format: 'text' });
        var count = text.trim().length > 0 ? text.trim().split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = count;
    }
</script>
