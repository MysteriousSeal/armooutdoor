(function () {
    const dropZone = document.getElementById('gallery-drop-zone');
    const picker = document.getElementById('gallery-files-picker');
    const mainInput = document.getElementById('image-file-input');
    const additionalInput = document.getElementById('gallery-images-input');
    const list = document.getElementById('gallery-images-list');
    const orderInput = document.getElementById('gallery-order-input');

    if (!dropZone || !picker || !mainInput || !additionalInput || !list || !orderInput) {
        return;
    }

    const MAX_TOTAL = 30;
    const DRAG_THRESHOLD = 6;
    const isEdit = list.dataset.hasMain === '1';

    /** @type {File[]} */
    let selectedFiles = [];
    let drag = null;

    function attachDocumentDragListeners() {
        document.addEventListener('pointermove', onDocumentPointerMove, true);
        document.addEventListener('pointerup', onDocumentPointerUp, true);
        document.addEventListener('pointercancel', onDocumentPointerCancel, true);
    }

    function removeDocumentDragListeners() {
        document.removeEventListener('pointermove', onDocumentPointerMove, true);
        document.removeEventListener('pointerup', onDocumentPointerUp, true);
        document.removeEventListener('pointercancel', onDocumentPointerCancel, true);
    }

    function onDocumentPointerMove(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        const dx = event.clientX - drag.startX;
        const dy = event.clientY - drag.startY;

        if (!drag.moved) {
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) {
                return;
            }

            beginDragVisual();
        }

        event.preventDefault();
        positionDragItem(event.clientX, event.clientY);
        updateDropTarget(event.clientX, event.clientY);
    }

    function onDocumentPointerUp(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        removeDocumentDragListeners();
        endDrag(true);
    }

    function onDocumentPointerCancel(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        removeDocumentDragListeners();
        endDrag(false);
    }

    function activeExistingItems() {
        return Array.from(list.querySelectorAll('.additional-images-item[data-image-key]:not(.is-marked-removed)'));
    }

    function allListItems() {
        return Array.from(list.querySelectorAll('.additional-images-item:not(.is-marked-removed)'));
    }

    function existingCount() {
        return activeExistingItems().length;
    }

    function maxNewFiles() {
        if (isEdit) {
            return Math.max(0, MAX_TOTAL - existingCount());
        }

        return MAX_TOTAL;
    }

    function setInputFiles(input, files) {
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    }

    function syncOrderInput() {
        const keys = allListItems()
            .map((item) => item.dataset.imageKey)
            .filter(Boolean);

        orderInput.value = keys.join(',');
    }

    function syncFormInputs() {
        const items = allListItems();
        const mainItem = list.querySelector('[data-image-key="main"]');
        const removeMainChecked = Boolean(mainItem?.classList.contains('is-marked-removed'));
        const removeMainCheckbox = document.getElementById('remove-main-checkbox');

        if (removeMainCheckbox) {
            removeMainCheckbox.checked = removeMainChecked;
        }

        // Map the visual list to form fields:
        // - first surviving tile is the cover (existing key via gallery_order, or a new file via the main input)
        // - remaining new files go to gallery_images[]
        const first = items[0];
        const newFilesInOrder = items.map((item) => item._file).filter(Boolean);

        if (!isEdit) {
            if (newFilesInOrder.length === 0) {
                setInputFiles(mainInput, []);
                setInputFiles(additionalInput, []);
            } else {
                setInputFiles(mainInput, [newFilesInOrder[0]]);
                setInputFiles(additionalInput, newFilesInOrder.slice(1));
            }
        } else if (first?._file) {
            // New file is cover — replaces the current one on save.
            setInputFiles(mainInput, [first._file]);
            setInputFiles(
                additionalInput,
                items.slice(1).map((item) => item._file).filter(Boolean),
            );
        } else {
            // Cover is an existing image (main or a promoted gallery image).
            setInputFiles(mainInput, []);
            setInputFiles(additionalInput, newFilesInOrder);
        }

        if (newFilesInOrder.length > 0 || isEdit) {
            picker.removeAttribute('required');
        }

        rebuildSelectedFilesFromDom();
        syncOrderInput();
        updateCoverBadge();
        updateRemoveButtons();
    }

    function updateRemoveButtons() {
        const activeCount = allListItems().length;
        const canRemove = activeCount > 1;

        list.querySelectorAll('.additional-images-item').forEach((item) => {
            const removeButton = item.querySelector('.additional-images-remove');
            if (!removeButton) {
                return;
            }

            if (item.classList.contains('is-marked-removed')) {
                removeButton.hidden = false;
                removeButton.disabled = false;
                return;
            }

            removeButton.hidden = !canRemove;
            removeButton.disabled = !canRemove;
        });
    }

    function updateCoverBadge() {
        const items = allListItems();

        items.forEach((item, index) => {
            item.classList.toggle('is-cover', index === 0);

            let badge = item.querySelector('.additional-images-badge');

            if (index === 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'additional-images-badge';
                    item.appendChild(badge);
                }
                badge.textContent = 'Cover';
            } else if (badge) {
                badge.remove();
            }
        });
    }

    function rebuildSelectedFilesFromDom() {
        const newItems = Array.from(list.querySelectorAll('.additional-images-item[data-new-index]'));
        selectedFiles = newItems.map((item) => item._file).filter(Boolean);
        newItems.forEach((item, index) => {
            item.dataset.newIndex = String(index);
        });
    }

    function createNewItem(file, index) {
        const item = document.createElement('div');
        item.className = 'additional-images-item';
        item.dataset.newIndex = String(index);
        item.tabIndex = 0;
        item.setAttribute('role', 'button');
        item.setAttribute('aria-label', 'New image. Press arrow keys to reorder.');

        const img = document.createElement('img');
        img.alt = '';
        img.draggable = false;
        item.appendChild(img);

        const reader = new FileReader();
        reader.onload = (event) => {
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);

        const handle = document.createElement('span');
        handle.className = 'upload-images-handle';
        handle.setAttribute('aria-hidden', 'true');
        handle.title = 'Drag to reorder';
        handle.textContent = '⋮⋮';
        item.appendChild(handle);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'additional-images-remove';
        removeButton.setAttribute('aria-label', 'Remove this image');
        removeButton.textContent = '×';
        removeButton.addEventListener('click', () => {
            item.remove();
            rebuildSelectedFilesFromDom();
            syncFormInputs();
        });
        item.appendChild(removeButton);

        item._file = file;
        bindItemInteractions(item);

        return item;
    }

    function addFiles(fileList) {
        const incoming = Array.from(fileList).filter((file) => file.type.startsWith('image/'));
        const room = maxNewFiles() - selectedFiles.length;
        const toAdd = incoming.slice(0, Math.max(0, room));

        toAdd.forEach((file) => {
            const index = selectedFiles.length;
            selectedFiles.push(file);
            list.appendChild(createNewItem(file, index));
        });

        rebuildSelectedFilesFromDom();
        syncFormInputs();
    }

    function afterReorder() {
        rebuildSelectedFilesFromDom();
        syncFormInputs();
    }

    function onPointerDown(event) {
        const item = event.currentTarget;

        if (event.target.closest('button')) {
            return;
        }

        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        if (item.classList.contains('is-marked-removed')) {
            return;
        }

        const rect = item.getBoundingClientRect();

        drag = {
            item,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            offsetX: event.clientX - rect.left,
            offsetY: event.clientY - rect.top,
            width: rect.width,
            height: rect.height,
            moved: false,
            placeholder: null,
        };

        item.setPointerCapture(event.pointerId);
        attachDocumentDragListeners();
    }

    function onPointerMove(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        const dx = event.clientX - drag.startX;
        const dy = event.clientY - drag.startY;

        if (!drag.moved) {
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) {
                return;
            }

            beginDragVisual();
        }

        event.preventDefault();
        positionDragItem(event.clientX, event.clientY);
        updateDropTarget(event.clientX, event.clientY);
    }

    function beginDragVisual() {
        drag.moved = true;

        const placeholder = document.createElement('div');
        placeholder.className = 'additional-images-item additional-images-placeholder';
        drag.item.after(placeholder);
        drag.placeholder = placeholder;

        drag.item.classList.add('is-dragging');
        drag.item.style.position = 'fixed';
        drag.item.style.width = drag.width + 'px';
        drag.item.style.height = drag.height + 'px';
        drag.item.style.zIndex = '80';
        drag.item.style.pointerEvents = 'none';

        positionDragItem(drag.startX, drag.startY);
    }

    function positionDragItem(clientX, clientY) {
        drag.item.style.left = (clientX - drag.offsetX) + 'px';
        drag.item.style.top = (clientY - drag.offsetY) + 'px';
    }

    function updateDropTarget(clientX, clientY) {
        const others = Array.from(list.querySelectorAll('.additional-images-item'))
            .filter((el) => el !== drag.item && el !== drag.placeholder);

        let closestEl = null;
        let closestDistance = Infinity;
        let closestRect = null;

        others.forEach((el) => {
            const rect = el.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const distance = Math.hypot(clientX - centerX, clientY - centerY);

            if (distance < closestDistance) {
                closestDistance = distance;
                closestEl = el;
                closestRect = rect;
            }
        });

        if (!closestEl) {
            return;
        }

        const before = clientX < closestRect.left + closestRect.width / 2;

        if (before) {
            list.insertBefore(drag.placeholder, closestEl);
        } else {
            list.insertBefore(drag.placeholder, closestEl.nextSibling);
        }
    }

    function endDrag(shouldActivate) {
        if (!drag) {
            removeDocumentDragListeners();
            return;
        }

        const { item, moved, placeholder, pointerId } = drag;

        try {
            if (moved && placeholder) {
                placeholder.replaceWith(item);
            }

            item.classList.remove('is-dragging');
            item.style.position = '';
            item.style.left = '';
            item.style.top = '';
            item.style.width = '';
            item.style.height = '';
            item.style.zIndex = '';
            item.style.pointerEvents = '';

            if (item.hasPointerCapture(pointerId)) {
                item.releasePointerCapture(pointerId);
            }

            if (moved) {
                afterReorder();
            }
        } finally {
            removeDocumentDragListeners();
            drag = null;
        }
    }

    function onPointerUp(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        endDrag(true);
    }

    function onPointerCancel(event) {
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        endDrag(false);
    }

    function onKeyDown(event) {
        const item = event.currentTarget;

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            const sibling = event.key === 'ArrowLeft' ? item.previousElementSibling : item.nextElementSibling;

            if (!sibling || !sibling.classList.contains('additional-images-item')) {
                return;
            }

            event.preventDefault();

            if (event.key === 'ArrowLeft') {
                list.insertBefore(item, sibling);
            } else {
                list.insertBefore(item, sibling.nextSibling);
            }

            afterReorder();
            item.focus();
        }
    }

    function bindItemInteractions(item) {
        item.addEventListener('pointerdown', onPointerDown);
        item.addEventListener('pointermove', onPointerMove);
        item.addEventListener('pointerup', onPointerUp);
        item.addEventListener('pointercancel', onPointerCancel);
        item.addEventListener('keydown', onKeyDown);
    }

    function bindExistingRemove(item) {
        const removeButton = item.querySelector('.additional-images-remove');
        const checkbox = item.querySelector('input[type="checkbox"]');

        if (!removeButton || !checkbox) {
            return;
        }

        removeButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const active = allListItems();
            const isRemoved = item.classList.contains('is-marked-removed');

            if (!isRemoved && active.length <= 1) {
                return;
            }

            checkbox.checked = !checkbox.checked;
            item.classList.toggle('is-marked-removed', checkbox.checked);
            removeButton.setAttribute('aria-label', checkbox.checked ? 'Undo remove' : 'Remove this image');
            removeButton.textContent = checkbox.checked ? '↶' : '×';
            syncFormInputs();
        });
    }

    list.querySelectorAll('.additional-images-item[data-image-key]').forEach((item) => {
        bindItemInteractions(item);
        bindExistingRemove(item);
    });

    dropZone.addEventListener('click', (event) => {
        if (event.target.closest('button')) {
            return;
        }

        picker.click();
    });

    picker.addEventListener('change', () => {
        if (picker.files?.length) {
            addFiles(picker.files);
            picker.value = '';
        }
    });

    dropZone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));

    dropZone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropZone.classList.remove('dragover');

        if (event.dataTransfer.files?.length) {
            addFiles(event.dataTransfer.files);
        }
    });

    const form = dropZone.closest('form');

    form?.addEventListener('submit', () => {
        rebuildSelectedFilesFromDom();
        syncFormInputs();
    });

    syncFormInputs();
})();
