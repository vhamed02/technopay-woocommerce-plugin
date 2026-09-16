(function () {
    var GAP = 4;
    var EDGE = 8;

    function TpfwDropdown(select, settings) {
        settings = settings || {};

        this.select = select;
        this.hasPlaceholder = !!settings.placeholder;
        this.isOpen = false;
        this.highlighted = -1;
        this.options = [];

        this.build();

        this.anchor = settings.anchor || this.toggle;
        this.container = select.closest('label') || this.anchor;

        this.bind();
        this.sync();
    }

    TpfwDropdown.prototype.build = function () {
        var self = this;

        this.select.classList.add('tpfw-dropdown__native');

        this.toggle = document.createElement('button');
        this.toggle.type = 'button';
        this.toggle.className = 'tpfw-dropdown__toggle';
        this.toggle.setAttribute('aria-haspopup', 'listbox');
        this.toggle.setAttribute('aria-expanded', 'false');

        this.valueText = document.createElement('span');
        this.valueText.className = 'tpfw-dropdown__value';

        var chevron = document.createElement('span');
        chevron.className = 'tpfw-dropdown__chevron';
        chevron.setAttribute('aria-hidden', 'true');

        this.toggle.appendChild(this.valueText);
        this.toggle.appendChild(chevron);
        this.select.insertAdjacentElement('afterend', this.toggle);

        this.menu = document.createElement('div');
        this.menu.className = 'tpfw-dropdown__menu';
        this.menu.hidden = true;

        this.list = document.createElement('div');
        this.list.className = 'tpfw-dropdown__list';
        this.list.setAttribute('role', 'listbox');

        Array.prototype.forEach.call(this.select.options, function (option, index) {
            if (self.hasPlaceholder && index === 0) {
                return;
            }

            var item = document.createElement('div');
            item.className = 'tpfw-dropdown__option';
            item.setAttribute('role', 'option');
            item.setAttribute('data-value', option.value);
            item.textContent = option.text;

            if (option.title) {
                item.title = option.title;
            }

            self.list.appendChild(item);
            self.options.push(item);
        });

        this.menu.appendChild(this.list);
        document.body.appendChild(this.menu);
    };

    TpfwDropdown.prototype.bind = function () {
        var self = this;

        this.container.addEventListener('click', function (event) {
            event.preventDefault();
            self.isOpen ? self.close() : self.open();
        });

        this.toggle.addEventListener('keydown', function (event) {
            self.handleKey(event);
        });

        this.list.addEventListener('click', function (event) {
            var item = event.target.closest('.tpfw-dropdown__option');

            if (item) {
                self.setValue(item.getAttribute('data-value'));
                self.close();
                self.toggle.focus();
            }
        });

        this.list.addEventListener('mousemove', function (event) {
            var item = event.target.closest('.tpfw-dropdown__option');

            if (item) {
                self.highlight(self.options.indexOf(item), false);
            }
        });

        document.addEventListener('mousedown', function (event) {
            if (self.isOpen && !self.container.contains(event.target) && !self.menu.contains(event.target)) {
                self.close();
            }
        });

        window.addEventListener('resize', function () {
            self.reposition();
        });

        document.addEventListener('scroll', function (event) {
            if (event.target !== self.list) {
                self.reposition();
            }
        }, true);
    };

    TpfwDropdown.prototype.handleKey = function (event) {
        var moves = { ArrowDown: 1, ArrowUp: -1 };

        if (event.key === 'Escape') {
            if (this.isOpen) {
                event.stopPropagation();
                event.preventDefault();
                this.close();
            }
            return;
        }

        if (event.key === 'Tab') {
            this.close();
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();

            if (this.isOpen && this.highlighted >= 0) {
                this.setValue(this.options[this.highlighted].getAttribute('data-value'));
                this.close();
            } else {
                this.open();
            }
            return;
        }

        if (moves[event.key]) {
            event.preventDefault();

            if (!this.isOpen) {
                this.open();
                return;
            }

            this.highlight(this.highlighted + moves[event.key], true);
        }
    };

    TpfwDropdown.prototype.highlight = function (index, scrollIntoView) {
        if (!this.options.length) {
            return;
        }

        index = Math.max(0, Math.min(index, this.options.length - 1));

        this.options.forEach(function (item, itemIndex) {
            item.classList.toggle('is-highlighted', itemIndex === index);
        });

        this.highlighted = index;

        if (scrollIntoView) {
            this.options[index].scrollIntoView({ block: 'nearest' });
        }
    };

    TpfwDropdown.prototype.open = function () {
        if (this.isOpen) {
            return;
        }

        this.isOpen = true;
        this.menu.hidden = false;
        this.toggle.setAttribute('aria-expanded', 'true');
        this.anchor.classList.add('is-open');
        this.reposition();

        var selectedIndex = this.selectedIndex();
        this.highlight(selectedIndex >= 0 ? selectedIndex : 0, true);
        this.menu.classList.add('is-visible');
    };

    TpfwDropdown.prototype.close = function () {
        if (!this.isOpen) {
            return;
        }

        this.isOpen = false;
        this.menu.hidden = true;
        this.menu.classList.remove('is-visible');
        this.toggle.setAttribute('aria-expanded', 'false');
        this.anchor.classList.remove('is-open');
        this.highlighted = -1;
    };

    TpfwDropdown.prototype.reposition = function () {
        if (!this.isOpen) {
            return;
        }

        var rect = this.anchor.getBoundingClientRect();
        var menuHeight = this.menu.offsetHeight;
        var spaceBelow = window.innerHeight - rect.bottom - GAP - EDGE;
        var spaceAbove = rect.top - GAP - EDGE;
        var openAbove = menuHeight > spaceBelow && spaceAbove >= menuHeight;

        this.menu.style.left = rect.left + 'px';
        this.menu.style.width = rect.width + 'px';
        this.menu.style.top = (openAbove ? rect.top - GAP - menuHeight : rect.bottom + GAP) + 'px';
        this.menu.classList.toggle('is-above', openAbove);
    };

    TpfwDropdown.prototype.selectedIndex = function () {
        var value = this.select.value;

        for (var index = 0; index < this.options.length; index += 1) {
            if (this.options[index].getAttribute('data-value') === value) {
                return index;
            }
        }

        return -1;
    };

    TpfwDropdown.prototype.setValue = function (value) {
        if (this.select.value === value) {
            return;
        }

        this.select.value = value;
        this.sync();
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    TpfwDropdown.prototype.sync = function () {
        var selectedIndex = this.selectedIndex();
        var placeholder = this.hasPlaceholder && this.select.options.length ? this.select.options[0].text : '';

        this.options.forEach(function (item, index) {
            item.setAttribute('aria-selected', index === selectedIndex ? 'true' : 'false');
            item.classList.toggle('is-selected', index === selectedIndex);
        });

        this.valueText.textContent = selectedIndex >= 0 ? this.options[selectedIndex].textContent : placeholder;
        this.toggle.classList.toggle('is-placeholder', selectedIndex < 0);
    };

    window.TpfwDropdown = TpfwDropdown;
}());
