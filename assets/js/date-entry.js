(() => {
    const initialized = new WeakSet();

    function localIsoDate(date = new Date()) {
        return [
            String(date.getFullYear()).padStart(4, '0'),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    }

    function toIsoDate(value) {
        const normalized = value.trim();
        let year;
        let month;
        let day;

        if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
            [year, month, day] = normalized.split('-').map(Number);
        } else {
            const match = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (!match) return null;
            [, day, month, year] = match.map(Number);
        }

        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }
        return [
            String(year).padStart(4, '0'),
            String(month).padStart(2, '0'),
            String(day).padStart(2, '0'),
        ].join('-');
    }

    function initialize(input) {
        if (initialized.has(input) || input.dataset.dateEntryReady === 'true') return;
        initialized.add(input);
        input.dataset.dateEntryReady = 'true';
        input.type = 'text';
        input.placeholder = 'YYYY-MM-DD or DD/MM/YYYY';
        input.inputMode = 'numeric';
        input.autocomplete = input.autocomplete || 'bday';
        input.setAttribute('pattern', '(\\d{4}-\\d{2}-\\d{2}|\\d{1,2}/\\d{1,2}/\\d{4})');
        input.setAttribute('title', 'Enter YYYY-MM-DD or DD/MM/YYYY, or choose a date from the calendar.');

        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        input.before(wrapper);
        wrapper.append(input);

        const calendarButton = document.createElement('button');
        calendarButton.type = 'button';
        calendarButton.className = 'absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg border-l border-slate-300 text-slate-500 hover:bg-slate-50 hover:text-blue-700';
        calendarButton.setAttribute('aria-label', 'Choose date from calendar');
        calendarButton.innerHTML = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M16 3v4M8 3v4M3 10h18"/></svg>';

        const picker = document.createElement('input');
        picker.type = 'date';
        picker.tabIndex = -1;
        picker.setAttribute('aria-hidden', 'true');
        picker.setAttribute('aria-label', 'Choose date from calendar');
        picker.className = 'absolute inset-y-0 right-0 z-10 w-11 cursor-pointer opacity-0';
        if (input.dataset.dateMaxToday === 'true') {
            picker.max = localIsoDate();
        }

        wrapper.append(calendarButton, picker);

        function synchronizePicker() {
            const isoDate = toIsoDate(input.value);
            picker.value = isoDate || '';
        }

        function validate() {
            const value = input.value.trim();
            if (!value) {
                input.setCustomValidity(input.required ? 'Enter a date of birth.' : '');
                picker.value = '';
                return;
            }

            const isoDate = toIsoDate(value);
            if (!isoDate) {
                input.setCustomValidity('Enter a valid date as YYYY-MM-DD or DD/MM/YYYY.');
                picker.value = '';
                return;
            }

            const today = localIsoDate();
            if (input.dataset.dateMaxToday === 'true' && isoDate > today) {
                input.setCustomValidity('Date of birth cannot be in the future.');
                picker.value = '';
                return;
            }

            input.setCustomValidity('');
            picker.value = isoDate;
            if (input.value !== isoDate) {
                input.value = isoDate;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        input.classList.add('pr-12');
        input.addEventListener('input', () => {
            input.setCustomValidity('');
            synchronizePicker();
        });
        input.addEventListener('blur', () => {
            if (input.value.trim()) validate();
        });
        picker.addEventListener('click', () => {
            const isoDate = toIsoDate(input.value);
            picker.value = isoDate || '';
        });
        picker.addEventListener('change', () => {
            input.value = picker.value;
            input.setCustomValidity('');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        calendarButton.addEventListener('click', () => {
            if (typeof picker.showPicker === 'function') {
                picker.showPicker();
            } else {
                picker.focus();
                picker.click();
            }
        });

        input.form?.addEventListener('submit', event => {
            validate();
            if (!input.checkValidity()) {
                event.preventDefault();
                input.reportValidity();
            }
        }, true);

        if (input.value) {
            const isoDate = toIsoDate(input.value);
            if (isoDate) input.value = isoDate;
            synchronizePicker();
        }
    }

    function initializeAll(root = document) {
        if (root instanceof HTMLInputElement && root.matches('[data-date-entry]')) {
            initialize(root);
        }
        root.querySelectorAll('[data-date-entry]').forEach(initialize);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeAll(), { once: true });
    } else {
        initializeAll();
    }

    document.addEventListener('spa:page-loaded', () => initializeAll(document.querySelector('main') || document));
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(node => {
            if (node instanceof Element) initializeAll(node);
        }));
    }).observe(document.body, { childList: true, subtree: true });
})();
