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

        // Les articles de blog acceptent des images, les fiches produit non :
        // c'est le gabarit qui le dit, en posant l'adresse d'envoi.
        var uploadUrl = group.dataset.imageUploadUrl || '';
        var groupToolbar = uploadUrl
            ? toolbar.slice(0, 4).concat([['image'], ['clean']])
            : toolbar;

        var quill = new Quill(host, {
            theme: 'snow',
            placeholder: textarea.getAttribute('placeholder') || '',
            modules: {
                toolbar: groupToolbar,
                clipboard: {
                    matchVisual: false,
                },
            },
        });

        if (uploadUrl) {
            quill.getModule('toolbar').addHandler('image', function () {
                var input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';

                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];

                    if (!file) {
                        return;
                    }

                    var body = new FormData();
                    body.append('file', file);

                    var token = document.querySelector('meta[name="csrf-token"]');

                    fetch(uploadUrl, {
                        method: 'POST',
                        body: body,
                        headers: {
                            'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                            Accept: 'application/json',
                        },
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('upload failed');
                            }

                            return response.json();
                        })
                        .then(function (payload) {
                            var range = quill.getSelection(true);
                            quill.insertEmbed(range.index, 'image', payload.url, 'user');
                            quill.setSelection(range.index + 1);
                        })
                        .catch(function () {
                            window.alert('Image upload failed.');
                        });
                });

                input.click();
            });
        }

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
