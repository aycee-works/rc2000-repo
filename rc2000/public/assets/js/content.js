/* assets/js/content.js - Manage content page and the content form page.
   Everything posts to admin/api/content.php (PHP does the real validation and
   permission checks; the checks here only give faster feedback). After a successful
   save the page reloads or returns to the list, where the flash message is shown. */

(function () {
    'use strict';

    var API = 'admin/api/content.php';
    var listUrl = RC.baseUrl() + '/admin/content/index.php';

    function showMessage(el, text) {
        if (!el) { return; }
        el.textContent = text;
        el.hidden = false;
    }

    /** Show a preview when the user picks an image file. */
    function wirePreview(input, img) {
        if (!input || !img) { return; }
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { img.hidden = true; return; }
            if (f.size > 5 * 1024 * 1024) {
                alert('That image is larger than 5 MB. Please choose a smaller one.');
                input.value = '';
                img.hidden = true;
                return;
            }
            img.src = URL.createObjectURL(f);
            img.hidden = false;
        });
    }

    function post(data, onSuccess, onError) {
        RC.api(API, { method: 'POST', body: data })
            .then(function (res) { res.success ? onSuccess(res) : onError(res.message); })
            .catch(function () { onError('Could not reach the server. Please try again.'); });
    }

    // ---------- Publish / Archive buttons in the lists ----------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-set-status]');
        if (!btn) { return; }
        var data = new FormData();
        data.append('action', 'set_status');
        data.append('entity', btn.getAttribute('data-entity'));
        data.append('id', btn.getAttribute('data-id'));
        data.append('status', btn.getAttribute('data-status'));
        btn.disabled = true;
        post(data, function () { window.location.reload(); }, function (msg) {
            btn.disabled = false;
            alert(msg);
        });
    });

    // ---------- Gallery popup ----------
    var dialog = document.getElementById('gallery-dialog');
    if (dialog) {
        var form = document.getElementById('gallery-form');
        var title = document.getElementById('gallery-dialog-title');
        var preview = document.getElementById('gallery-preview');
        var message = document.getElementById('gallery-message');
        var saveBtn = document.getElementById('gallery-save');
        var delBtn = document.getElementById('gallery-delete');
        var imageLabel = document.getElementById('gal-image-label');
        wirePreview(form.elements.image, preview);

        function openDialog(tile) {
            form.reset();
            message.hidden = true;
            preview.hidden = true;
            var editing = !!tile;
            title.textContent = editing ? 'Edit image' : 'Add image';
            imageLabel.textContent = editing
                ? 'Replace image (optional · JPG, PNG or WebP, max 5 MB)'
                : 'Image (JPG, PNG or WebP, max 5 MB)';
            form.elements.id.value = editing ? tile.getAttribute('data-id') : '0';
            form.elements.version.value = editing ? tile.getAttribute('data-version') : '';
            form.elements.title.value = editing ? tile.getAttribute('data-title') : '';
            form.elements.caption.value = editing ? tile.getAttribute('data-caption') : '';
            form.elements.status.value = editing ? tile.getAttribute('data-status') : 'published';
            delBtn.hidden = !editing;
            if (editing) {
                preview.src = tile.getAttribute('data-src');
                preview.hidden = false;
            }
            dialog.showModal();
        }

        document.addEventListener('click', function (e) {
            var edit = e.target.closest('[data-gallery-edit]');
            if (edit) { openDialog(edit); return; }
            if (e.target.closest('[data-gallery-new]')) { openDialog(null); }
        });
        document.getElementById('gallery-cancel').addEventListener('click', function () { dialog.close(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            message.hidden = true;
            if (form.elements.title.value.trim() === '') {
                showMessage(message, 'Please enter a title.');
                return;
            }
            if (form.elements.id.value === '0' && !form.elements.image.files.length) {
                showMessage(message, 'Please choose an image to upload.');
                return;
            }
            var data = new FormData(form);
            data.append('action', 'gallery_save');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
            post(data, function () { window.location.reload(); }, function (msg) {
                showMessage(message, msg);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            });
        });

        delBtn.addEventListener('click', function () {
            if (!window.confirm('Delete this image permanently? It will disappear from the public gallery.')) { return; }
            var data = new FormData();
            data.append('action', 'delete');
            data.append('entity', 'gallery');
            data.append('id', form.elements.id.value);
            delBtn.disabled = true;
            post(data, function () { window.location.reload(); }, function (msg) {
                showMessage(message, msg);
                delBtn.disabled = false;
            });
        });
    }

    // ---------- Story / announcement / trivia form page ----------
    var cform = document.getElementById('content-form');
    if (cform) {
        var cmsg = document.getElementById('form-message');
        var csave = document.getElementById('content-save');
        var cdel = document.getElementById('content-delete');
        wirePreview(document.getElementById('f-image'), document.getElementById('f-preview'));

        cform.addEventListener('submit', function (e) {
            e.preventDefault();
            cmsg.hidden = true;
            var data = new FormData(cform);
            data.append('action', cform.getAttribute('data-action'));
            csave.disabled = true;
            csave.textContent = 'Saving…';
            post(data, function () { window.location.href = listUrl; }, function (msg) {
                showMessage(cmsg, msg);
                csave.disabled = false;
                csave.textContent = 'Save';
            });
        });

        if (cdel) {
            cdel.addEventListener('click', function () {
                if (!window.confirm('Delete this item permanently? This cannot be undone. (To just hide it, set the status to Draft or Archived instead.)')) { return; }
                var data = new FormData();
                data.append('action', 'delete');
                data.append('entity', cdel.getAttribute('data-entity'));
                data.append('id', cdel.getAttribute('data-id'));
                cdel.disabled = true;
                post(data, function () { window.location.href = listUrl; }, function (msg) {
                    showMessage(cmsg, msg);
                    cdel.disabled = false;
                });
            });
        }
    }
})();
