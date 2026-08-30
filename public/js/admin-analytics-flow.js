(function () {
    var svg = document.querySelector('[data-flow]');

    if (!svg) {
        return;
    }

    var links = Array.prototype.slice.call(svg.querySelectorAll('.admin-flow-link'));
    var nodes = Array.prototype.slice.call(svg.querySelectorAll('.admin-flow-node'));
    var labels = Array.prototype.slice.call(svg.querySelectorAll('.admin-flow-label'));

    function clear() {
        svg.classList.remove('is-hovering');
        links.concat(nodes, labels).forEach(function (el) {
            el.classList.remove('is-active');
        });
    }

    function activate(activeLinks, activeKeys) {
        svg.classList.add('is-hovering');

        links.forEach(function (link) {
            link.classList.toggle('is-active', activeLinks.indexOf(link) !== -1);
        });
        nodes.forEach(function (node) {
            node.classList.toggle('is-active', activeKeys.indexOf(node.getAttribute('data-node')) !== -1);
        });
        labels.forEach(function (label) {
            label.classList.toggle('is-active', activeKeys.indexOf(label.getAttribute('data-node-label')) !== -1);
        });
    }

    svg.addEventListener('mouseover', function (event) {
        var target = event.target.closest ? event.target.closest('[data-node], .admin-flow-link') : null;

        if (!target) {
            return;
        }

        if (target.hasAttribute('data-node')) {
            // A node lights up every ribbon touching it, and their far ends.
            var key = target.getAttribute('data-node');
            var activeLinks = links.filter(function (link) {
                return link.getAttribute('data-from') === key || link.getAttribute('data-to') === key;
            });
            var keys = [key];

            activeLinks.forEach(function (link) {
                keys.push(link.getAttribute('data-from'), link.getAttribute('data-to'));
            });

            activate(activeLinks, keys);
        } else {
            activate([target], [target.getAttribute('data-from'), target.getAttribute('data-to')]);
        }
    });

    svg.addEventListener('mouseleave', clear);
})();
