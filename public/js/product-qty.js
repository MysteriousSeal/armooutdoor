(function () {
    var form = document.querySelector('.add-to-cart-form');

    if (!form) {
        return;
    }

    var input = form.querySelector('.qty-stepper-input');

    if (!input) {
        return;
    }

    form.querySelectorAll('[data-qty-step]').forEach(function (button) {
        button.addEventListener('click', function () {
            var step = parseInt(button.getAttribute('data-qty-step'), 10) || 0;
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || min;
            var next = (parseInt(input.value, 10) || min) + step;

            input.value = Math.min(max, Math.max(min, next));
        });
    });
})();
