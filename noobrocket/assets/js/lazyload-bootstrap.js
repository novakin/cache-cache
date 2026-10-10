/* The subscriber inlines this file after the existing lazyLoadOptions configuration. */
(function () {
    'use strict';

    var currentScript = document.currentScript || document.scripts[document.scripts.length - 1];
    var placeholder = currentScript.previousElementSibling;
    if (!placeholder || !placeholder.content) {
        // Browsers without template support execute its script normally.
        return;
    }
    var original = placeholder.content.firstElementChild;
    if (!original) {
        // A buffer optimizer may have combined and relocated the runtime.
        return;
    }
    var observer;
    var started = false;

    function startRuntime() {
        if (started) {
            return;
        }

        started = true;
        if (observer) {
            observer.disconnect();
        }

        // Clone the parser-created script without TrustedHTML/ScriptURL sinks.
        // This preserves the filtered attributes and the site's security policy.
        var script = original.cloneNode(true);
        if (placeholder.parentNode) {
            placeholder.parentNode.removeChild(placeholder);
        }
        (document.body || document.head || document.documentElement).appendChild(script);
    }

    function hasTargets() {
        var options = window.lazyLoadOptions;
        var configurations = Array.isArray(options) ? options : [options];
        try {
            for (var i = 0; i < configurations.length; i++) {
                var configuration = configurations[i];
                var container = configuration.container || document;
                if (container.querySelector(configuration.elements_selector || '.lazy')) {
                    return true;
                }
            }
        } catch (error) {
            // Let the original library handle unsupported filtered configuration.
            return true;
        }
        return false;
    }

    if (original.type || !original.hasAttribute('src') || !original.hasAttribute('async') || original.hasAttribute('defer')) {
        // Delay/defer rewrites must be visible in the live DOM before their
        // runtime snapshots scripts, including on initially empty pages.
        startRuntime();
        return;
    }

    if (!window.MutationObserver || !document.body || hasTargets()) {
        startRuntime();
        return;
    }

    // Keep initially empty pages ready for AJAX content and YouTube thumbnails
    // created at DOMContentLoaded. The library scans again when its fetch ends.
    observer = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            for (var j = 0; j < mutations[i].addedNodes.length; j++) {
                if (mutations[i].addedNodes[j].nodeType === 1) {
                    if (hasTargets()) {
                        startRuntime();
                    }
                    return;
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
