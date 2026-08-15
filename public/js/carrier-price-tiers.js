(function () {
    document.querySelectorAll('[data-tier-rows]').forEach(function (container) {
        var form = container.closest('form');

        if (!form) {
            return;
        }

        var addBtn = form.querySelector('[data-tier-add]');
        var template = form.querySelector('[data-tier-template]');
        var emptyMsg = container.querySelector('[data-tier-empty]');
        var counter = container.querySelectorAll('[data-tier-row]').length;

        function bindRemove(row) {
            var btn = row.querySelector('.tier-remove');

            if (btn) {
                btn.addEventListener('click', function () {
                    row.remove();
                });
            }
        }

        container.querySelectorAll('[data-tier-row]').forEach(bindRemove);

        if (!addBtn || !template) {
            return;
        }

        addBtn.addEventListener('click', function () {
            if (emptyMsg) {
                emptyMsg.remove();
                emptyMsg = null;
            }

            var html = template.innerHTML.replaceAll('__INDEX__', String(counter));
            container.insertAdjacentHTML('beforeend', html);

            var row = container.lastElementChild;
            bindRemove(row);

            var focusInput = row.querySelector('input');

            if (focusInput) {
                focusInput.focus();
            }

            counter++;
        });
    });
})();
