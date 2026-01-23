<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= ($this->esc($title)) ?></title>
    <link rel="manifest" href="<?= ($base) ?>/public/manifest.json">
    <meta name="theme-color" content="#3f51b5">
    <meta name="csrf-token" content="<?= ($csrfToken) ?>">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="<?= ($base) ?>/public/js/quill-adapter.js?v=16"></script>
    <link rel="stylesheet" href="<?= ($base) ?>/public/style.css?v=4">

    <?php $selectedTheme=isset($COOKIE['theme']) ? $COOKIE['theme'] : 'default'; ?>
    <link rel="stylesheet" href="<?= ($base) ?>/public/theme-<?= ($selectedTheme) ?>.css">
</head>

<body class="theme-<?= ($selectedTheme) ?>">
    <header>
        <h1>Assistant</h1>
        <nav class="main-nav">
            <?php if (isset($SESSION['user_id'])): ?>
                
                    <a href="<?= ($base) ?>/dashboard">Tableau de bord</a>
                    <a href="#" id="aiRequestBtn">Demande IA</a>
                    <a href="<?= ($base) ?>/ai/usage">Consommation IA</a>
                    <a href="<?= ($base) ?>/logout">Déconnexion</a>
                
                <?php else: ?>
                    <a href="<?= ($base) ?>/">Accueil</a>
                    <a href="<?= ($base) ?>/login">Connexion</a>
                    <a href="<?= ($base) ?>/register">Inscription</a>
                
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <div class="container">
            <?= ($this->raw($content))."
" ?>
        </div>
    </main>

    <!-- Shared Tools UI (Hidden by default) -->
    <!-- AI Modal -->
    <div id="aiModal" class="ai-modal ai-modal--compact">
        <h3>Assistant IA</h3>
        <textarea id="aiModalText" class="ai-modal-textarea ai-modal-textarea--sm"></textarea>
        <div class="ai-modal-actions">
            <button id="aiBtnReplace" class="button">Remplacer</button>
            <button id="aiBtnInsert" class="button">Insérer</button>
            <button id="aiBtnClose" class="button secondary">Fermer</button>
        </div>
    </div>
    <footer>
        &copy; <?= (date('Y')) ?> Daniel ROUAIX (Ce logiciel est privé et gratuit)
    </footer>

    <!-- Register service worker for PWA support -->
    <script>
        window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        document.querySelectorAll('form[method="post"]').forEach(function (form) {
            if (form.querySelector('input[name="csrf_token"]')) {
                return;
            }
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'csrf_token';
            hidden.value = window.CSRF_TOKEN;
            form.appendChild(hidden);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= ($base) ?>/public/service-worker.js').catch(function (err) {
                    console.error('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>

    <!-- AI Request Modal -->
    <?php if (isset($SESSION['user_id'])): ?>
        <div id="aiRequestModal" class="modal-overlay modal-overlay--front">
            <div class="modal-content modal-content--wide">
                <div class="modal-header">
                    <h3>Demande IA</h3>
                    <button class="modal-close" id="closeAiModal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="aiSystemPrompt">Prompt Système</label>
                        <textarea id="aiSystemPrompt" class="input-block " rows="3"
                            placeholder="Définissez le rôle de l'IA...">Tu es un assistant d'écriture expert.</textarea>
                    </div>

                    <div class="form-group">
                        <label for="aiUserPrompt">Prompt</label>
                        <textarea id="aiUserPrompt" class="input-block" rows="5"
                            placeholder="Votre demande..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="aiContextFile">Joindre un fichier (Max 1Mo)</label>
                        <input type="file" id="aiContextFile" class="input-block">
                    </div>

                    <div class="form-actions-right form-actions-right--sm">
                        <button class="button primary" id="sendAiRequest">Envoyer</button>
                    </div>

                    <div id="aiResponseContainer" class="ai-response">
                        <h4>Réponse IA</h4>
                        <div id="aiResponseContent" class="typography ai-response-content"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // AI Request Modal Logic
            const aiModal = document.getElementById('aiRequestModal');
            const aiBtn = document.getElementById('aiRequestBtn');
            const aiClose = document.getElementById('closeAiModal');
            const sendBtn = document.getElementById('sendAiRequest');
            const fileInput = document.getElementById('aiContextFile');
            const responseContainer = document.getElementById('aiResponseContainer');
            const responseContent = document.getElementById('aiResponseContent');
            const systemPromptInput = document.getElementById('aiSystemPrompt');

            if (aiBtn) {
                aiBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    aiModal.classList.add('is-visible');
                });
            }

            if (aiClose) {
                aiClose.addEventListener('click', function () {
                    aiModal.classList.remove('is-visible');
                });
            }

            // Close on click outside
            window.addEventListener('click', function (event) {
                if (event.target == aiModal) {
                    aiModal.classList.remove('is-visible');
                }
            });

            if (sendBtn) {
                sendBtn.addEventListener('click', function () {
                    const systemPrompt = systemPromptInput.value;
                    const userPrompt = document.getElementById('aiUserPrompt').value;
                    const file = fileInput.files[0];

                    if (!userPrompt) {
                        alert("Veuillez entrer un prompt.");
                        return;
                    }

                    responseContainer.classList.add('is-visible');
                    responseContent.innerHTML = '<em>Génération en cours...</em>';
                    sendBtn.disabled = true;

                    const processRequest = (fileContent = null) => {
                        fetch('<?= ($base) ?>/ai/generate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': window.CSRF_TOKEN
                            },
                            body: JSON.stringify({
                                system_prompt: systemPrompt,
                                prompt: userPrompt,
                                context: fileContent,
                                task: 'custom' // New task type for custom requests
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                sendBtn.disabled = false;
                                if (data.text) {
                                    // Convert newlines to breaks if needed, or rely on markdown parsing if available
                                    // For now simple text display
                                    responseContent.innerHTML = data.text.replace(/\n/g, '<br>');
                                } else if (data.error) {
                                    responseContent.innerHTML = '<span class="text-error">Erreur: ' + data.error + '</span>';
                                }
                            })
                            .catch(err => {
                                sendBtn.disabled = false;
                                console.error(err);
                                responseContent.innerHTML = '<span class="text-error">Erreur de communication.</span>';
                            });
                    };

                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            processRequest(e.target.result);
                        };
                        reader.onerror = function () {
                            alert("Erreur lors de la lecture du fichier.");
                            sendBtn.disabled = false;
                        };
                        reader.readAsText(file);
                    } else {
                        processRequest();
                    }
                });
            }
        });
    </script>
</body>

</html>
