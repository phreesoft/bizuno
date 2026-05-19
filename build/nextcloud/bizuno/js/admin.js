/**
 * Bizuno NextCloud app — admin settings auto-save.
 *
 * Persists the bizuno_url input to NextCloud's AppConfig store every
 * time the field loses focus or changes. No submit button: NC's admin
 * UX strongly favors live-saving inputs.
 *
 * OCP.AppConfig.setValue is the documented endpoint and handles CSRF +
 * auth automatically; nothing else is needed on the server side.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('bizuno-url');
        var savedMsg = document.getElementById('bizuno-url-saved');
        if (!input) {
            return;
        }

        var saveTimer = null;

        function showSaved() {
            if (!savedMsg) return;
            savedMsg.classList.remove('hidden');
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                savedMsg.classList.add('hidden');
            }, 2000);
        }

        function save() {
            var url = input.value.trim();
            // OCP.AppConfig is the legacy global; OC.AppConfig also works
            // on older NC versions. Try the modern one first.
            if (window.OCP && OCP.AppConfig && OCP.AppConfig.setValue) {
                OCP.AppConfig.setValue('bizuno', 'bizuno_url', url, {
                    success: showSaved,
                });
            } else if (window.OC && OC.AppConfig && OC.AppConfig.setValue) {
                OC.AppConfig.setValue('bizuno', 'bizuno_url', url, {
                    success: showSaved,
                });
            } else {
                console.error('[bizuno] No OC.AppConfig available — cannot save URL.');
            }
        }

        input.addEventListener('change', save);
        input.addEventListener('blur', save);
    });
})();
