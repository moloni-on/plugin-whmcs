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
    });
})();
