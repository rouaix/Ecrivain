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
