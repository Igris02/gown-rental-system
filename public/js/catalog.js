document.addEventListener('DOMContentLoaded', function () {
    // Ask before cancelling a reservation
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Catalog filters. The Search button still works for typed searches, and without JS.
    var filterForm = document.querySelector('form[data-filters]');

    if (filterForm) {
        var wide = window.matchMedia('(min-width: 761px)');

        // On a computer the filters apply as soon as one is changed. On a phone the page would
        // reload while the panel is still open, so there the Apply filters button does it.
        filterForm.querySelectorAll('select, input[type="date"]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (wide.matches) {
                    filterForm.submit();
                }
            });
        });

        // The Filters button on phones folds the panel open and closed
        var toggle = filterForm.querySelector('[data-filter-toggle]');

        if (toggle) {
            toggle.addEventListener('click', function () {
                var open = !filterForm.classList.contains('is-open');

                filterForm.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    }

    // Photo zoom: click the photo to open the 1200 x 1600 version
    var triggers = document.querySelectorAll('[data-zoom-src]');
    var activeTrigger = null;
    var lightbox = document.querySelector('[data-lightbox]');

    if (triggers.length && lightbox) {
        var stage = lightbox.querySelector('[data-lightbox-stage]');
        var zoomImage = lightbox.querySelector('[data-lightbox-image]');
        var closeButton = lightbox.querySelector('[data-lightbox-close]');

        var openLightbox = function (trigger) {
            activeTrigger = trigger;
            // The large photo only starts loading when the customer asks for it
            zoomImage.src = trigger.dataset.zoomSrc;
            lightbox.classList.remove('is-actual');
            lightbox.hidden = false;
            document.body.classList.add('no-scroll');
            closeButton.focus();
        };

        var closeLightbox = function () {
            lightbox.hidden = true;
            zoomImage.removeAttribute('src');
            document.body.classList.remove('no-scroll');
            if (activeTrigger) {
                activeTrigger.focus();
            }
        };

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                openLightbox(trigger);
            });
        });
        closeButton.addEventListener('click', closeLightbox);

        // Clicking the dark area around the photo closes the zoom view
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox || event.target === stage) {
                closeLightbox();
            }
        });

        // Clicking the photo switches between fit-to-screen and full 1200 x 1600 size
        zoomImage.addEventListener('click', function () {
            lightbox.classList.toggle('is-actual');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }
});
