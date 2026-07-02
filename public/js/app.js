/**
 * Moloni ON module — front-end interactions.
 *
 * Progressive enhancement only: confirmation prompts and a "select all"
 * checkbox. The module works without JavaScript.
 */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    onReady(function () {
        // Confirmation prompts for destructive/bulk actions.
        document.querySelectorAll('[data-moloni-confirm]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                var message = el.getAttribute('data-moloni-confirm');
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        // "Select all" checkbox in tables.
        document.querySelectorAll('[data-moloni-check-all]').forEach(function (master) {
            var table = master.closest('table');
            if (!table) {
                return;
            }

            master.addEventListener('change', function () {
                table.querySelectorAll('tbody input[type="checkbox"]').forEach(function (box) {
                    box.checked = master.checked;
                });
            });
        });

        // Log-context overlay: show a row's context in a modal instead of
        // printing raw JSON inline. Falls back to a <noscript> inline block.
        var overlay = document.querySelector('[data-moloni-overlay]');
        if (overlay) {
            var body = overlay.querySelector('[data-moloni-overlay-body]');

            var openOverlay = function (raw) {
                var text = raw;
                try {
                    text = JSON.stringify(JSON.parse(raw), null, 2);
                } catch (e) {
                    // Not JSON — show the raw string as-is.
                }
                body.textContent = text;
                overlay.hidden = false;
            };

            var closeOverlay = function () {
                overlay.hidden = true;
                body.textContent = '';
            };

            document.querySelectorAll('[data-moloni-log-context]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openOverlay(btn.getAttribute('data-moloni-log-context') || '');
                });
            });

            overlay.addEventListener('click', function (event) {
                // Close when clicking the backdrop or the close button.
                if (event.target === overlay || event.target.closest('[data-moloni-overlay-close]')) {
                    closeOverlay();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !overlay.hidden) {
                    closeOverlay();
                }
            });
        }
    });
})();
