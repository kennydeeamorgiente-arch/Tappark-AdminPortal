(function() {
    if (window.TapParkControls) return;

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const shortDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    function parseIsoDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) return null;
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function toIsoDate(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function toDisplayDate(value) {
        const date = parseIsoDate(value);
        if (!date) return 'mm/dd/yyyy';
        return `${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}/${date.getFullYear()}`;
    }

    function dispatchNativeChange(control) {
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function closeControls(except) {
        document.querySelectorAll('.tappark-select.open, .tappark-date.open').forEach(control => {
            if (control === except) return;
            control.classList.remove('open');
            control.querySelector('[aria-expanded]')?.setAttribute('aria-expanded', 'false');
        });
    }

    function getSelectedOption(select) {
        return select.options[select.selectedIndex] || select.options[0] || null;
    }

    function syncSelect(select) {
        const wrapper = select.nextElementSibling;
        if (!wrapper || !wrapper.classList.contains('tappark-select')) return;

        const label = wrapper.querySelector('.tappark-select-label');
        const toggle = wrapper.querySelector('.tappark-select-toggle');
        const menu = wrapper.querySelector('.tappark-select-menu');
        const selected = getSelectedOption(select);

        if (label) label.textContent = selected ? selected.textContent.trim() : '';
        if (toggle) {
            toggle.disabled = select.disabled;
            toggle.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        }
        if (!menu) return;

        menu.innerHTML = '';
        Array.from(select.options).forEach(option => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'tappark-select-option';
            item.dataset.value = option.value;
            item.textContent = option.textContent.trim();
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.disabled = option.disabled;
            if (option.selected) item.classList.add('active');
            item.addEventListener('click', () => {
                if (option.disabled) return;
                select.value = option.value;
                syncSelect(select);
                closeControls();
                dispatchNativeChange(select);
            });
            menu.appendChild(item);
        });
    }

    function enhanceSelect(select) {
        if (!(select instanceof HTMLSelectElement)) return;
        if (select.multiple || select.dataset.tapparkControl === 'native' || select.dataset.tapparkEnhanced === 'select') return;

        select.dataset.tapparkEnhanced = 'select';
        select.classList.add('tappark-control-original');

        const wrapper = document.createElement('div');
        wrapper.className = 'tappark-select';
        if (select.classList.contains('form-select-sm')) wrapper.classList.add('tappark-select-sm');
        wrapper.style.cssText = select.getAttribute('style') || '';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'tappark-select-toggle';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span class="tappark-select-label"></span><i class="fas fa-chevron-down"></i>';

        const menu = document.createElement('div');
        menu.className = 'tappark-select-menu';
        menu.setAttribute('role', 'listbox');

        wrapper.append(toggle, menu);
        select.insertAdjacentElement('afterend', wrapper);

        toggle.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            if (select.disabled) return;
            const willOpen = !wrapper.classList.contains('open');
            closeControls(wrapper);
            wrapper.classList.toggle('open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        select.addEventListener('change', () => syncSelect(select));
        syncSelect(select);
    }

    function renderDatePicker(input) {
        const wrapper = input.nextElementSibling;
        if (!wrapper || !wrapper.classList.contains('tappark-date')) return;

        const selected = parseIsoDate(input.value);
        let viewDate = wrapper._tapparkViewDate;
        if (!(viewDate instanceof Date)) {
            viewDate = selected ? new Date(selected) : new Date();
        }

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const previousMonthDays = new Date(year, month, 0).getDate();
        const todayIso = toIsoDate(new Date());
        const selectedIso = selected ? toIsoDate(selected) : '';
        const cells = [];

        for (let i = firstDay - 1; i >= 0; i--) {
            cells.push({ day: previousMonthDays - i, muted: true, date: new Date(year, month - 1, previousMonthDays - i) });
        }
        for (let day = 1; day <= daysInMonth; day++) {
            cells.push({ day, muted: false, date: new Date(year, month, day) });
        }
        while (cells.length % 7 !== 0) {
            const nextDay = cells.length - firstDay - daysInMonth + 1;
            cells.push({ day: nextDay, muted: true, date: new Date(year, month + 1, nextDay) });
        }

        wrapper.querySelector('.tappark-date-label').textContent = toDisplayDate(input.value);
        wrapper.querySelector('.tappark-date-toggle').disabled = input.disabled;
        wrapper.querySelector('.tappark-date-menu').innerHTML = `
            <div class="tappark-date-header">
                <button type="button" class="tappark-date-nav" data-date-nav="-1" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <div class="tappark-date-title">${monthNames[month]} ${year}</div>
                <button type="button" class="tappark-date-nav" data-date-nav="1" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="tappark-date-grid">
                ${shortDays.map(day => `<span class="tappark-date-weekday">${day}</span>`).join('')}
                ${cells.map(cell => {
                    const iso = toIsoDate(cell.date);
                    const classes = ['tappark-date-day', cell.muted ? 'muted' : '', iso === selectedIso ? 'selected' : '', iso === todayIso ? 'today' : ''].filter(Boolean).join(' ');
                    return `<button type="button" class="${classes}" data-date="${iso}">${cell.day}</button>`;
                }).join('')}
            </div>
            <div class="tappark-date-footer">
                <button type="button" class="tappark-date-action" data-date-clear>Clear</button>
                <button type="button" class="tappark-date-action" data-date-today>Today</button>
            </div>
        `;
        wrapper._tapparkViewDate = viewDate;
    }

    function enhanceDate(input) {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.type !== 'date' || input.dataset.tapparkControl === 'native' || input.dataset.tapparkEnhanced === 'date') return;

        input.dataset.tapparkEnhanced = 'date';
        input.classList.add('tappark-control-original');

        const wrapper = document.createElement('div');
        wrapper.className = 'tappark-date';
        wrapper.style.cssText = input.getAttribute('style') || '';
        wrapper.innerHTML = `
            <button type="button" class="tappark-date-toggle" aria-haspopup="dialog" aria-expanded="false">
                <span class="tappark-date-label">mm/dd/yyyy</span>
                <i class="fas fa-calendar-alt"></i>
            </button>
            <div class="tappark-date-menu" role="dialog" aria-label="Calendar"></div>
        `;
        input.insertAdjacentElement('afterend', wrapper);

        wrapper.querySelector('.tappark-date-toggle').addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            if (input.disabled) return;
            const willOpen = !wrapper.classList.contains('open');
            closeControls(wrapper);
            if (willOpen) {
                renderDatePicker(input);
                wrapper.classList.add('open');
                event.currentTarget.setAttribute('aria-expanded', 'true');
            }
        });

        wrapper.addEventListener('click', event => {
            event.stopPropagation();
            const nav = event.target.closest('[data-date-nav]');
            const day = event.target.closest('.tappark-date-day');
            const clear = event.target.closest('[data-date-clear]');
            const today = event.target.closest('[data-date-today]');

            if (nav) {
                const viewDate = wrapper._tapparkViewDate instanceof Date ? wrapper._tapparkViewDate : new Date();
                wrapper._tapparkViewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + Number(nav.dataset.dateNav), 1);
                renderDatePicker(input);
                return;
            }
            if (day) {
                input.value = day.dataset.date;
                wrapper._tapparkViewDate = parseIsoDate(input.value);
                renderDatePicker(input);
                closeControls();
                dispatchNativeChange(input);
                return;
            }
            if (clear) {
                input.value = '';
                renderDatePicker(input);
                closeControls();
                dispatchNativeChange(input);
                return;
            }
            if (today) {
                input.value = toIsoDate(new Date());
                wrapper._tapparkViewDate = new Date();
                renderDatePicker(input);
                closeControls();
                dispatchNativeChange(input);
            }
        });

        input.addEventListener('change', () => renderDatePicker(input));
        renderDatePicker(input);
    }

    function refresh(root) {
        const scope = root instanceof Element ? root : document;
        scope.querySelectorAll('select').forEach(enhanceSelect);
        scope.querySelectorAll('input[type="date"]').forEach(enhanceDate);
        scope.querySelectorAll('select[data-tappark-enhanced="select"]').forEach(syncSelect);
        scope.querySelectorAll('input[data-tappark-enhanced="date"]').forEach(renderDatePicker);
    }

    document.addEventListener('click', event => {
        if (!event.target.closest('.tappark-select, .tappark-date')) closeControls();
    });

    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(node => {
                if (node instanceof Element) refresh(node);
            });
            if (mutation.type === 'childList' && mutation.target instanceof HTMLSelectElement) {
                syncSelect(mutation.target);
            }
        }
    });

    window.TapParkControls = { refresh, close: closeControls };

    if (window.jQuery && !window.jQuery.fn._tapparkOriginalVal) {
        const originalVal = window.jQuery.fn.val;
        window.jQuery.fn._tapparkOriginalVal = originalVal;
        window.jQuery.fn.val = function() {
            const result = originalVal.apply(this, arguments);
            if (arguments.length) {
                this.each(function() {
                    if (this.dataset?.tapparkEnhanced === 'select') syncSelect(this);
                    if (this.dataset?.tapparkEnhanced === 'date') renderDatePicker(this);
                });
            }
            return result;
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            refresh(document);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    } else {
        refresh(document);
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
