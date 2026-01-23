/**
 * Quill Adapter & Shared Tools
 * Ports EditorTools functionality to Quill.js
 */

var QuillTools = {
    quill: null,
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

        // Initialize Quill
        this.toolbarOptions = [
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
        this.quill.on('text-change', function () {
            var html = self.quill.root.innerHTML;
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
    },

    styleCustomButtons: function () {
        // Styles are now handled in style.css to avoid JS injection issues and global conflicts.
    },

    setupCustomToolbarIcons: function () {
        // Deprecated: handled via Quill.import('ui/icons')
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
    }
};
