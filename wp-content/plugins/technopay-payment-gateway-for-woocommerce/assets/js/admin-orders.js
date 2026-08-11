(function () {
    var refundModal = document.querySelector('.tpfw-refund-modal:not(.tpfw-cancel-modal)');
    var cancelModal = document.querySelector('.tpfw-cancel-modal');
    var activeModal = null;
    var activeTrigger = null;
    var filtersForm = document.querySelector('.tpfw-orders-filters');
    var filterAmountInput = filtersForm ? filtersForm.querySelector('[data-filter-amount]') : null;
    var cancelForm = cancelModal ? cancelModal.querySelector('[data-cancel-refund-form]') : null;
    var cancelTrackNumberInput = cancelForm ? cancelForm.querySelector('[name="track_number"]') : null;
    var refundForm = refundModal ? refundModal.querySelector('[data-refund-form]') : null;
    var amountInput = refundForm ? refundForm.querySelector('[name="requested_amount"]') : null;
    var fullAmountButton = refundForm ? refundForm.querySelector('[data-refund-full-amount]') : null;
    var trackNumberInput = refundForm ? refundForm.querySelector('[name="track_number"]') : null;
    var reasonSelect = refundForm ? refundForm.querySelector('.tpfw-refund-modal__reason') : null;
    var reasonField = refundForm ? refundForm.querySelector('.tpfw-refund-modal__reason-field') : null;
    var customReasonField = refundForm ? refundForm.querySelector('.tpfw-refund-modal__custom-reason') : null;
    var customReasonInput = customReasonField ? customReasonField.querySelector('input') : null;

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

    function formatAmountInput(input) {
        var selectionStart = input.selectionStart === null ? input.value.length : input.selectionStart;
        var digitsBeforeCaret = getAmountDigits(input.value.slice(0, selectionStart)).length;
        var formattedAmount = formatAmount(input.value);
        var caretPosition = getAmountCaretPosition(formattedAmount, digitsBeforeCaret);

        input.value = formattedAmount;
        input.setSelectionRange(caretPosition, caretPosition);
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

    function validateRefundAmount() {
        var digits = getAmountDigits(amountInput.value);
        var amount = digits === '' ? 0 : parseInt(digits, 10);
        var maximum = parseInt(amountInput.getAttribute('data-maximum'), 10);

        if (amount < 1) {
            amountInput.setCustomValidity(tpfwAdminOrders.amountRequired);
        } else if (!isNaN(maximum) && amount > maximum) {
            amountInput.setCustomValidity(tpfwAdminOrders.amountTooHigh);
        } else {
            amountInput.setCustomValidity('');
        }

        return digits;
    }

    function validateCustomReason() {
        var isRequired = reasonSelect.value === 'other';

        customReasonInput.setCustomValidity(isRequired && customReasonInput.value.trim() === '' ? tpfwAdminOrders.reasonRequired : '');
    }

    function prepareRefundModal(trigger) {
        refundForm.reset();
        trackNumberInput.value = trigger.getAttribute('data-track-number') || '';
        amountInput.setAttribute('data-maximum', trigger.getAttribute('data-available-amount') || '');
        amountInput.setCustomValidity('');
        customReasonInput.setCustomValidity('');
        customReasonInput.required = false;
        customReasonField.hidden = true;
        reasonSelect.setAttribute('aria-expanded', 'false');
    }

    function prepareCancelModal(trigger) {
        cancelForm.reset();
        cancelTrackNumberInput.value = trigger.getAttribute('data-track-number') || '';
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
            prepareRefundModal(button);
            openModal(refundModal, button);
            return;
        }

        button = event.target.closest('[data-cancel-refund-modal]');
        if (button) {
            prepareCancelModal(button);
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

    if (filterAmountInput) {
        filterAmountInput.value = formatAmount(filterAmountInput.value);

        filterAmountInput.addEventListener('input', function () {
            formatAmountInput(filterAmountInput);
        });

        filtersForm.addEventListener('submit', function () {
            filterAmountInput.value = getAmountDigits(filterAmountInput.value);
        });
    }

    if (refundForm) {
        amountInput.addEventListener('input', function () {
            formatAmountInput(amountInput);
            validateRefundAmount();
        });

        fullAmountButton.addEventListener('click', function () {
            amountInput.value = formatAmount(amountInput.getAttribute('data-maximum') || '');
            validateRefundAmount();
            amountInput.focus();
            amountInput.setSelectionRange(amountInput.value.length, amountInput.value.length);
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
            validateCustomReason();

            if (isOther) {
                customReasonInput.focus();
            } else {
                customReasonInput.value = '';
            }
        });

        customReasonInput.addEventListener('input', validateCustomReason);

        refundForm.addEventListener('submit', function (event) {
            var amount = validateRefundAmount();

            validateCustomReason();

            if (!refundForm.checkValidity()) {
                event.preventDefault();
                refundForm.reportValidity();
                return;
            }

            amountInput.value = amount;
            refundForm.querySelector('[type="submit"]').disabled = true;
            refundForm.querySelector('[type="submit"]').textContent = tpfwAdminOrders.submitting;
        });
    }

    if (cancelForm) {
        cancelForm.addEventListener('submit', function () {
            cancelForm.querySelector('[type="submit"]').disabled = true;
            cancelForm.querySelector('[type="submit"]').textContent = tpfwAdminOrders.canceling;
        });
    }
}());
