(function () {
    var timeout = null;

    // Shares #store-toast with the other storefront scripts, so two toasts
    // can never sit on top of one another.
    function toast(text) {
        var el = document.getElementById('store-toast');

        if (!el) {
            el = document.createElement('div');
            el.id = 'store-toast';
            document.body.appendChild(el);
        }

        el.className = 'store-toast is-success';
        el.setAttribute('role', 'status');
        el.textContent = text;
        el.classList.add('is-visible');

        clearTimeout(timeout);
        timeout = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 2600);
    }

    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        // Older Safari and any non-secure context.
        var input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();

        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }

        document.body.removeChild(input);

        return ok ? Promise.resolve() : Promise.reject(new Error('copy-failed'));
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy-code]');

        if (!button) {
            return;
        }

        var code = button.getAttribute('data-copy-code');

        copy(code)
            .then(function () {
                toast(button.dataset.copiedMessage || 'Code copié.');
            })
            .catch(function () {
                // Copying failed, so leave the code selectable rather than
                // claiming success.
                // The button sits outside .voucher-code, so walk up to the
                // whole voucher to find the text to select.
                var text = button.closest('.voucher').querySelector('.voucher-code-text');

                if (text) {
                    window.getSelection().selectAllChildren(text);
                }
            });
    });
})();
