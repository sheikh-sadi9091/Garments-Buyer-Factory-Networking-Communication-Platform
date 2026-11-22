'use strict';

document.querySelectorAll('form[data-validate]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        const password = form.querySelector('[name="password"]');
        const confirmation = form.querySelector('[name="password_confirmation"]');
        if (password && confirmation && password.value !== confirmation.value) {
            event.preventDefault();
            confirmation.setCustomValidity('The password confirmation does not match.');
            confirmation.reportValidity();
        }
    });

    const confirmation = form.querySelector('[name="password_confirmation"]');
    if (confirmation) {
        confirmation.addEventListener('input', () => confirmation.setCustomValidity(''));
    }
});
