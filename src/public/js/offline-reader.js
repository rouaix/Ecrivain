/* offline-reader.js — IndexedDB cache for offline reading */
(function (global) {
    'use strict';

    var DB_NAME = 'ecrivain-offline';
    var DB_VERSION = 1;
    var STORE_NAME = 'projects';
    var _db = null;

    function openDb() {
        if (_db) return Promise.resolve(_db);
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                }
            };
            req.onsuccess = function (e) { _db = e.target.result; resolve(_db); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function saveProject(id, title, html) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE_NAME, 'readwrite');
                tx.objectStore(STORE_NAME).put({
                    id: String(id),
                    title: title,
                    html: html,
                    savedAt: new Date().toISOString()
                });
                tx.oncomplete = resolve;
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    function loadProject(id) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var req = db.transaction(STORE_NAME, 'readonly')
                    .objectStore(STORE_NAME).get(String(id));
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function listProjects() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var req = db.transaction(STORE_NAME, 'readonly')
                    .objectStore(STORE_NAME).getAll();
                req.onsuccess = function () { resolve(req.result); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function deleteProject(id) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE_NAME, 'readwrite');
                tx.objectStore(STORE_NAME).delete(String(id));
                tx.oncomplete = resolve;
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    global.OfflineReader = {
        saveProject: saveProject,
        loadProject: loadProject,
        listProjects: listProjects,
        deleteProject: deleteProject
    };

})(window);
