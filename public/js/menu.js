// Side bar menu for phones. If this file does not load, the links stay visible under the top bar.
(function () {
    var bar = document.querySelector('.topbar');
    var button = bar && bar.querySelector('[data-menu-toggle]');
    var drawer = bar && bar.querySelector('[data-menu-drawer]');

    if (!bar || !button || !drawer) {
        return;
    }

    var closeButton = drawer.querySelector('[data-menu-close]');
    var backdrop = bar.querySelector('[data-menu-backdrop]');
    var phone = window.matchMedia('(max-width: 760px)');
    var root = document.documentElement;

    function isOpen() {
        return bar.classList.contains('is-open');
    }

    function setOpen(open, moveFocus) {
        bar.classList.toggle('is-open', open);
        root.classList.toggle('menu-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');

        if (!moveFocus) {
            return;
        }

        if (open && closeButton) {
            closeButton.focus();
        } else if (!open) {
            button.focus();
        }
    }

    button.addEventListener('click', function () {
        setOpen(!isOpen(), true);
    });

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            setOpen(false, true);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false, true);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!isOpen()) {
            return;
        }

        if (event.key === 'Escape') {
            setOpen(false, true);
            return;
        }

        // Keep Tab inside the side bar while it is open
        if (event.key === 'Tab') {
            var items = Array.prototype.filter.call(
                drawer.querySelectorAll('a[href], button:not([disabled])'),
                function (el) { return el.getClientRects().length > 0; }
            );

            if (items.length === 0) {
                return;
            }

            var first = items[0];
            var last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            } else if (!drawer.contains(document.activeElement)) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Turning the phone sideways, or widening the window, closes it
    function onChange(event) {
        if (!event.matches) {
            setOpen(false, false);
        }
    }

    if (phone.addEventListener) {
        phone.addEventListener('change', onChange);
    } else if (phone.addListener) {
        phone.addListener(onChange);
    }

    // Coming back with the Back button should not show a stale open menu
    window.addEventListener('pageshow', function () {
        setOpen(false, false);
    });
})();
