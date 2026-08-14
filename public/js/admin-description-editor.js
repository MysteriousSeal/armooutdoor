(function () {
    if (typeof Quill === 'undefined') {
        return;
    }

    var toolbar = [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link'],
        ['clean'],
    ];

    document.querySelectorAll('.description-editor-group').forEach(function (group) {
        var host = group.querySelector('.description-editor');
        var textarea = group.querySelector('.description-editor-source');

        if (!host || !textarea) {
            return;
        }

        var quill = new Quill(host, {
            theme: 'snow',
            placeholder: textarea.getAttribute('placeholder') || '',
            modules: {
                toolbar: toolbar,
                clipboard: {
                    matchVisual: false,
                },
            },
        });

        quill.clipboard.addMatcher(Node.ELEMENT_NODE, function (node, delta) {
            if (node.tagName === 'SCRIPT' || node.tagName === 'STYLE' || node.tagName === 'IFRAME') {
                return new (Quill.import('delta'))();
            }

            return delta;
        });

        var initialHtml = textarea.value.trim();

        if (initialHtml) {
            quill.root.innerHTML = initialHtml;
        }

        var mode = 'visual';

        var modeBar = document.createElement('div');
        modeBar.className = 'description-editor-mode';
        modeBar.setAttribute('role', 'group');
        modeBar.setAttribute('aria-label', 'Editor mode');

        var visualBtn = document.createElement('button');
        visualBtn.type = 'button';
        visualBtn.className = 'description-editor-mode-btn is-active';
        visualBtn.dataset.mode = 'visual';
        visualBtn.textContent = 'Visual';

        var htmlBtn = document.createElement('button');
        htmlBtn.type = 'button';
        htmlBtn.className = 'description-editor-mode-btn';
        htmlBtn.dataset.mode = 'html';
        htmlBtn.textContent = 'HTML';

        modeBar.append(visualBtn, htmlBtn);
        group.insertBefore(modeBar, group.querySelector('.ql-toolbar') || host);

        textarea.classList.add('description-editor-html-input');
        textarea.removeAttribute('hidden');
        textarea.setAttribute('aria-label', 'HTML source');
        textarea.spellcheck = false;

        var toolbarEl = group.querySelector('.ql-toolbar');
        var containerEl = group.querySelector('.ql-container');

        var htmlFromQuill = function () {
            var html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
            var plain = quill.getText().replace(/ /g, ' ').trim();

            return plain === '' ? '' : html;
        };

        var syncTextareaFromQuill = function () {
            if (mode !== 'visual') {
                return;
            }

            textarea.value = htmlFromQuill();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        var applyMode = function (nextMode) {
            if (nextMode === mode) {
                return;
            }

            if (nextMode === 'html') {
                textarea.value = htmlFromQuill();
                mode = 'html';
            } else {
                var html = textarea.value.trim();
                quill.setContents([]);
                if (html) {
                    quill.clipboard.dangerouslyPasteHTML(html);
                }
                mode = 'visual';
                syncTextareaFromQuill();
            }

            group.classList.toggle('is-html-mode', mode === 'html');
            visualBtn.classList.toggle('is-active', mode === 'visual');
            htmlBtn.classList.toggle('is-active', mode === 'html');
            visualBtn.setAttribute('aria-pressed', mode === 'visual' ? 'true' : 'false');
            htmlBtn.setAttribute('aria-pressed', mode === 'html' ? 'true' : 'false');

            if (toolbarEl) {
                toolbarEl.hidden = mode === 'html';
            }
            if (containerEl) {
                containerEl.hidden = mode === 'html';
            }
        };

        visualBtn.addEventListener('click', function () {
            applyMode('visual');
        });
        htmlBtn.addEventListener('click', function () {
            applyMode('html');
        });

        quill.on('text-change', syncTextareaFromQuill);
        syncTextareaFromQuill();

        group.classList.remove('is-html-mode');
        visualBtn.setAttribute('aria-pressed', 'true');
        htmlBtn.setAttribute('aria-pressed', 'false');

        var form = textarea.closest('form');

        if (form) {
            form.addEventListener('submit', function () {
                if (mode === 'visual') {
                    syncTextareaFromQuill();
                }
            });
        }
    });
})();
