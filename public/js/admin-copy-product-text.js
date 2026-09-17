(function () {
    var RESET_AFTER_MS = 1600;

    var BOLD_STYLE = 'font-weight: bold;';
    var ITALIC_STYLE = 'font-style: italic;';

    function styled(doc, tagName, style, source) {
        var element = doc.createElement(tagName);
        element.setAttribute('style', style);

        while (source.firstChild) {
            element.appendChild(source.firstChild);
        }

        return element;
    }

    /**
     * NaturaBuy's editor only knows bold, italic and underline, and drops the
     * tags it would not have written itself on paste: headings, <strong> and
     * <em> came out as plain text. The copy speaks its language instead:
     * headings become bold paragraphs, <strong>/<em> become <b>/<i> (with the
     * inline style too, for editors that keep styles rather than tags), and
     * lists become bulleted lines, since the editor has no list either.
     */
    function forBasicEditor(html) {
        var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
        var root = doc.body.firstChild;

        root.querySelectorAll('strong').forEach(function (node) {
            node.replaceWith(styled(doc, 'b', BOLD_STYLE, node));
        });

        root.querySelectorAll('em').forEach(function (node) {
            node.replaceWith(styled(doc, 'i', ITALIC_STYLE, node));
        });

        root.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function (node) {
            var paragraph = doc.createElement('p');
            paragraph.appendChild(styled(doc, 'b', BOLD_STYLE, node));
            node.replaceWith(paragraph);
        });

        root.querySelectorAll('ul, ol').forEach(function (list) {
            var paragraph = doc.createElement('p');
            var items = Array.prototype.filter.call(list.children, function (child) {
                return child.tagName === 'LI';
            });

            items.forEach(function (item, index) {
                if (index > 0) {
                    paragraph.appendChild(doc.createElement('br'));
                }

                paragraph.appendChild(doc.createTextNode(list.tagName === 'OL' ? (index + 1) + '. ' : '• '));

                while (item.firstChild) {
                    paragraph.appendChild(item.firstChild);
                }
            });

            list.replaceWith(paragraph);
        });

        return root.innerHTML;
    }

    // Formatted when the browser allows it, so a rich editor keeps the
    // bold and the paragraphs; plain text otherwise.
    function copy(text, html) {
        if (html && window.ClipboardItem && navigator.clipboard.write) {
            return navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([forBasicEditor(html)], { type: 'text/html' }),
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                }),
            ]).catch(function () {
                return navigator.clipboard.writeText(text);
            });
        }

        return navigator.clipboard.writeText(text);
    }

    function flash(button, label) {
        if (!button.dataset.idleLabel) {
            button.dataset.idleLabel = button.textContent;
        }

        clearTimeout(button._copyTimer);
        button.textContent = label;
        button.classList.toggle('is-copied', label === 'Copied');

        button._copyTimer = setTimeout(function () {
            button.textContent = button.dataset.idleLabel;
            button.classList.remove('is-copied');
        }, RESET_AFTER_MS);
    }

    // Without a clipboard (no HTTPS, old browser) the buttons would do
    // nothing: better they are not there.
    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
        // Removed rather than hidden: `.btn` sets its own display, which
        // would win over the hidden attribute.
        document.querySelectorAll('.nb-copy').forEach(function (group) {
            group.remove();
        });

        return;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-copy-text]');

        if (!button) {
            return;
        }

        copy(button.getAttribute('data-copy-text'), button.getAttribute('data-copy-html')).then(function () {
            flash(button, 'Copied');
        }, function () {
            flash(button, 'Copy failed');
        });
    });
})();
