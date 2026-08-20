(function () {
    var modal = document.getElementById('bulk-confirm-modal');
    var form = document.getElementById('bulk-confirm-form');
    var bar = document.getElementById('bulk-bar');

    if (!modal || !form || !bar) {
        return;
    }

    var selectAll = document.getElementById('bulk-select-all');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.bulk-select-row'));
    var number = document.getElementById('bulk-bar-number');
    var applyBtns = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-apply]'));
    var clearBtn = document.getElementById('bulk-clear');
    var modalBody = document.getElementById('bulk-confirm-body');
    var modalSubmit = document.getElementById('bulk-confirm-submit');

    var lastToggled = null;

    function selected() {
        return rows.filter(function (row) {
            return row.checked;
        });
    }

    function sync() {
        var count = selected().length;

        number.textContent = count;
        bar.hidden = count === 0;

        rows.forEach(function (row) {
            row.closest('[data-bulk-row]').classList.toggle('is-selected', row.checked);
        });

        if (selectAll) {
            selectAll.checked = count > 0 && count === rows.length;
            // Without this a partial selection looks identical to none.
            selectAll.indeterminate = count > 0 && count < rows.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rows.forEach(function (row) {
                row.checked = selectAll.checked;
            });
            // Anchor the next shift-click somewhere meaningful rather than on
            // whichever row happened to be clicked before.
            lastToggled = null;
            sync();
        });
    }

    rows.forEach(function (row, index) {
        row.addEventListener('click', function (event) {
            // Shift-click selects the range, which is the difference between
            // a usable bulk tool and a tedious one on a long list.
            if (event.shiftKey && lastToggled !== null) {
                var from = Math.min(lastToggled, index);
                var to = Math.max(lastToggled, index);

                for (var i = from; i <= to; i++) {
                    rows[i].checked = row.checked;
                }
            }

            lastToggled = index;
            sync();
        });
    });

    clearBtn.addEventListener('click', function () {
        rows.forEach(function (row) {
            row.checked = false;
        });
        lastToggled = null;
        sync();
    });

    // The bar can offer more than one action, so each button carries its own
    // endpoint and wording rather than the script inferring them from a label.
    applyBtns.forEach(function (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var chosen = selected();

            if (chosen.length === 0) {
                return;
            }

            var noun = chosen.length === 1 ? 'order' : 'orders';

            // Rebuilt every time, so a selection changed after a cancelled
            // confirmation cannot leave stale ids behind.
            form.querySelectorAll('input[name="order_ids[]"]').forEach(function (input) {
                input.remove();
            });

            chosen.forEach(function (row) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'order_ids[]';
                hidden.value = row.value;
                form.appendChild(hidden);
            });

            // Set here rather than on the form, since a cancelled confirmation
            // must not leave the previous button's endpoint or method behind.
            form.action = applyBtn.getAttribute('data-bulk-action');
            form.querySelector('[name="_method"]').value = applyBtn.getAttribute('data-bulk-method') || 'PATCH';
            modalSubmit.classList.toggle('btn-danger', applyBtn.getAttribute('data-bulk-method') === 'DELETE');

            modalBody.textContent = 'Are you sure you want to '
                + applyBtn.getAttribute('data-bulk-verb') + ' ' + chosen.length + ' ' + noun + '?';
            modalSubmit.textContent = applyBtn.getAttribute('data-bulk-label') + ' ' + chosen.length + ' ' + noun;

            modal.showModal();
        });
    });

    sync();
})();
