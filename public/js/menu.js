// Menu button for phones. The links stay visible if this file does not load.
(function () {
    var bar = document.querySelector('.topbar');
    var button = bar && bar.querySelector('[data-menu-toggle]');

    if (!bar || !button) {
        return;
    }

    var label = button.querySelector('[data-menu-label]');
    var phone = window.matchMedia('(max-width: 760px)');

    function setOpen(open) {
        bar.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');

        if (label) {
            label.textContent = open ? 'Close' : 'Menu';
        }
    }

    button.addEventListener('click', function () {
        setOpen(!bar.classList.contains('is-open'));
    });

    // Escape closes it and puts focus back on the button
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && bar.classList.contains('is-open')) {
            setOpen(false);
            button.focus();
        }
    });

    // Tapping anywhere outside the top bar closes it
    document.addEventListener('click', function (event) {
        if (bar.classList.contains('is-open') && !bar.contains(event.target)) {
            setOpen(false);
        }
    });

    // Turning the phone sideways or widening the window resets it
    function onResize(event) {
        if (!event.matches) {
            setOpen(false);
        }
    }

    if (phone.addEventListener) {
        phone.addEventListener('change', onResize);
    } else if (phone.addListener) {
        phone.addListener(onResize);
    }

    // Coming back with the Back button should not show a stale open menu
    window.addEventListener('pageshow', function () {
        setOpen(false);
    });
})();
