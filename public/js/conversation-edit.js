(function () {
    var thread = document.getElementById('conversation-thread');

    if (!thread) {
        return;
    }

    function bubbleOf(item) {
        return item.querySelector('.thread-bubble');
    }

    function closeEditor(item, originalHtml) {
        var bubble = bubbleOf(item);
        var editor = bubble.querySelector('.thread-editor');

        if (editor) {
            editor.remove();
        }

        var body = bubble.querySelector('.thread-body');
        if (body && originalHtml !== undefined) {
            body.innerHTML = originalHtml;
        }
        if (body) {
            body.hidden = false;
        }

        var foot = bubble.querySelector('.thread-foot');
        if (foot) {
            foot.hidden = false;
        }
    }

    function openEditor(item) {
        var bubble = bubbleOf(item);

        if (bubble.querySelector('.thread-editor')) {
            return;
        }

        var body = bubble.querySelector('.thread-body');
        var foot = bubble.querySelector('.thread-foot');
        var originalHtml = body.innerHTML;

        // innerText, not innerHTML: the body is rendered with <br>, and we
        // want the author to edit plain text with real newlines.
        var text = body.innerText;

        body.hidden = true;
        if (foot) {
            foot.hidden = true;
        }

        var editor = document.createElement('div');
        editor.className = 'thread-editor';

        var textarea = document.createElement('textarea');
        textarea.className = 'form-control';
        textarea.rows = 4;
        textarea.maxLength = 5000;
        textarea.value = text;

        var actions = document.createElement('div');
        actions.className = 'thread-editor-actions';

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-sm btn-secondary';
        cancel.textContent = 'Cancel';

        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'btn btn-sm btn-primary';
        save.textContent = 'Save';

        actions.appendChild(cancel);
        actions.appendChild(save);
        editor.appendChild(textarea);
        editor.appendChild(actions);
        bubble.appendChild(editor);

        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);

        cancel.addEventListener('click', function () {
            closeEditor(item, originalHtml);
        });

        save.addEventListener('click', function () {
            if (textarea.value.trim() === '') {
                textarea.classList.add('is-invalid');
                return;
            }

            textarea.classList.remove('is-invalid');
            save.disabled = true;
            cancel.disabled = true;

            var form = new FormData();
            form.append('_method', 'PATCH');
            form.append('body', textarea.value);

            fetch(item.dataset.editUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                },
                body: form,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        throw new Error('conversation-edit-failed');
                    }

                    var body = bubbleOf(item).querySelector('.thread-body');
                    // textContent then <br>: never trust the round-tripped body as HTML.
                    body.textContent = result.data.body;
                    body.innerHTML = body.innerHTML.replace(/\n/g, '<br>');

                    var edited = bubbleOf(item).querySelector('.thread-edited');
                    if (edited) {
                        edited.textContent = result.data.editedLabel;
                        edited.hidden = false;
                    }

                    closeEditor(item);
                })
                .catch(function () {
                    save.disabled = false;
                    cancel.disabled = false;
                    textarea.classList.add('is-invalid');
                });
        });
    }

    thread.addEventListener('click', function (event) {
        var button = event.target.closest('[data-thread-edit]');

        if (!button) {
            return;
        }

        var item = button.closest('.thread-item');
        if (item && item.dataset.editUrl) {
            openEditor(item);
        }
    });
})();
