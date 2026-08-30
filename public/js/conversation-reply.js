(function () {
    var form = document.getElementById('conversation-reply-form');
    var thread = document.getElementById('conversation-thread');

    if (!form || !thread) {
        return;
    }

    var textarea = form.querySelector('textarea[name="body"]');
    var submitButton = form.querySelector('button[type="submit"]');
    var toastTimeout = null;

    // The admin pages share one toast through armoToast; the account page
    // builds the storefront's own. Same gesture either way: the reply
    // confirms itself without the page moving.
    function confirmSent(text) {
        if (window.armoToast) {
            window.armoToast.show(text);
            return;
        }

        var toast = document.getElementById('store-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'store-toast';
            document.body.appendChild(toast);
        }

        toast.className = 'store-toast is-success';
        toast.setAttribute('role', 'status');
        toast.textContent = text;
        toast.classList.add('is-visible');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 4500);
    }

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

        var avatar = document.createElement('span');
        avatar.className = 'thread-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = data.authorInitials || '';

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

        // Only the admin view sends an edit URL; a customer cannot edit.
        if (data.editUrl) {
            item.dataset.editUrl = data.editUrl;

            var foot = document.createElement('div');
            foot.className = 'thread-foot';

            var edited = document.createElement('span');
            edited.className = 'thread-edited';
            edited.hidden = true;

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'thread-edit-btn';
            editBtn.setAttribute('data-thread-edit', '');
            editBtn.textContent = 'Edit';

            foot.appendChild(edited);
            foot.appendChild(editBtn);
            bubble.appendChild(foot);
        }

        item.appendChild(avatar);
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
            submitButton.classList.add('is-loading');
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

                // The reply is saved by now: a hiccup while drawing the
                // bubble must reload to show it, never re-submit it — the
                // form fallback below would post the same reply twice.
                try {
                    appendMessage(result.data);
                } catch (renderError) {
                    window.location.reload();
                    return;
                }

                textarea.value = '';
                confirmSent(result.data.message || 'Reply sent.');
            })
            .catch(function () {
                form.setAttribute('data-bypass', '1');
                form.submit();
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('is-loading');
                }
            });
    });
})();
