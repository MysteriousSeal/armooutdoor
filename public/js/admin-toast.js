(function () {
    var timeout = null;

    /**
     * One toast element for the whole admin. Two scripts each making their
     * own would sit at the same fixed position and overlap.
     */
    function show(text, duration, type) {
        var toast = document.getElementById('admin-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'admin-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }

        // Reset the class each time: a toast reused after an error must not
        // keep wearing the error coat.
        toast.className = 'admin-toast' + (type === 'error' ? ' is-error' : '');
        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(timeout);
        timeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, duration || 2600);
    }

    window.armoToast = { show: show };
})();
