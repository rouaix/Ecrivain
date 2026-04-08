/**
 * Editor Tools Shared Logic
 * Provides Grammar Check, AI Generation, Text Analysis, and Word Count features.
 * 
 * Dependencies: TinyMCE
 */

console.log('Loading EditorTools...');

var EditorTools = {
    config: {
        baseUrl: '',
        csrfToken: '',
        endpoints: {
            grammarLog: '/ai/usage/log',
            synonyms: '/ai/synonyms',
            generate: '/ai/generate',
            grammarCheck: 'https://api.languagetool.org/v2/check'
        },
        selectors: {
            editorId: 'content',
            wordCountId: 'wordCount',
            grammarResultsId: 'grammar-results',
            grammarPanelId: 'grammar-panel',
            synonymsBoxId: 'synonymsBox',
            statusId: 'status',
            analysisBoxId: 'analysisBox',
            aiModalId: 'aiModal',
            aiModalTextId: 'aiModalText'
        },
        contextId: null,
        contextType: null
    },

    init: function (config) {
        console.log('Initializing EditorTools with config:', config);
        this.config = Object.assign({}, this.config, config);
        this.attachEventListeners();

        // Expose necessary functions to global scope for HTML callbacks
        window.closeGrammarPanel = this.closeGrammarPanel.bind(this);
        window.applyCorrection = this.applyCorrection.bind(this);
        window.runGrammarCheck = this.runGrammarCheck.bind(this);
        window.updateWordCount = this.updateWordCount.bind(this);
    },

    setupEditor: function (editor) {
        // Grammar Check Button
        editor.ui.registry.addButton('grammarcheck', {
            text: 'Grammaire',
            icon: 'spell-check',
            onAction: function () {
                EditorTools.runGrammarCheck();
            }
        });

        // Em Dash Button
        // Use fromCharCode to avoid source file encoding issues
        editor.ui.registry.addButton('emdash', {
            text: String.fromCharCode(8212), // Em Dash
            tooltip: 'Tiret cadratin',
            onAction: function () {
                editor.insertContent('&mdash;');
            }
        });

        // Remove Double Spaces
        editor.ui.registry.addButton('remove_doublespaces', {
            text: 'Nettoyer espaces',
            tooltip: 'Remplacer les espaces multiples (et insécables) par un espace simple',
            onAction: function () {
                editor.undoManager.transact(function () {
                    var walker = new tinymce.dom.TreeWalker(editor.getBody(), editor.getBody());
                    var nodesToChange = [];

                    // First pass: collect nodes to avoid mutation issues during walk
                    while (walker.next()) {
                        if (walker.current().nodeType === 3) { // Text Node
                            nodesToChange.push(walker.current());
                        }
                    }

                    // Second pass: modify
                    // Aggressive regex for any whitespace sequence of 2+ chars
                    // Includes NBSP (\u00a0), generic spec \s, and other unicode spaces
                    var regex = /[\s\u00a0\u2000-\u200B\u2028\u2029\u3000]{2,}/g;
                    var count = 0;

                    nodesToChange.forEach(function (node) {
                        var val = node.nodeValue;
                        if (val && regex.test(val)) {
                            node.nodeValue = val.replace(regex, ' ');
                            count++;
                        }
                    });

                    if (count > 0) {
                        var status = document.getElementById(EditorTools.config.selectors.statusId);
                        if (status) {
                            status.textContent = count + ' corrections d\'espaces effectuées.';
                            status.classList.remove('status--muted', 'status--error', 'status--info', 'status--warn');
                            status.classList.add('status--ok');
                            setTimeout(function () { status.textContent = ''; }, 3000);
                        }
                    } else {
                        var status = document.getElementById(EditorTools.config.selectors.statusId);
                        if (status) {
                            status.textContent = 'Aucun espace multiple trouvé.';
                            status.classList.remove('status--ok', 'status--error', 'status--info', 'status--warn');
                            status.classList.add('status--muted');
                            setTimeout(function () { status.textContent = ''; }, 3000);
                        }
                    }
                });
            }
        });

        // Group Dialogue
        editor.ui.registry.addButton('group_dialogue', {
            text: 'Grouper dialogues',
            tooltip: 'Grouper les lignes sélectionnées en un seul paragraphe',
            onAction: function () {
                editor.undoManager.transact(function () {
                    var blocks = editor.selection.getSelectedBlocks();
                    if (blocks.length < 2) return;

                    var firstBlock = blocks[0];
                    var content = '';

                    // Merge content
                    for (var i = 0; i < blocks.length; i++) {
                        var blockContent = blocks[i].innerHTML;
                        // Avoid double br if block already ends with it (unlikely in p but possible)
                        if (i > 0) content += '<br>';
                        content += blockContent;
                    }

                    // Set first block content
                    firstBlock.innerHTML = content;

                    // Remove other blocks
                    for (var i = 1; i < blocks.length; i++) {
                        editor.dom.remove(blocks[i]);
                    }

                    // Restore selection (optional, but good UX)
                    editor.selection.select(firstBlock);
                });
            }
        });
    },

    attachEventListeners: function () {
        // Word Count
        // Wait for editor to be ready before attaching events if possible, 
        // but tinymce.get() might be null here if called too early.
        // We rely on setupEditor mostly, but this backup is fine.
        var editor = tinymce.get(this.config.selectors.editorId);
        if (editor) {
            var self = this;
            editor.on('init input change keyup', function () { self.updateWordCount(); });
        }

        // Synonyms
        var synBtn = document.getElementById('synButton');
        if (synBtn) synBtn.addEventListener('click', this.handleSynonyms.bind(this));

        // AI Generation
        var continueBtn = document.getElementById('continueButton');
        if (continueBtn) continueBtn.addEventListener('click', this.handleContinue.bind(this));

        var rephraseBtn = document.getElementById('rephraseButton');
        if (rephraseBtn) rephraseBtn.addEventListener('click', this.handleRephrase.bind(this));

        // Text Analysis
        var analysisBtn = document.getElementById('analysisButton');
        if (analysisBtn) analysisBtn.addEventListener('click', this.handleAnalysis.bind(this));

        // AI Modal
        var aiInsertBtn = document.getElementById('aiBtnInsert');
        if (aiInsertBtn) aiInsertBtn.addEventListener('click', this.aiInsert.bind(this));

        var aiReplaceBtn = document.getElementById('aiBtnReplace');
        if (aiReplaceBtn) aiReplaceBtn.addEventListener('click', this.aiReplace.bind(this));

        var aiCopyBtn = document.getElementById('aiBtnCopy');
        if (aiCopyBtn) aiCopyBtn.addEventListener('click', this.aiCopy.bind(this));

        var aiCloseBtn = document.getElementById('aiBtnClose');
        if (aiCloseBtn) aiCloseBtn.addEventListener('click', this.closeAiModal.bind(this));
    },

    updateWordCount: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        if (!editor) return;
        var text = editor.getContent({ format: 'text' });
        var count = text.trim().length > 0 ? text.trim().split(/\s+/).length : 0;
        var el = document.getElementById(this.config.selectors.wordCountId);
        if (el) el.textContent = count;
    },

    // --- Grammar Check ---

    runGrammarCheck: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        if (!editor) return;

        var text = editor.getContent({ format: 'text' });
        var resultsDiv = document.getElementById(this.config.selectors.grammarResultsId);
        var panel = document.getElementById(this.config.selectors.grammarPanelId);

        if (panel) panel.classList.add('is-visible');
        if (resultsDiv) resultsDiv.innerHTML = '<p class="text-muted text-italic">Analyse en cours...</p>';

        var self = this;
        fetch(this.config.endpoints.grammarCheck, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ text: text, language: 'fr' })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                // Log Usage
                var tokenEstimate = Math.ceil(text.length / 4);
                self.logAiUsage('languagetool', tokenEstimate, 0, 'grammar_check');

                if (resultsDiv) {
                    if (data.matches.length === 0) {
                        resultsDiv.innerHTML = '<p class="text-success text-bold">Aucune faute détectée ! Félicitations.</p>';
                        return;
                    }

                    resultsDiv.innerHTML = '';
                    data.matches.forEach(function (match) {
                        var errorCard = document.createElement('div');
                        errorCard.className = 'grammar-error-card';

                        var suggestionsHtml = '';
                        match.replacements.slice(0, 3).forEach(function (rep) {
                            var val = rep.value.replace(/'/g, "\\'");
                            suggestionsHtml += '<button type="button" class="button small suggestion-button" onclick="applyCorrection(' + match.offset + ', ' + match.length + ', \'' + val + '\')">' + rep.value + '</button>';
                        });

                        errorCard.innerHTML =
                            '<div class="grammar-error-title">' + match.rule.description + '</div>' +
                            '<div class="grammar-error-snippet">"...' + text.substring(Math.max(0, match.offset - 10), match.offset) + '<span class="grammar-error-highlight">' + text.substring(match.offset, match.offset + match.length) + '</span>' + text.substring(match.offset + match.length, Math.min(text.length, match.offset + match.length + 10)) + '..."</div>' +
                            '<div class="grammar-error-message">' + match.message + '</div>' +
                            '<div class="suggestions">' + suggestionsHtml + '</div>';

                        resultsDiv.appendChild(errorCard);
                    });
                }
            })
            .catch(function (err) {
                console.error(err);
                if (resultsDiv) resultsDiv.innerHTML = '<p class="text-error">Erreur lors de l\'analyse (' + err.message + ').</p>';
            });
    },

    applyCorrection: function (offset, length, replacement) {
        var editor = tinymce.get(this.config.selectors.editorId);
        editor.focus();
        var walker = new tinymce.dom.TreeWalker(editor.getBody(), editor.getBody());
        var currentOffset = 0;
        var found = false;

        while (walker.next()) {
            if (walker.current().nodeType === 3) { // Text node
                var nodeText = walker.current().nodeValue;
                if (currentOffset <= offset && offset < currentOffset + nodeText.length) {
                    var relativeOffset = offset - currentOffset;
                    var rng = editor.dom.createRng();
                    rng.setStart(walker.current(), relativeOffset);
                    var endNode = walker.current();
                    var endOffset = Math.min(relativeOffset + length, nodeText.length);
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
            this.updateWordCount();
            this.runGrammarCheck();
        } else {
            alert("Impossible d'appliquer la correction automatiquement.");
        }
    },

    closeGrammarPanel: function () {
        var panel = document.getElementById(this.config.selectors.grammarPanelId);
        if (panel) panel.classList.remove('is-visible');
    },

    // --- AI Features ---

    handleSynonyms: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var selected = editor.selection.getContent({ format: 'text' }).trim();
        var synBox = document.getElementById(this.config.selectors.synonymsBoxId);
        var self = this;

        if (!selected) {
            alert('Sélectionnez un mot dans le texte pour obtenir des synonymes.');
            return;
        }

        fetch(this.config.baseUrl + this.config.endpoints.synonyms + '/' + encodeURIComponent(selected))
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (!Array.isArray(data) || data.length === 0) {
                    if (synBox) {
                        synBox.classList.add('is-visible');
                        synBox.innerHTML = '<em>Aucun synonyme trouvé.</em>';
                    }
                    return;
                }
                var list = data.map(function (word) { return '<a href="#" class="synonym" data-word="' + word + '">' + word + '</a>'; }).join(', ');
                if (synBox) {
                    synBox.innerHTML = 'Synonymes pour "' + selected + '" : ' + list;
                    synBox.classList.add('is-visible');
                    synBox.querySelectorAll('a.synonym').forEach(function (el) {
                        el.addEventListener('click', function (e) {
                            e.preventDefault();
                            editor.selection.setContent(e.target.getAttribute('data-word'));
                            synBox.classList.remove('is-visible');
                            self.updateWordCount();
                        });
                    });
                }
            });
    },

    callAiGeneration: function (task, prompt, context) {
        context = context || '';
        var statusLabel = document.getElementById(this.config.selectors.statusId);
        if (statusLabel) {
            statusLabel.textContent = 'IA en cours...';
            statusLabel.classList.remove('status--ok', 'status--muted', 'status--error', 'status--warn');
            statusLabel.classList.add('status--info');
        }

        return fetch(this.config.baseUrl + this.config.endpoints.generate, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.config.csrfToken },
            body: JSON.stringify({
                task: task,
                prompt: prompt,
                context: context,
                contextId: this.config.contextId,
                contextType: this.config.contextType
            })
        })
            .then(function (resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.text().then(function (text) {
                    try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON'); }
                });
            })
            .then(function (data) {
                if (statusLabel) statusLabel.textContent = '';
                if (data.error) {
                    alert('Erreur IA : ' + data.error);
                    return null;
                }

                // Display Debug Info
                if (data.debug) {
                    var debugContainer = document.getElementById('ai-debug-container');
                    var debugToggle = document.getElementById('ai-debug-toggle');
                    var footer = document.querySelector('footer');

                    if (!debugContainer) {
                        debugContainer = document.createElement('div');
                        debugContainer.id = 'ai-debug-container';
                        debugContainer.className = 'ai-debug-container';

                        if (footer) {
                            footer.parentNode.insertBefore(debugContainer, footer);
                        } else {
                            document.body.appendChild(debugContainer);
                        }
                    }

                    if (!debugToggle) {
                        debugToggle = document.createElement('button');
                        debugToggle.id = 'ai-debug-toggle';
                        debugToggle.textContent = 'Afficher les infos de debug IA';
                        debugToggle.className = 'ai-debug-toggle';
                        debugToggle.onclick = function () {
                            if (!debugContainer.classList.contains('is-visible')) {
                                debugContainer.classList.add('is-visible');
                                debugToggle.textContent = 'Masquer les infos de debug IA';
                                debugContainer.scrollIntoView({ behavior: 'smooth' });
                            } else {
                                debugContainer.classList.remove('is-visible');
                                debugToggle.textContent = 'Afficher les infos de debug IA';
                            }
                        };

                        // Insert Toggle BEFORE Container (so it is above it)
                        if (debugContainer.parentNode) {
                            debugContainer.parentNode.insertBefore(debugToggle, debugContainer);
                        } else if (footer) {
                            footer.parentNode.insertBefore(debugToggle, footer);
                        } else {
                            document.body.appendChild(debugToggle);
                        }
                    }

                    var debugContent = '<strong>--- AI Request Debug Info ---</strong>\n';
                    debugContent += 'Model: ' + data.debug.model + '\n';
                    debugContent += '-------------------------------------\n';
                    debugContent += '<strong>SYSTEM PROMPT:</strong>\n' + data.debug.system + '\n\n';
                    debugContent += '-------------------------------------\n';
                    debugContent += '<strong>USER PROMPT:</strong>\n' + data.debug.user + '\n';
                    debugContent += '-------------------------------------\n';

                    if (data.debug.system.includes('[INTEGRALITE DU MANUSCRIT (JSON)]')) {
                        debugContent += '<strong>FULL MANUSCRIPT DETECTED IN SYSTEM PROMPT:</strong> YES\n';
                        debugContent += '-------------------------------------\n';
                    }

                    debugContainer.innerHTML = debugContent;

                    // Do not auto-scroll if hidden
                    if (debugContainer.classList.contains('is-visible')) {
                        debugContainer.scrollIntoView({ behavior: 'smooth' });
                    }
                }

                return data.text;
            })
            .catch(function (err) {
                console.error(err);
                if (statusLabel) {
                    statusLabel.textContent = 'Erreur';
                    statusLabel.classList.remove('status--ok', 'status--muted', 'status--info', 'status--warn');
                    statusLabel.classList.add('status--error');
                }
                return null;
            });
    },

    handleContinue: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var text = editor.getContent({ format: 'text' });
        var context = text.slice(-1000);
        var self = this;
        this.callAiGeneration('continue', '', context).then(function (generated) {
            if (generated) self.showAiModal(generated, false);
        });
    },

    handleRephrase: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var selection = editor.selection.getContent({ format: 'text' }).trim();
        var self = this;
        if (!selection) {
            alert('Veuillez sélectionner le texte à reformuler.');
            return;
        }
        this.callAiGeneration('rephrase', selection).then(function (generated) {
            if (generated) self.showAiModal(generated, true);
        });
    },

    // --- AI Modal ---

    showAiModal: function (text, isReplacement) {
        var modal = document.getElementById(this.config.selectors.aiModalId);
        var textarea = document.getElementById(this.config.selectors.aiModalTextId);
        var replaceBtn = document.getElementById('aiBtnReplace');

        if (textarea) textarea.value = text;
        if (modal) modal.classList.add('is-visible');

        if (replaceBtn) {
            replaceBtn.disabled = !isReplacement;
            replaceBtn.classList.toggle('is-dimmed', !isReplacement);
        }
    },

    closeAiModal: function () {
        var modal = document.getElementById(this.config.selectors.aiModalId);
        if (modal) modal.classList.remove('is-visible');
        var textarea = document.getElementById(this.config.selectors.aiModalTextId);
        if (textarea) textarea.value = '';
    },

    aiInsert: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var textarea = document.getElementById(this.config.selectors.aiModalTextId);
        editor.selection.collapse(false);
        editor.insertContent(textarea.value);
        this.updateWordCount();
        this.closeAiModal();
    },

    aiReplace: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var textarea = document.getElementById(this.config.selectors.aiModalTextId);
        editor.selection.setContent(textarea.value);
        this.updateWordCount();
        this.closeAiModal();
    },

    aiCopy: function () {
        var textarea = document.getElementById(this.config.selectors.aiModalTextId);
        textarea.select();
        document.execCommand('copy');
        alert('Texte copié !');
    },

    // --- Analysis ---

    handleAnalysis: function () {
        var editor = tinymce.get(this.config.selectors.editorId);
        var text = editor.getContent({ format: 'text' }).toLowerCase().replace(/[^a-zà-ÿ\s]/g, ' ');
        var words = text.trim().split(/\s+/).filter(Boolean);
        var counts = {};
        words.forEach(function (w) { counts[w] = (counts[w] || 0) + 1; });

        var repeated = Object.keys(counts).filter(function (k) { return counts[k] > 2; });
        var analysisBox = document.getElementById(this.config.selectors.analysisBoxId);

        if (!analysisBox) return;

        if (repeated.length === 0) {
            analysisBox.innerHTML = '<em>Aucune répétition notable détectée.</em>';
        } else {
            var html = '<strong>Mots répétés plusieurs fois :</strong><ul>';
            repeated.forEach(function (w) { html += '<li>' + w + ' (' + counts[w] + ')</li>'; });
            html += '</ul>';
            analysisBox.innerHTML = html;
        }
        analysisBox.classList.add('is-visible');
    },

    // --- Helper ---

    logAiUsage: function (model, prompt_tokens, completion_tokens, feature) {
        fetch(this.config.baseUrl + this.config.endpoints.grammarLog, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.config.csrfToken },
            body: JSON.stringify({
                model: model,
                prompt_tokens: prompt_tokens,
                completion_tokens: completion_tokens,
                feature: feature
            })
        });
    }
};

window.EditorTools = EditorTools;
