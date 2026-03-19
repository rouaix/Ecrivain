/**
 * pro-ui.js — Comportements de l'interface Pro
 * Sidebar toggle, user menu dropdown, slide panels, theme swatches
 */
(function () {
    'use strict';

    /* ── User menu dropdown ── */
    function initUserMenu() {
        var menu     = document.getElementById('proUserMenu');
        var btn      = document.getElementById('proUserBtn');
        var dropdown = document.getElementById('proUserDropdown');
        if (!btn || !dropdown) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = dropdown.classList.contains('is-open');
            dropdown.classList.toggle('is-open', !isOpen);
            if (menu) menu.classList.toggle('is-open', !isOpen);
        });

        document.addEventListener('click', function (e) {
            if (menu && !menu.contains(e.target)) {
                dropdown.classList.remove('is-open');
                menu.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                dropdown.classList.remove('is-open');
                if (menu) menu.classList.remove('is-open');
            }
        });
    }

    /* ── Sidebar toggle (mobile) ── */
    function initSidebarToggle() {
        var toggleBtn = document.getElementById('proSidebarToggle');
        var sidebar   = document.querySelector('.pro-sidebar');
        var backdrop  = document.getElementById('proBackdrop');
        if (!toggleBtn || !sidebar) return;

        function openSidebar() {
            sidebar.classList.add('is-open');
            if (backdrop) backdrop.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
        }

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }
    }

    /* ── Slide panels ── */
    function initSlidePanels() {
        var backdrop = document.getElementById('proBackdrop');

        // Ouvrir via data-panel-open="panelId"
        document.querySelectorAll('[data-panel-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = document.getElementById(btn.getAttribute('data-panel-open'));
                if (panel) {
                    panel.classList.add('is-open');
                    if (backdrop) backdrop.classList.add('is-visible');
                }
            });
        });

        // Fermer via bouton .pro-slide-panel-close
        document.querySelectorAll('.pro-slide-panel-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = btn.closest('.pro-slide-panel');
                if (panel) panel.classList.remove('is-open');
                // Ne ferme le backdrop que si aucun autre panel n'est ouvert
                var anyOpen = document.querySelector('.pro-slide-panel.is-open');
                if (!anyOpen && backdrop) backdrop.classList.remove('is-visible');
            });
        });

        // Fermer avec Echap
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.pro-slide-panel.is-open').forEach(function (p) {
                    p.classList.remove('is-open');
                });
                if (backdrop) backdrop.classList.remove('is-visible');
            }
        });
    }

    /* ── Sélecteur de thème ── */
    function initThemeSwatches() {
        var form  = document.getElementById('proThemeForm');
        var input = document.getElementById('proThemeInput');
        if (!form || !input) return;

        document.querySelectorAll('.pro-theme-swatch').forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-theme');
                form.submit();
            });
        });
    }

    /* ── Activer les tooltips title sur les boutons ── */
    function initTooltips() {
        // Utilise les title HTML natifs — pas de lib nécessaire
        // Amélioration future : custom tooltips
    }

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', function () {
        initUserMenu();
        initSidebarToggle();
        initSlidePanels();
        initThemeSwatches();
        initTooltips();
    });

}());
