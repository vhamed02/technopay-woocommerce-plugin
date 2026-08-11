(function () {
    var modal = document.querySelector('.tpfw-refund-modal');
    var activeTrigger = null;

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }

        var input = document.createElement('textarea');
        input.value = value;
        input.setAttribute('readonly', 'readonly');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        return Promise.resolve();
    }

    function openModal(trigger) {
        if (!modal) {
            return;
        }

        activeTrigger = trigger;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tpfw-refund-modal-open');
        modal.querySelector('.tpfw-refund-modal__close').focus();
    }

    function closeModal() {
        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tpfw-refund-modal-open');

        if (activeTrigger) {
            activeTrigger.focus();
            activeTrigger = null;
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.tpfw-copy-button');

        if (button) {
            copyText(button.getAttribute('data-copy')).then(function () {
                button.classList.add('is-copied');
                button.setAttribute('title', tpfwAdminOrders.copied);
                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                    button.setAttribute('title', tpfwAdminOrders.copy);
                }, 1600);
            });
            return;
        }

        button = event.target.closest('[data-refund-modal]');
        if (button) {
            openModal(button);
            return;
        }

        if (event.target.closest('[data-refund-modal-close]') || event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (modal) {
        modal.querySelector('form').addEventListener('submit', function (event) {
            event.preventDefault();
            closeModal();
        });
    }
}());
