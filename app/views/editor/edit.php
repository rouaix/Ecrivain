<?php
/* Chapter editor view. Variables: $chapter, $project, $errors */
?>
<h2>Édition du chapitre «<?php echo htmlspecialchars($chapter['title'] ?? ''); ?>»</h2>
<p>Projet : <a
        href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title'] ?? ''); ?></a>
    <?php if ($currentAct): ?>
        &nbsp;&raquo;&nbsp; Acte : <strong>
            <?php echo htmlspecialchars($currentAct['title']); ?>
        </strong>
    <?php endif; ?>
    <?php if ($parentChapter): ?>
        &nbsp;&raquo;&nbsp; Chapitre : <strong>
            <?php echo htmlspecialchars($parentChapter['title']); ?>
        </strong>
    <?php endif; ?>
</p>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div
        style="background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
        <form method="post" action="<?php echo $base; ?>/chapter/<?php echo $chapter['id']; ?>/save" id="editorForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
    <div class="form-group">
        <label for="title">Titre</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($chapter['title'] ?? ''); ?>"
            required>
    </div>
    <div class="form-group">
        <label for="act_id">Appartient à l'acte</label>
        <select id="act_id" name="act_id">
            <option value="">-- Aucun (hors actes) --</option>
            <?php foreach ($acts as $act): ?>
                <option value="<?php echo $act['id']; ?>" <?php echo (($chapter['act_id'] ?? null) == $act['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($act['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="parent_id">Est un sous-chapitre de</label>
        <select id="parent_id" name="parent_id">
            <option value="">-- Aucun (chapitre principal) --</option>
            <?php foreach ($topChapters as $top): ?>
                <?php if ($top['id'] == $chapter['id'])
                    continue; ?>
                <option value="<?php echo $top['id']; ?>" <?php echo (($chapter['parent_id'] ?? null) == $top['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($top['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="editor-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
        <div class="form-group" style="flex: 1;">
            <label for="content">Contenu</label>
            <textarea id="content" name="content" rows="15"
                style="height:300px;"><?php echo htmlspecialchars($chapter['content'] ?? ''); ?></textarea>
            <p>Compteur de mots : <span id="wordCount">0</span></p>
        </div>

        <!-- Grammar Sidebar -->
        <div id="grammar-panel" style="width: 320px; display: none; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-height: 700px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.05); sticky: top 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 1.1rem; color: #333;">Correction Grammaticale</h3>
                <button type="button" onclick="closeGrammarPanel()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #aaa; line-height: 1;">&times;</button>
            </div>
            <div id="grammar-results">
                <p style="font-style: italic; color: #777; font-size: 0.9em;">Cliquez sur le bouton "Grammaire" dans la barre d'outils pour lancer l'analyse.</p>
            </div>
        </div>
    </div>
    <div class="form-group">
        <button type="button" id="synButton">Suggestions de synonymes</button>
        <button type="button" id="commentButton">Ajouter un commentaire</button>
        <button type="button" id="analysisButton">Analyse du texte</button>
        <div id="status" style="display:inline-block; margin-left:10px; color:green;"></div>
        <div id="synonymsBox"
            style="margin-top:8px; display:none; background:#f9f9f9; padding:10px; border:1px solid #ccc;"></div>
        <div id="analysisBox"
            style="margin-top:8px; display:none; background:#f9f9f9; padding:10px; border:1px solid #ccc;"></div>
    </div>
    <input type="submit" value="Enregistrer">
    <?php if (!empty($chapter['parent_id'])): ?>
        <?php
        $parentTitle = 'Chapitre';
        foreach ($topChapters as $top) {
            if ($top['id'] == $chapter['parent_id']) {
                $parentTitle = $top['title'];
                break;
            }
        }
        ?>
        <a href="<?php echo $base; ?>/chapter/<?php echo $chapter['parent_id']; ?>" class="button secondary">Retour à «
            <?php echo htmlspecialchars($parentTitle); ?> »</a>
    <?php endif; ?>
    <a href="<?php echo $base; ?>/project/<?php echo $project['id']; ?>" class="button secondary">Retour au projet</a>
</form>

<h3>Commentaires</h3>
<div id="commentsList">
    <?php if (!empty($comments)): ?>
        <ul>
            <?php foreach ($comments as $com): ?>
                <li><strong>« <?php echo htmlspecialchars(mb_substr($chapter['content'] ?? '', (int) $com['start_pos'], (int) $com['end_pos'] - (int) $com['start_pos'])); ?>»</strong> :
                    <?php echo htmlspecialchars($com['content'] ?? ''); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Aucun commentaire pour l’instant.</p>
    <?php endif; ?>
</div>

<script>
    // Initialize TinyMCE
    tinymce.init({
        selector: '#content',
        language: 'fr_FR',
        plugins: 'wordcount autosave lists advlist charmap help link preview nonbreaking searchreplace visualchars visualblocks',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | grammarcheck | help',
        menubar: false,
        height: 600,
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
                var draft = localStorage.getItem(storageKey);
                if (draft !== null && draft !== editor.getContent()) {
                    editor.setContent(draft);
                }
                updateWordCount();
            });
            editor.on('input change keyup', function () {
                localStorage.setItem(storageKey, editor.getContent());
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
                    errorCard.style = 'background: white; border: 1px solid #eee; border-radius: 6px; padding: 10px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);';

                    let suggestionsHtml = '';
                    match.replacements.slice(0, 3).forEach(rep => {
                        suggestionsHtml += `<button type="button" class="button small" style="margin-right: 5px; margin-top: 5px;" onclick="applyCorrection(${match.offset}, ${match.length}, '${rep.value.replace(/'/g, "\\'")}')">${rep.value}</button>`;
                    });

                    errorCard.innerHTML = `
                    <div style="font-weight: bold; color: #d32f2f; margin-bottom: 5px; font-size: 0.9em;">${match.rule.description}</div>
                    <div style="background: #fff8f8; padding: 5px; border-radius: 4px; margin-bottom: 8px; font-style: italic; font-size: 0.9em;">"...${text.substring(Math.max(0, match.offset - 10), match.offset)}<span style="background: #ffcccc; color: #b71c1c;">${text.substring(match.offset, match.offset + match.length)}</span>${text.substring(match.offset + match.length, Math.min(text.length, match.offset + match.length + 10))}..."</div>
                    <div style="font-size: 0.85em; color: #333; margin-bottom: 8px;">${match.message}</div>
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
        const text = editor.getContent({ format: 'text' });

        // This is a simplified replacement logic for plain text. 
        // TinyMCE handles HTML, so we use its selection API to be safe.
        // We find the nth character in the visible text.

        const content = editor.getContent();
        // For TinyMCE, we need to find the range that corresponds to the text offset.
        // A more robust way in TinyMCE is to use its own search/replace or custom bookmarks.
        // For now, let's use a standard search and replace on the content if it's unique enough,
        // or re-run the check after a manual fix.

        // Improved replacement for TinyMCE 7:
        // We use a temporary marker to navigate to the offset in the text representation.
        editor.focus();
        const walker = new tinymce.dom.TreeWalker(editor.getBody(), editor.getBody());
        let currentOffset = 0;
        let found = false;

        while (walker.next()) {
            if (walker.current().nodeType === 3) { // Text node
                const nodeText = walker.current().nodeValue;
                if (currentOffset <= offset && offset < currentOffset + nodeText.length) {
                    const relativeOffset = offset - currentOffset;
                    const rng = editor.dom.createRng();
                    rng.setStart(walker.current(), relativeOffset);

                    // Find end
                    let endNode = walker.current();
                    let endOffset = relativeOffset + length;

                    if (endOffset > nodeText.length) {
                        // Error spans multiple nodes (rare in plain text check)
                        // For simplicity, we limit to the current node
                        endOffset = nodeText.length;
                    }

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
            runGrammarCheck(); // Refresh results
        } else {
            alert("Impossible d'appliquer la correction automatiquement (le texte a peut-être changé).");
        }
    }

    function closeGrammarPanel() {
        document.getElementById('grammar-panel').style.display = 'none';
    }

    // Offline auto‑save and draft handling
    var chapterId = <?php echo (int) $chapter['id']; ?>;
    var storageKey = 'chapter_' + chapterId + '_draft';
    var statusLabel = document.getElementById('status');

    // Auto‑save to server every 15 seconds if online
    function autoSave() {
        if (!navigator.onLine) {
            statusLabel.textContent = 'Mode hors ligne';
            statusLabel.style.color = 'orange';
            return;
        }
        var editor = tinymce.get('content');
        if (!editor) return;

        var title = document.getElementById('title').value;
        var actId = document.getElementById('act_id').value;
        var parentId = document.getElementById('parent_id').value;
        var content = editor.getContent();
        var formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        formData.append('act_id', actId);
        formData.append('parent_id', parentId);

        fetch('<?php echo $base; ?>/chapter/' + chapterId + '/save', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: formData
        }).then(function (resp) {
            if (resp.ok) {
                statusLabel.textContent = 'Enregistré';
                statusLabel.style.color = 'green';
                localStorage.removeItem(storageKey);
            } else {
                statusLabel.textContent = 'Erreur de sauvegarde';
                statusLabel.style.color = 'red';
            }
        }).catch(function () {
            statusLabel.textContent = 'Erreur réseau';
            statusLabel.style.color = 'red';
        });
    }
    setInterval(autoSave, 15000);

    // Compute word count (stripping HTML)
    function updateWordCount() {
        var editor = tinymce.get('content');
        if (!editor) return;
        var text = editor.getContent({ format: 'text' });
        var count = text.trim().length > 0 ? text.trim().split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = count;
    }

    // Synonyms functionality
    var synButton = document.getElementById('synButton');
    var synBox = document.getElementById('synonymsBox');
    synButton.addEventListener('click', function () {
        var editor = tinymce.get('content');
        var selected = editor.selection.getContent({ format: 'text' }).trim();

        if (!selected) {
            alert('Sélectionnez un mot dans le texte pour obtenir des synonymes.');
            return;
        }

        fetch('<?php echo $base; ?>/synonyms/' + encodeURIComponent(selected))
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (!Array.isArray(data) || data.length === 0) {
                    synBox.style.display = 'block';
                    synBox.innerHTML = '<em>Aucun synonyme trouvé.</em>';
                    return;
                }
                var list = data.map(function (word) {
                    return '<a href="#" class="synonym" data-word="' + word + '">' + word + '</a>';
                }).join(', ');
                synBox.innerHTML = 'Synonymes pour "' + selected + '" : ' + list;
                synBox.style.display = 'block';
                synBox.querySelectorAll('a.synonym').forEach(function (el) {
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        var newWord = this.getAttribute('data-word');
                        editor.selection.setContent(newWord);
                        synBox.style.display = 'none';
                        updateWordCount();
                    });
                });
            });
    });

    // Add comment functionality
    var commentButton = document.getElementById('commentButton');
    commentButton.addEventListener('click', function () {
        var editor = tinymce.get('content');
        var selection = editor.selection.getContent({ format: 'text' }).trim();

        if (!selection) {
            alert('Sélectionnez une portion de texte pour commenter.');
            return;
        }

        var userContent = prompt('Saisissez votre commentaire :');
        if (!userContent) return;

        // NOTE: We're keeping the existing structure but it might be less precise with HTML content
        // In a real app we would use <span> markers, but we'll try to keep the legacy offset logic
        // by calculating offsets on the clean text. 
        var fullText = editor.getContent({ format: 'text' });
        var start = fullText.indexOf(selection); // Simplistic but matches existing backend expectations better than nothing
        var end = start + selection.length;

        fetch('<?php echo $base; ?>/chapter/' + chapterId + '/comment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify({ start: start, end: end, content: userContent })
        }).then(function (resp) { return resp.json().catch(function () { return null; }); }).then(function (data) {
            // Reload comments list
            fetch('<?php echo $base; ?>/chapter/' + chapterId + '/comments')
                .then(function (resp) { return resp.json(); })
                .then(function (comments) {
                    var listDiv = document.getElementById('commentsList');
                    if (comments.length === 0) {
                        listDiv.innerHTML = '<p>Aucun commentaire pour l’instant.</p>';
                        return;
                    }
                    var html = '<ul>';
                    comments.forEach(function (com) {
                        var snippet = com.snippet.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        html += '<li><strong>« ' + snippet + ' »</strong> : ' + com.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</li>';
                    });
                    html += '</ul>';
                    listDiv.innerHTML = html;
                });
        });
    });

    // Basic text analysis: detect repeated words and display counts
    var analysisButton = document.getElementById('analysisButton');
    var analysisBox = document.getElementById('analysisBox');
    analysisButton.addEventListener('click', function () {
        var editor = tinymce.get('content');
        var text = editor.getContent({ format: 'text' }).toLowerCase().replace(/[^a-zà-ÿ\s]/g, ' ');
        var words = text.trim().split(/\s+/).filter(Boolean);
        var counts = {};
        words.forEach(function (w) {
            counts[w] = (counts[w] || 0) + 1;
        });
        var repeated = Object.keys(counts).filter(function (k) { return counts[k] > 2; });
        if (repeated.length === 0) {
            analysisBox.innerHTML = '<em>Aucune répétition notable détectée.</em>';
        } else {
            var html = '<strong>Mots répétés plusieurs fois :</strong><ul>';
            repeated.forEach(function (w) {
                html += '<li>' + w + ' (' + counts[w] + ')</li>';
            });
            html += '</ul>';
            analysisBox.innerHTML = html;
        }
        analysisBox.style.display = 'block';
    });
</script>
