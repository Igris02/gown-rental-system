/*
 * Availability calendar for the gown page.
 *
 * The customer picks Day 1 (pick up). Day 2 (use) and Day 3 (return) follow automatically.
 * A day can be chosen only when all 3 days are free. The server checks the same rules again
 * when the form is sent, so this script is a convenience and not the safety net.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-reserve]');

    if (!root) {
        return;
    }

    var DAY_MS = 86400000;
    var RENTAL_DAYS = 3;

    var blocked = new Set(readList(root.dataset.blocked));
    var minDate = root.dataset.min;
    var maxDate = root.dataset.max;
    var price = root.dataset.price || '';

    var startInput = root.querySelector('[data-start-input]');
    var grid = root.querySelector('[data-grid]');
    var title = root.querySelector('[data-title]');
    var prevButton = root.querySelector('[data-prev]');
    var nextButton = root.querySelector('[data-next]');
    var summary = root.querySelector('[data-summary]');
    var submit = root.querySelector('[data-submit]'); // not there for signed-out visitors
    var submitLabel = submit ? submit.textContent : '';

    var selected = '';
    var view = { y: 0, m: 0 };

    // ---- Date helpers. Dates are "YYYY-MM-DD" text, calculated in UTC so time zones never shift a day.

    function readList(text) {
        try {
            var list = JSON.parse(text || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function toTime(date) {
        var p = date.split('-');
        return Date.UTC(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    }

    function format(time) {
        return new Date(time).toISOString().slice(0, 10);
    }

    function addDays(date, days) {
        return format(toTime(date) + days * DAY_MS);
    }

    function pretty(date) {
        return new Date(toTime(date)).toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC'
        });
    }

    function monthIndex(date) {
        var p = date.split('-');
        return Number(p[0]) * 12 + Number(p[1]) - 1;
    }

    // A rental can start on this day only when it is in range and all 3 days are free
    function canStart(date) {
        if (date < minDate || date > maxDate) {
            return false;
        }

        for (var i = 0; i < RENTAL_DAYS; i++) {
            if (blocked.has(addDays(date, i))) {
                return false;
            }
        }

        return true;
    }

    // ---- Drawing the calendar

    function render() {
        var first = new Date(Date.UTC(view.y, view.m, 1));
        var daysInMonth = new Date(Date.UTC(view.y, view.m + 1, 0)).getUTCDate();

        title.textContent = first.toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });
        grid.textContent = '';

        for (var i = 0; i < first.getUTCDay(); i++) {
            var spacer = document.createElement('span');
            spacer.className = 'cal-spacer';
            grid.appendChild(spacer);
        }

        for (var d = 1; d <= daysInMonth; d++) {
            grid.appendChild(dayButton(format(Date.UTC(view.y, view.m, d)), d));
        }

        var current = view.y * 12 + view.m;
        prevButton.disabled = current <= monthIndex(minDate);
        nextButton.disabled = current >= monthIndex(maxDate);
    }

    function dayButton(date, number) {
        var button = document.createElement('button');
        var label = pretty(date);
        var step = selected ? Math.round((toTime(date) - toTime(selected)) / DAY_MS) : -1;

        button.type = 'button';
        button.className = 'cal-day';
        button.textContent = String(number);
        button.dataset.date = date;

        if (step === 0) {
            button.classList.add('is-pickup');
            button.dataset.tag = 'Pick up';
            label += ', Day 1, pick up';
        } else if (step === 1) {
            button.classList.add('is-use');
            button.dataset.tag = 'Use';
            label += ', Day 2, use';
        } else if (step === 2) {
            button.classList.add('is-return');
            button.dataset.tag = 'Return';
            label += ', Day 3, return';
        }

        if (blocked.has(date)) {
            button.classList.add('is-booked');
            button.disabled = true;
            label += ', already reserved';
        } else if (!canStart(date)) {
            button.classList.add('is-off');
            button.disabled = true;

            if (date >= minDate && date <= maxDate && step < 0) {
                button.title = 'A 3-day rental starting here would overlap another reservation';
            }
        }

        button.setAttribute('aria-label', label);

        if (step === 0) {
            button.setAttribute('aria-pressed', 'true');
        }

        return button;
    }

    function showSummary() {
        summary.textContent = '';

        if (!selected) {
            summary.textContent = 'Choose the pick-up date (Day 1) on the calendar.';

            if (submit) {
                submit.disabled = true;
            }

            return;
        }

        [
            ['Day 1 · Pick up', selected],
            ['Day 2 · Use', addDays(selected, 1)],
            ['Day 3 · Return', addDays(selected, 2)]
        ].forEach(function (row) {
            var line = document.createElement('div');
            var name = document.createElement('strong');
            var value = document.createElement('span');

            name.textContent = row[0];
            value.textContent = pretty(row[1]);
            line.appendChild(name);
            line.appendChild(value);
            summary.appendChild(line);
        });

        if (price) {
            var total = document.createElement('div');
            var totalName = document.createElement('strong');
            var totalValue = document.createElement('span');

            totalName.textContent = 'Rental price';
            totalValue.textContent = price;
            total.appendChild(totalName);
            total.appendChild(totalValue);
            summary.appendChild(total);
        }

        if (submit) {
            submit.disabled = false;
        }
    }

    function choose(date) {
        selected = date;
        startInput.value = date;
        render();
        showSummary();
    }

    // ---- Events

    grid.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-date]');

        if (button && !button.disabled) {
            choose(button.dataset.date);
        }
    });

    function moveMonth(step) {
        var index = view.y * 12 + view.m + step;

        view.y = Math.floor(index / 12);
        view.m = index % 12;
        render();
    }

    prevButton.addEventListener('click', function () { moveMonth(-1); });
    nextButton.addEventListener('click', function () { moveMonth(1); });

    root.addEventListener('submit', function (event) {
        if (!selected) {
            event.preventDefault();
            return;
        }

        // Stop double submits
        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Sending...';
        }
    });

    // Restore the button when the customer comes back with the browser's back button
    window.addEventListener('pageshow', function () {
        if (submit) {
            submit.textContent = submitLabel;
        }

        showSummary();
    });

    // ---- Start

    var preset = root.dataset.selected;

    if (preset && canStart(preset)) {
        selected = preset;
        startInput.value = preset;
    } else {
        startInput.value = '';
    }

    var startMonth = (selected || minDate).split('-');
    view.y = Number(startMonth[0]);
    view.m = Number(startMonth[1]) - 1;

    render();
    showSummary();
})();
