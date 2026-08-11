(function () {
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

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.tpfw-copy-button');
        if (!button) {
            return;
        }

        copyText(button.getAttribute('data-copy')).then(function () {
            button.classList.add('is-copied');
            button.setAttribute('title', tpfwAdminOrders.copied);
            window.setTimeout(function () {
                button.classList.remove('is-copied');
                button.setAttribute('title', tpfwAdminOrders.copy);
            }, 1600);
        });
    });
}());
