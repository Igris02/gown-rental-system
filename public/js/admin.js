document.addEventListener('DOMContentLoaded', function () {
    // Ask before deleting
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Photo preview and size check on the add and edit pages
    var imageInput = document.querySelector('[data-image-input]');
    var preview = document.querySelector('[data-image-preview]');
    var sizeError = document.querySelector('[data-image-error]');
    var MAX_BYTES = 4 * 1024 * 1024;

    if (imageInput && preview) {
        imageInput.addEventListener('change', function () {
            var file = imageInput.files[0];

            sizeError.hidden = true;

            if (!file) {
                preview.hidden = true;
                preview.removeAttribute('src');
                return;
            }

            if (file.size > MAX_BYTES) {
                imageInput.value = '';
                preview.hidden = true;
                preview.removeAttribute('src');
                sizeError.hidden = false;
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        });
    }

    // Stop double submits while a photo is uploading
    var form = document.querySelector('form[data-gown-form]');

    if (form) {
        var submitButton = form.querySelector('button[type="submit"]');
        var originalLabel = submitButton.textContent;

        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        });

        // Restore the button if the user comes back with the browser's back button
        window.addEventListener('pageshow', function () {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
        });
    }
});
