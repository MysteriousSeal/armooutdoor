(function () {
    var form = document.getElementById('admin-form');
    var modal = document.getElementById('self-demote-confirm-modal');
    var confirmBtn = document.getElementById('self-demote-confirm-submit');

    if (!form || !modal || !confirmBtn) {
        return;
    }

    var bypass = false;

    form.addEventListener('submit', function (event) {
        if (bypass) {
            return;
        }

        var selectedRole = form.querySelector('input[name="role"]:checked');
        if (selectedRole && selectedRole.value !== 'owner') {
            event.preventDefault();
            modal.showModal();
        }
    });

    confirmBtn.addEventListener('click', function () {
        bypass = true;
        modal.close();
        form.submit();
    });
})();
