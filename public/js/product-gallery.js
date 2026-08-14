(function () {
    var mainImage = document.getElementById('product-detail-main-image');
    var thumbs = document.querySelectorAll('.product-detail-thumb');

    if (!mainImage || !thumbs.length) {
        return;
    }

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var src = thumb.dataset.fullSrc;

            if (!src) {
                return;
            }

            mainImage.src = src;

            thumbs.forEach(function (other) {
                other.classList.toggle('is-active', other === thumb);
            });
        });
    });
})();
