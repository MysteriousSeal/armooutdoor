/**
 * The Cdiscount listing page: only the chosen category's fields are shown,
 * and only they are sent. The others are disabled rather than merely hidden,
 * so a category switched away from leaves nothing behind in what is posted.
 */
(function () {
    var select = document.getElementById('octopia_template_id');

    if (!select) {
        return;
    }

    var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-octopia-fields]'));

    function apply() {
        blocks.forEach(function (block) {
            var current = block.getAttribute('data-octopia-fields') === select.value;

            block.hidden = !current;
            block.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !current;
            });
        });
    }

    select.addEventListener('change', apply);
    apply();
})();
