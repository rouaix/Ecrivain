/**
 * api-client.js — Centralized fetch wrapper
 * Auto-injects CSRF token from <meta name="csrf-token">.
 * All methods return a Promise resolving to the parsed JSON body,
 * or rejecting with { status, message } on HTTP error.
 */
const ApiClient = (() => {
    function getCsrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function handleResponse(res) {
        if (!res.ok) {
            return res.json()
                .catch(() => ({ message: res.statusText }))
                .then(body => Promise.reject({ status: res.status, message: body.message || body.error || res.statusText }));
        }
        const ct = res.headers.get('Content-Type') || '';
        if (ct.includes('application/json')) {
            return res.json();
        }
        return res.text();
    }

    /**
     * GET /url
     * @param {string} url
     * @param {Object} [params] — query string key/value pairs
     */
    function get(url, params) {
        if (params && Object.keys(params).length) {
            const qs = new URLSearchParams(params).toString();
            url = url + (url.includes('?') ? '&' : '?') + qs;
        }
        return fetch(url, {
            method: 'GET',
            headers: { 'X-Csrf-Token': getCsrf() },
            credentials: 'same-origin',
        }).then(handleResponse);
    }

    /**
     * POST /url with JSON body
     * @param {string} url
     * @param {Object} data
     */
    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': getCsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(data),
        }).then(handleResponse);
    }

    /**
     * POST /url with FormData or HTMLFormElement
     * @param {string} url
     * @param {FormData|HTMLFormElement} form
     */
    function postForm(url, form) {
        const fd = form instanceof HTMLFormElement ? new FormData(form) : form;
        // Inject CSRF if not already present
        if (!fd.has('csrf_token')) {
            fd.set('csrf_token', getCsrf());
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Csrf-Token': getCsrf() },
            credentials: 'same-origin',
            body: fd,
        }).then(handleResponse);
    }

    /**
     * DELETE /url (sends CSRF in header)
     * @param {string} url
     */
    function del(url) {
        return fetch(url, {
            method: 'DELETE',
            headers: { 'X-Csrf-Token': getCsrf() },
            credentials: 'same-origin',
        }).then(handleResponse);
    }

    /**
     * PUT /url with JSON body
     * @param {string} url
     * @param {Object} data
     */
    function put(url, data) {
        return fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': getCsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(data),
        }).then(handleResponse);
    }

    return { get, post, postForm, delete: del, put };
})();

/**
 * AppUI — utilitaires d'interface partagés.
 *
 * Remplace window.confirm() et les handlers .js-confirm dispersés dans les vues.
 * Centralise aussi les toasts de notification et les helpers de modals.
 */
const AppUI = (() => {

    // ── Confirm ────────────────────────────────────────────────────────────────

    /**
     * Affiche une boîte de confirmation stylisée.
     * @param {string} message
     * @param {string} [title='Confirmation']
     * @returns {Promise<boolean>}
     */
    function confirm(message, title = 'Confirmation') {
        return new Promise(resolve => {
            let overlay = document.getElementById('appui-confirm-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'appui-confirm-overlay';
                overlay.className = 'modal-overlay';
                overlay.innerHTML =
                    '<div class="modal-box" role="dialog" aria-modal="true">' +
                        '<div class="modal-header"><h3 class="modal-title" id="appui-confirm-title"></h3></div>' +
                        '<div class="modal-body"><p id="appui-confirm-message"></p></div>' +
                        '<div class="modal-footer">' +
                            '<button class="button secondary" id="appui-confirm-cancel">Annuler</button>' +
                            '<button class="button delete" id="appui-confirm-ok">Confirmer</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(overlay);
            }

            document.getElementById('appui-confirm-title').textContent   = title;
            document.getElementById('appui-confirm-message').textContent = message;

            function done(result) {
                overlay.classList.remove('is-visible');
                document.getElementById('appui-confirm-ok').onclick     = null;
                document.getElementById('appui-confirm-cancel').onclick  = null;
                resolve(result);
            }

            document.getElementById('appui-confirm-ok').onclick     = () => done(true);
            document.getElementById('appui-confirm-cancel').onclick  = () => done(false);
            overlay.onclick = e => { if (e.target === overlay) done(false); };

            overlay.classList.add('is-visible');
            document.getElementById('appui-confirm-ok').focus();
        });
    }

    // ── Toast notifications ────────────────────────────────────────────────────

    /**
     * Affiche un toast de notification temporaire.
     * @param {string} message
     * @param {'success'|'error'|'info'|'warning'} [type='success']
     * @param {number} [durationMs=3000]
     */
    function notify(message, type = 'success', durationMs = 3000) {
        let container = document.getElementById('appui-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'appui-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.textContent = message;
        container.appendChild(toast);

        // Trigger animation frame so transition applies
        requestAnimationFrame(() => toast.classList.add('toast--visible'));

        setTimeout(() => {
            toast.classList.remove('toast--visible');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, durationMs);
    }

    // ── Modals ─────────────────────────────────────────────────────────────────

    /** Ouvre un modal par son id. */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('is-visible');
    }

    /** Ferme un modal par son id. */
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('is-visible');
    }

    // ── Initialisation globale ────────────────────────────────────────────────

    /**
     * Active le handler de confirmation pour tous les éléments .js-confirm.
     * À appeler après chaque rendu dynamique ou une fois au DOMContentLoaded.
     */
    function initConfirmLinks() {
        document.querySelectorAll('.js-confirm:not([data-appui-bound])').forEach(el => {
            el.dataset.appuiBound = '1';
            el.addEventListener('click', async e => {
                e.preventDefault();
                const message = el.dataset.confirm || 'Confirmer cette action ?';
                const ok = await confirm(message);
                if (ok) {
                    window.location.href = el.href;
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initConfirmLinks);

    return { confirm, notify, openModal, closeModal, initConfirmLinks };
})();
