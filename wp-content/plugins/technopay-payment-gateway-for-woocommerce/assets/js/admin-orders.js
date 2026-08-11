(function () {
    var refundModal = document.querySelector('.tpfw-refund-modal:not(.tpfw-cancel-modal)');
    var cancelModal = document.querySelector('.tpfw-cancel-modal');
    var activeModal = null;
    var activeTrigger = null;

    function getAmountDigits(value) {
        return value.replace(/[۰-۹]/g, function (digit) {
            return String(digit.charCodeAt(0) - 1776);
        }).replace(/[٠-٩]/g, function (digit) {
            return String(digit.charCodeAt(0) - 1632);
        }).replace(/\D/g, '');
    }

    function formatAmount(value) {
        return getAmountDigits(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function getAmountCaretPosition(value, digitCount) {
        var currentDigitCount = 0;
        var index;

        if (digitCount === 0) {
            return 0;
        }

        for (index = 0; index < value.length; index += 1) {
            if (/\d/.test(value.charAt(index))) {
                currentDigitCount += 1;
            }

            if (currentDigitCount === digitCount) {
                return index + 1;
            }
        }

        return value.length;
    }

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

    function openModal(modal, trigger) {
        if (!modal) {
            return;
        }

        activeModal = modal;
        activeTrigger = trigger;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tpfw-refund-modal-open');
        modal.querySelector('.tpfw-refund-modal__close').focus();
    }

    function closeModal() {
        if (!activeModal || activeModal.hidden) {
            return;
        }

        activeModal.hidden = true;
        activeModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tpfw-refund-modal-open');
        activeModal = null;

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
            openModal(refundModal, button);
            return;
        }

        button = event.target.closest('[data-cancel-refund-modal]');
        if (button) {
            openModal(cancelModal, button);
            return;
        }

        if (event.target.closest('[data-refund-modal-close]') || event.target === activeModal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (refundModal) {
        var amountInput = refundModal.querySelector('.tpfw-refund-modal__amount input');
        var reasonSelect = refundModal.querySelector('.tpfw-refund-modal__reason');
        var reasonField = refundModal.querySelector('.tpfw-refund-modal__reason-field');
        var customReasonField = refundModal.querySelector('.tpfw-refund-modal__custom-reason');
        var customReasonInput = customReasonField.querySelector('input');

        amountInput.addEventListener('input', function () {
            var selectionStart = amountInput.selectionStart === null ? amountInput.value.length : amountInput.selectionStart;
            var digitsBeforeCaret = getAmountDigits(amountInput.value.slice(0, selectionStart)).length;
            var formattedAmount = formatAmount(amountInput.value);
            var caretPosition = getAmountCaretPosition(formattedAmount, digitsBeforeCaret);

            amountInput.value = formattedAmount;
            amountInput.setSelectionRange(caretPosition, caretPosition);
        });

        reasonField.addEventListener('click', function (event) {
            if (event.target === reasonSelect) {
                return;
            }

            event.preventDefault();
            reasonSelect.focus();

            if (typeof reasonSelect.showPicker === 'function') {
                try {
                    reasonSelect.showPicker();
                } catch (error) {
                    reasonSelect.click();
                }
            } else {
                reasonSelect.click();
            }
        });

        reasonSelect.addEventListener('change', function () {
            var isOther = reasonSelect.value === 'other';

            customReasonField.hidden = !isOther;
            customReasonInput.required = isOther;
            reasonSelect.setAttribute('aria-expanded', isOther ? 'true' : 'false');

            if (isOther) {
                customReasonInput.focus();
            } else {
                customReasonInput.value = '';
            }
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.tpfw-refund-modal form'), function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            closeModal();
        });
    });
}());
