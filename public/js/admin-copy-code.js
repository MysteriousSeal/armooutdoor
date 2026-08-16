(function () {
    var toastTimeout = null;

    function showToast(text) {
        var toast = document.getElementById('admin-copy-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'admin-copy-toast';
            toast.className = 'admin-toast';
            document.body.appendChild(toast);
        }

        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2000);
    }

    function copyWithExecCommand(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();

        var succeeded = false;
        try {
            succeeded = document.execCommand('copy');
        } catch (e) {
            succeeded = false;
        }

        document.body.removeChild(input);

        return succeeded ? Promise.resolve() : Promise.reject(new Error('execCommand copy failed'));
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () {
                return copyWithExecCommand(text);
            });
        }

        return copyWithExecCommand(text);
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-copy-code]');

        if (!target) {
            return;
        }

        var code = target.getAttribute('data-copy-code');

        copyText(code).then(function () {
            showToast('Copied "' + code + '" to clipboard.');
        }).catch(function () {
            showToast('Could not copy "' + code + '".');
        });
    });
})();
