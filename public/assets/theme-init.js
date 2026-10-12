(function () {
    var root = document.documentElement;
    var preference = root.getAttribute('data-theme-preference') || 'system';

    if (root.getAttribute('data-theme-source') !== 'user') {
        try {
            var stored = localStorage.getItem('rahandaz-theme');
            if (stored === 'light' || stored === 'dark' || stored === 'system') {
                preference = stored;
            }
        } catch (error) {
            preference = 'system';
        }
        root.setAttribute('data-theme-preference', preference);
    }

    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function resolve(next) {
        if (next === 'dark' || next === 'light') {
            return next;
        }

        return media.matches ? 'dark' : 'light';
    }

    function apply() {
        var current = root.getAttribute('data-theme-preference') || 'system';
        var resolved = resolve(current);
        root.setAttribute('data-theme', resolved);
        root.style.colorScheme = resolved;
    }

    apply();
    media.addEventListener('change', function () {
        if ((root.getAttribute('data-theme-preference') || 'system') === 'system') {
            apply();
        }
    });
})();
