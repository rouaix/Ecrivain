/**
 * Quill Adapter & Shared Tools
 * Ports EditorTools functionality to Quill.js
 */

var QuillTools = {
    quill: null,
    activeQuill: null, // Tracks the most recently focused Quill instance
    config: {
        selector: '#editor',
        inputSelector: 'input[name="content"]',
        baseUrl: '',
        csrfToken: '',
        contextId: null,
        contextType: null,
        endpoints: {
            grammarLog: '/ai/usage/log',
            synonyms: '/ai/synonyms',
            generate: '/ai/generate',
            grammarCheck: 'https://api.languagetool.org/v2/check'
        }
    },

    getToolbarOptions: function() {
        return [
            ['undo', 'redo'],
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'header': 1 }, { 'header': 2 }],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'script': 'sub' }, { 'script': 'super' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
            [{ 'direction': 'rtl' }],
            [{ 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'font': [] }],
            [{ 'align': [] }],
            ['clean'],
            ['emdash', 'group_lines', 'remove_doublespaces'] // Custom handlers
        ];
    },

    // Simplified toolbar for mobile screens (touch-friendly, essential tools only)
    getMobileToolbarOptions: function() {
        return [
            ['undo', 'redo'],
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'header': 1 }, { 'header': 2 }],
            ['link'],
            ['emdash']
        ];
    },

    init: function (selector, config) {
        this.config.selector = selector || this.config.selector;
        this.config = Object.assign({}, this.config, config);

        // Register Custom Icons (Must be done before Quill init)
        // Register Custom Icons (Must be done before Quill init)
        var icons = Quill.import('ui/icons');
        icons['emdash'] = '<span class="ql-icon ql-icon-emdash">—</span>';
        icons['group_lines'] = '<span class="ql-icon ql-icon-group-lines">Grouper</span>';
        icons['remove_doublespaces'] = '<span class="ql-icon ql-icon-remove-doublespaces">NoDblSp</span>';

        // Fix for missing Undo/Redo icons in Snow theme
        icons['undo'] = '<svg viewbox="0 0 18 18"><polygon class="ql-fill ql-stroke" points="6 10 4 12 2 10 6 10"></polygon><path class="ql-stroke" d="M8.09,13.91A4.6,4.6,0,0,0,9,14,5,5,0,1,0,4,9"></path></svg>';
        icons['redo'] = '<svg viewbox="0 0 18 18"><polygon class="ql-fill ql-stroke" points="12 10 14 12 16 10 12 10"></polygon><path class="ql-stroke" d="M9.91,13.91A4.6,4.6,0,0,1,9,14a5,5,0,1,1,5-5"></path></svg>';

        // Initialize Quill — use simplified toolbar on mobile
        var isMobile = window.matchMedia('(max-width: 767px)').matches;
        this.toolbarOptions = isMobile ? this.getMobileToolbarOptions() : this.getToolbarOptions();

        this.quill = new Quill(this.config.selector, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: this.toolbarOptions,
                    handlers: {
                        'undo': function () { this.quill.history.undo(); },
                        'redo': function () { this.quill.history.redo(); },
                        'emdash': this.handleEmDash.bind(this),
                        'group_lines': this.handleGroupLines.bind(this),
                        'remove_doublespaces': this.handleRemoveDoubleSpaces.bind(this)
                    }
                }
            }
        });

        // Add extra styling to custom buttons to ensure they have width
        this.styleCustomButtons();

        // Sync to Hidden Input on Change
        var self = this;

        // Track this as the active Quill instance when focused
        QuillTools.activeQuill = this.quill;
        this.quill.on('selection-change', function (range) {
            if (range) QuillTools.activeQuill = self.quill;
        });

        this.quill.on('text-change', function () {
            var html = self.quill.root.innerHTML;
            html = self.cleanQuillHtml(html);
            var input = document.querySelector(self.config.inputSelector);
            if (input) input.value = html;
            self.updateWordCount();
        });

        // Init Word Count
        this.updateWordCount();

        // Attach AI/Tool Listeners (Buttons outside the editor)
        this.attachExternalListeners();

        // Expose global for HTML callbacks if needed
        window.EditorTools = this; // Backward compatibility for views calling EditorTools

        // Init dictation once (only on pages with an editor)
        if (!QuillTools.dictation.button) {
            QuillTools.dictation.init();
        }
    },

    styleCustomButtons: function () {
        // Styles are now handled in style.css to avoid JS injection issues and global conflicts.
    },

    setupCustomToolbarIcons: function () {
        // Deprecated: handled via Quill.import('ui/icons')
    },
    cleanQuillHtml: function (html) {
        if (!html || html.trim() === '') return html;
        var cleaned = html;
        cleaned = cleaned.replace(/(<p><br><\/p>\s*){2,}/g, '<p><br></p>');
        cleaned = cleaned.replace(/(<p>\s*<\/p>\s*){2,}/g, '');
        cleaned = cleaned.replace(/^(<p><br><\/p>\s*)+/, '');
        cleaned = cleaned.replace(/(<p><br><\/p>\s*)+$/, '<p><br></p>');
        return cleaned;
    },

    // --- Custom Handlers ---

    handleEmDash: function () {
        var range = this.quill.getSelection();
        if (range) {
            this.quill.insertText(range.index, '—');
        }
    },

    handleRemoveDoubleSpaces: function () {
        var text = this.quill.getText();
        // Regex for multiple spaces
        var regex = /[\s\u00a0]{2,}/g;
        var match;
        // We traverse backwards to avoid index shift issues? 
        // Actually, replacing text in Quill via delta is best, but replaceText works.
        // Simplest: Identify ranges and replace. 
        // NOTE: This might strip formatting if we just setText.
        // We must preserve formatting.

        // Iterating matches and replacing them one by one (backwards)
        var matches = [];
        while ((match = regex.exec(text)) !== null) {
            matches.push({ index: match.index, length: match[0].length });
        }

        if (matches.length === 0) {
            this.showStatus('Aucun double espace trouvé.', 'gray');
            return;
        }

        matches.reverse().forEach(m => {
            this.quill.deleteText(m.index, m.length);
            this.quill.insertText(m.index, ' ');
        });

        this.showStatus(matches.length + ' corrections effectuées.', 'green');
    },

    handleGroupLines: function () {
        var range = this.quill.getSelection();
        if (!range || range.length === 0) return;

        // In Quill, "lines" are usually Blocks.
        // We want to merge the selected blocks into one block, joining them with something?
        // User asked for "group lines" which in TinyMCE implementation joined with <br>.
        // Quill doesn't naturally support <br> inside a block easily without custom blots or just shifting to text.
        // However, we can try to join them with just spaces or shift+enter simulation (insertText \n same block??).
        // Actually, standard Quill behavior: \n creates new block. 
        // If we remove the \n between blocks, they merge.

        // Strategy: Get all newlines in the range and remove them?
        // That would merge paragraphs into one.

        var text = this.quill.getText(range.index, range.length);
        var matches = [];
        var regex = /\n/g;
        var match;
        while ((match = regex.exec(text)) !== null) {
            // Relative index
            matches.push(range.index + match.index);
        }

        // We replace \n with ' ' or nothing?
        // TinyMCE version used <br>. 
        // If we want <br> visual in Quill, it's hard. 
        // Let's assume merging paragraphs => remove newlines (merge) and maybe insert a space.

        if (matches.length > 0) {
            matches.reverse().forEach(idx => {
                this.quill.deleteText(idx, 1);
                this.quill.insertText(idx, ' '); // Join with space
            });
        }
    },

    // --- External Listeners (Synonyms, etc) ---

    attachExternalListeners: function () {
        // Word Count
        // (Handled in text-change)

        // Synonyms
        var synBtn = document.getElementById('synButton');
        if (synBtn) synBtn.addEventListener('click', this.handleSynonyms.bind(this));

        // AI Generation
        var continueBtn = document.getElementById('continueButton');
        if (continueBtn) continueBtn.addEventListener('click', this.handleContinue.bind(this));

        var rephraseBtn = document.getElementById('rephraseButton');
        if (rephraseBtn) rephraseBtn.addEventListener('click', this.handleRephrase.bind(this));

        // Analysis
        var analysisBtn = document.getElementById('analysisButton');
        if (analysisBtn) analysisBtn.addEventListener('click', this.handleAnalysis.bind(this));

        // AI Modal
        var aiInsertBtn = document.getElementById('aiBtnInsert');
        if (aiInsertBtn) aiInsertBtn.addEventListener('click', this.aiInsert.bind(this));

        var aiReplaceBtn = document.getElementById('aiBtnReplace');
        if (aiReplaceBtn) aiReplaceBtn.addEventListener('click', this.aiReplace.bind(this));

        var aiCloseBtn = document.getElementById('aiBtnClose');
        if (aiCloseBtn) aiCloseBtn.addEventListener('click', this.closeAiModal.bind(this));
    },

    updateWordCount: function () {
        var text = this.quill.getText();
        var count = text.trim().length > 0 ? text.trim().split(/\s+/).length : 0;
        var el = document.getElementById('wordCount');
        if (el) el.textContent = count;
    },

    showStatus: function (msg, color) {
        var s = document.getElementById('status');
        if (s) {
            s.textContent = msg;
            s.classList.remove('status--ok', 'status--muted', 'status--info', 'status--error', 'status--warn');
            if (color === 'green') s.classList.add('status--ok');
            if (color === 'gray') s.classList.add('status--muted');
            if (color === 'blue') s.classList.add('status--info');
            if (color === 'red') s.classList.add('status--error');
            setTimeout(() => s.textContent = '', 3000);
        }
    },

    // --- AI Handlers (Using Fetch wrapper) ---

    callAi: function (task, prompt, context) {
        this.showStatus('IA en cours...', 'blue');
        return fetch(this.config.endpoints.generate, {
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
            .then(r => r.json())
            .then(data => {
                this.showStatus('', 'black');
                if (data.error) throw new Error(data.error);
                return data.text;
            })
            .catch(e => {
                this.showStatus('Erreur IA', 'red');
                alert(e.message);
                return null;
            });
    },

    handleSynonyms: function () {
        var range = this.quill.getSelection();
        if (!range || range.length === 0) {
            alert('Sélectionnez un mot.');
            return;
        }
        var word = this.quill.getText(range.index, range.length);
        var box = document.getElementById('synonymsBox');

        fetch(this.config.baseUrl + this.config.endpoints.synonyms + '/' + encodeURIComponent(word))
            .then(r => r.json())
            .then(data => {
                if (box) {
                    box.classList.add('is-visible');
                    if (!data || data.length === 0) {
                        box.innerHTML = '<em>Pas de synonyme.</em>';
                        return;
                    }
                    var html = data.map(w => `< a href = "#" onclick = "QuillTools.replaceSelection('${w}'); return false;" > ${w}</a > `).join(', ');
                    box.innerHTML = 'Synonymes: ' + html;
                }
            });
    },

    replaceSelection: function (text) {
        var range = this.quill.getSelection();
        if (range) {
            this.quill.deleteText(range.index, range.length);
            this.quill.insertText(range.index, text);
            document.getElementById('synonymsBox').classList.remove('is-visible');
        }
    },

    handleContinue: function () {
        var text = this.quill.getText();
        var context = text.slice(-1000);
        this.callAi('continue', '', context).then(res => {
            if (res) this.showAiModal(res, false);
        });
    },

    handleRephrase: function () {
        var range = this.quill.getSelection();
        if (!range || range.length === 0) { alert('Sélectionnez du texte.'); return; }
        var text = this.quill.getText(range.index, range.length);

        this.callAi('rephrase', text).then(res => {
            if (res) this.showAiModal(res, true);
        });
    },

    handleAnalysis: function () {
        var text = this.quill.getText().toLowerCase().replace(/[^a-zà-ÿ\s]/g, ' ');
        var words = text.split(/\s+/).filter(Boolean);
        var counts = {};
        words.forEach(w => counts[w] = (counts[w] || 0) + 1);
        var repeated = Object.keys(counts).filter(w => counts[w] > 2);

        var box = document.getElementById('analysisBox');
        if (box) {
            box.classList.add('is-visible');
            box.innerHTML = repeated.length ? 'Répétitions: ' + repeated.join(', ') : 'Aucune répétition majeure.';
        }
    },

    // --- AI Modal Helpers ---
    showAiModal: function (text, isReplace) {
        var m = document.getElementById('aiModal');
        var t = document.getElementById('aiModalText');
        var btn = document.getElementById('aiBtnReplace');
        if (m && t) {
            t.value = text;
            m.classList.add('is-visible');
            if (btn) {
                btn.disabled = !isReplace;
                btn.classList.toggle('is-dimmed', !isReplace);
            }
        }
    },

    closeAiModal: function () {
        var m = document.getElementById('aiModal');
        if (m) m.classList.remove('is-visible');
    },

    aiInsert: function () {
        var t = document.getElementById('aiModalText');
        var range = this.quill.getSelection();
        // Insert at cursor or end
        var idx = range ? range.index + range.length : this.quill.getLength();
        this.quill.insertText(idx, t.value);
        this.closeAiModal();
    },

    aiReplace: function () {
        var t = document.getElementById('aiModalText');
        var range = this.quill.getSelection();
        if (range) {
            this.quill.deleteText(range.index, range.length);
            this.quill.insertText(range.index, t.value);
            this.closeAiModal();
        }
    },

    // ─── Grammar check (LanguageTool) ────────────────────────────────────────

    checkGrammar: function (ignoredWords, callback) {
        var text = this.quill.getText();
        if (!text.trim()) {
            callback([], null);
            return;
        }
        var params = new URLSearchParams({
            text: text,
            language: 'fr',
            disabledRules: 'WHITESPACE_RULE'
        });
        fetch('https://api.languagetool.org/v2/check', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var lower = (ignoredWords || []).map(function (w) { return w.toLowerCase(); });
            var matches = (data.matches || []).filter(function (m) {
                var word = text.substr(m.offset, m.length).trim().toLowerCase();
                return !lower.some(function (w) { return w === word; });
            });
            callback(matches, null);
        })
        .catch(function (err) { callback([], err); });
    },

    applyGrammarFix: function (offset, length, replacement) {
        this.quill.deleteText(offset, length);
        this.quill.insertText(offset, replacement);
    },

    // ─── Dictée vocale (Web Speech API) ──────────────────────────────────────

    dictation: {
        recognition: null,
        isRecording: false,
        button: null,
        indicator: null,

        init: function () {
            var SR = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (SR) {
                var self = this;
                this.recognition = new SR();
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.lang = 'fr-FR';

                this.recognition.onresult = function (e) { self.handleResult(e); };
                this.recognition.onerror  = function (e) { self.handleError(e); };
                this.recognition.onend    = function ()  { self.handleEnd(); };
            }

            // Le bouton est toujours créé (message si navigateur non supporté)
            this.createButton();
            this.createIndicator();
        },

        createButton: function () {
            var btn = document.createElement('button');
            btn.id   = 'dictation-btn';
            btn.type = 'button';
            btn.title = 'Dictée vocale — cliquer pour démarrer';
            btn.setAttribute('aria-label', 'Dictée vocale');
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm-1 1.93V18H9v2h6v-2h-2v-2.07A6 6 0 0 0 18 11h-2a4 4 0 0 1-8 0H6a6 6 0 0 0 5 5.93z"/></svg>';
            var self = this;
            btn.addEventListener('click', function () { self.toggle(); });
            document.body.appendChild(btn);
            this.button = btn;
        },

        createIndicator: function () {
            var el = document.createElement('div');
            el.id = 'dictation-indicator';
            document.body.appendChild(el);
            this.indicator = el;
        },

        toggle: function () {
            if (this.isRecording) { this.stop(); } else { this.start(); }
        },

        start: function () {
            if (!this.recognition) {
                alert('La dictée vocale n\'est pas disponible dans ce navigateur.\nUtilisez Chrome ou Edge.');
                return;
            }
            var quill = QuillTools.activeQuill || QuillTools.quill;
            if (!quill) {
                alert('Positionnez le curseur dans un éditeur avant de démarrer la dictée.');
                return;
            }
            try {
                this.recognition.start();
                this.isRecording = true;
                this.button.classList.add('is-recording');
                this.button.title = 'Dictée en cours — cliquer pour arrêter';
            } catch (e) { /* Déjà démarrée */ }
        },

        stop: function () {
            this.recognition.stop();
            this.isRecording = false;
            if (this.button) {
                this.button.classList.remove('is-recording');
                this.button.title = 'Dictée vocale — cliquer pour démarrer';
            }
            if (this.indicator) {
                this.indicator.textContent = '';
                this.indicator.classList.remove('is-visible');
            }
        },

        handleResult: function (event) {
            var quill = QuillTools.activeQuill || QuillTools.quill;
            if (!quill) return;

            var finalTranscript   = '';
            var interimTranscript = '';

            for (var i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                } else {
                    interimTranscript += event.results[i][0].transcript;
                }
            }

            // Afficher les résultats intermédiaires dans l'indicateur
            if (this.indicator) {
                this.indicator.textContent = interimTranscript;
                this.indicator.classList.toggle('is-visible', interimTranscript.length > 0);
            }

            if (finalTranscript) {
                if (this.indicator) {
                    this.indicator.textContent = '';
                    this.indicator.classList.remove('is-visible');
                }
                var sel = quill.getSelection(true);
                var idx = sel ? sel.index : quill.getLength() - 1;
                // Ajouter un espace avant si le caractère précédent n'est pas un séparateur
                var prevChar = idx > 0 ? quill.getText(idx - 1, 1) : '';
                var needsSpace = prevChar !== '' && prevChar !== ' ' && prevChar !== '\n';
                var text = (needsSpace ? ' ' : '') + finalTranscript;
                quill.insertText(idx, text, 'user');
                quill.setSelection(idx + text.length);
            }
        },

        handleError: function (event) {
            if (event.error === 'not-allowed') {
                alert('Accès au microphone refusé. Vérifiez les permissions de votre navigateur.');
            } else if (event.error !== 'no-speech' && event.error !== 'aborted') {
                console.warn('Dictée vocale — erreur :', event.error);
            }
            this.isRecording = false;
            if (this.button) {
                this.button.classList.remove('is-recording');
                this.button.title = 'Dictée vocale — cliquer pour démarrer';
            }
            if (this.indicator) {
                this.indicator.textContent = '';
                this.indicator.classList.remove('is-visible');
            }
        },

        handleEnd: function () {
            // Redémarrer automatiquement si la reconnaissance s'arrête seule
            if (this.isRecording) {
                try { this.recognition.start(); } catch (e) {
                    this.isRecording = false;
                    if (this.button) this.button.classList.remove('is-recording');
                }
            }
        }
    }
};

