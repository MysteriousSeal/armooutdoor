(function () {
    var form = document.getElementById('conversation-reply-form');
    var thread = document.getElementById('conversation-thread');

    if (!form || !thread) {
        return;
    }

    var textarea = form.querySelector('textarea[name="body"]');
    var submitButton = form.querySelector('button[type="submit"]');

    function setError(text) {
        var error = form.querySelector('.form-error');

        if (!text) {
            if (error) {
                error.remove();
            }
            textarea.classList.remove('is-invalid');
            return;
        }

        textarea.classList.add('is-invalid');

        if (!error) {
            error = document.createElement('p');
            error.className = 'form-error';
            textarea.insertAdjacentElement('afterend', error);
        }

        error.textContent = text;
    }

    function appendMessage(data) {
        var item = document.createElement('li');
        // Which side the new bubble belongs on depends on who is writing:
        // the admin view and the account view set this differently.
        item.className = 'thread-item ' + (form.dataset.threadItemClass || 'thread-item--admin');

        var bubble = document.createElement('div');
        bubble.className = 'thread-bubble';

        var meta = document.createElement('div');
        meta.className = 'thread-meta';

        var author = document.createElement('span');
        author.className = 'thread-author';
        author.textContent = data.authorLabel;

        var time = document.createElement('time');
        time.className = 'thread-time';
        time.textContent = data.sentAt;

        var body = document.createElement('p');
        body.className = 'thread-body';
        // textContent, not innerHTML: the body is author-written text.
        body.textContent = data.body;

        meta.appendChild(author);
        meta.appendChild(time);
        bubble.appendChild(meta);
        bubble.appendChild(body);
        item.appendChild(bubble);
        thread.appendChild(item);

        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', function (event) {
        if (form.hasAttribute('data-bypass')) {
            return;
        }

        event.preventDefault();

        if (textarea.value.trim() === '') {
            setError(form.dataset.requiredMessage || 'Write a reply before sending.');
            textarea.focus();
            return;
        }

        setError(null);

        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.status === 422 && result.data.errors && result.data.errors.body) {
                    setError(result.data.errors.body[0]);
                    return;
                }

                if (!result.ok) {
                    throw new Error('conversation-reply-failed');
                }

                appendMessage(result.data);
                textarea.value = '';
            })
            .catch(function () {
                form.setAttribute('data-bypass', '1');
                form.submit();
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    });
})();
