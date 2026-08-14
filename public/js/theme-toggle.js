(function () {
    var toggle = document.getElementById('theme-toggle');

    if (!toggle) {
        return;
    }

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        toggle.setAttribute('data-theme', theme);
    }

    toggle.addEventListener('click', function () {
        var nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';

        applyTheme(nextTheme);

        fetch('/preferences/theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: JSON.stringify({ theme: nextTheme }),
            credentials: 'same-origin',
        }).catch(function () {});
    });
})();
