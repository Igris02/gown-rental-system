document.addEventListener('DOMContentLoaded', function () {
    // Show / hide password buttons
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.togglePassword);
            var show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
        });
    });

    var form = document.querySelector('form[data-auth-form]');
    if (!form) {
        return;
    }

    var submitButton = form.querySelector('button[type="submit"]');
    var originalLabel = submitButton.textContent;
    var password = form.querySelector('#password');
    var confirmation = form.querySelector('#password_confirmation');
    var matchError = form.querySelector('[data-match-error]');

    // Sign up only: check that both passwords match
    function passwordsMatch() {
        if (!confirmation) {
            return true;
        }
        return password.value === confirmation.value;
    }

    function updateMatchMessage() {
        if (!confirmation) {
            return;
        }
        var mismatch = confirmation.value !== '' && !passwordsMatch();
        matchError.hidden = !mismatch;
        confirmation.classList.toggle('is-invalid', mismatch);
    }

    if (confirmation) {
        confirmation.addEventListener('input', updateMatchMessage);
        password.addEventListener('input', updateMatchMessage);
    }

    form.addEventListener('submit', function (event) {
        if (!passwordsMatch()) {
            event.preventDefault();
            matchError.hidden = false;
            confirmation.classList.add('is-invalid');
            confirmation.focus();
            return;
        }

        // Stop double submits
        submitButton.disabled = true;
        submitButton.textContent = 'Please wait...';
    });

    // Restore the button if the user comes back with the browser's back button
    window.addEventListener('pageshow', function () {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
    });
});
