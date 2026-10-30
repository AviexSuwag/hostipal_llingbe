(() => {
    let generatedId = 0;
    const minimumSearchableOptions = 12;
    const initializedSelects = new WeakSet();

    function initializeSelect(select) {
        if (initializedSelects.has(select)) return;
        if (select.dataset.searchableSelectReady === 'true') {
            select.previousElementSibling?.remove();
            delete select.dataset.searchableSelectReady;
        }
        if (select.multiple
            || select.size > 1
            || select.dataset.searchable === 'false'
            || select.options.length < minimumSearchableOptions) {
            return;
        }

        select.dataset.searchableSelectReady = 'true';
        initializedSelects.add(select);

        const wrapper = document.createElement('div');
        wrapper.className = 'select-search-wrapper relative w-full';

        const search = document.createElement('input');
        search.type = 'search';
        search.autocomplete = 'off';
        search.placeholder = 'Search options';
        const associatedLabels = Array.from(document.querySelectorAll('label[for]'))
            .filter(label => label.htmlFor === select.id)
            .map(label => label.textContent.trim())
            .filter(Boolean);
        search.setAttribute(
            'aria-label',
            associatedLabels.length ? associatedLabels.join(' ') : 'Search dropdown options'
        );
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-autocomplete', 'list');
        search.setAttribute('aria-expanded', 'false');
        search.className = 'select-search-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500';
        search.id = select.id ? `${select.id}-search` : `select-search-${++generatedId}`;

        const list = document.createElement('div');
        list.className = 'select-search-options absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
        list.id = `${search.id}-options`;
        list.setAttribute('role', 'listbox');
        search.setAttribute('aria-controls', list.id);

        const noMatches = document.createElement('p');
        noMatches.className = 'select-search-empty hidden px-3 py-2 text-sm text-slate-500';
        noMatches.textContent = 'No matching options.';

        wrapper.append(search, list, noMatches);
        select.before(wrapper);
        select.classList.add('sr-only');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        function closeList() {
            list.classList.add('hidden');
            search.setAttribute('aria-expanded', 'false');
        }

        function openList() {
            if (select.disabled) return;
            list.classList.remove('hidden');
            search.setAttribute('aria-expanded', 'true');
            renderOptions();
        }

        function renderOptions() {
            const query = search.value.trim().toLocaleLowerCase();
            const options = Array.from(select.options).filter(option => {
                if (!option.hasAttribute('data-select-search-initially-hidden')) {
                    option.dataset.selectSearchInitiallyHidden = option.hidden ? 'true' : 'false';
                }
                return option.dataset.selectSearchInitiallyHidden !== 'true'
                    && option.textContent.trim().toLocaleLowerCase().includes(query);
            });

            list.replaceChildren();
            options.forEach(option => {
                const item = document.createElement('button');
                item.type = 'button';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                item.disabled = option.disabled;
                item.className = 'block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 aria-selected:bg-blue-50';
                item.textContent = option.textContent.trim() || '—';
                item.addEventListener('click', () => {
                    select.value = option.value;
                    search.value = option.textContent.trim();
                    search.setCustomValidity('');
                    closeList();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                list.append(item);
            });

            noMatches.classList.toggle('hidden', options.length > 0);
        }

        function activateSearch() {
            const selectedOption = select.selectedOptions[0];
            if (selectedOption && search.value === selectedOption.textContent.trim()) {
                search.value = '';
            }
            openList();
        }

        search.addEventListener('focus', activateSearch);
        search.addEventListener('click', activateSearch);
        search.addEventListener('input', () => {
            openList();
            renderOptions();
        });
        search.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeList();
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openList();
                list.querySelector('button:not(:disabled)')?.focus();
                return;
            }
            if (event.key === 'Enter' && !list.classList.contains('hidden')) {
                const firstOption = list.querySelector('button:not(:disabled)');
                if (firstOption) {
                    event.preventDefault();
                    firstOption.click();
                }
            }
        });
        list.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeList();
                search.focus();
            }
        });
        document.addEventListener('click', event => {
            if (!wrapper.contains(event.target)) closeList();
        });
        select.addEventListener('change', () => {
            const selectedOption = select.selectedOptions[0];
            search.value = selectedOption?.textContent.trim() ?? '';
            search.setCustomValidity('');
            renderOptions();
        });
        select.addEventListener('invalid', event => {
            event.preventDefault();
            search.setCustomValidity('Select an option from the dropdown.');
            activateSearch();
            search.reportValidity();
        });
        select.form?.addEventListener('reset', () => {
            window.setTimeout(() => {
                const selectedOption = select.selectedOptions[0];
                search.value = selectedOption?.textContent.trim() ?? '';
                renderOptions();
                closeList();
            }, 0);
        });

        const optionsObserver = new MutationObserver(() => {
            if (!list.classList.contains('hidden')) renderOptions();
            const selectedOption = select.selectedOptions[0];
            if (selectedOption && list.classList.contains('hidden')) {
                search.value = selectedOption.textContent.trim();
            }
        });
        optionsObserver.observe(select, { childList: true, subtree: true });

        const selectedOption = select.selectedOptions[0];
        search.value = selectedOption?.textContent.trim() ?? '';
    }

    function initializeAll(root = document) {
        if (root instanceof HTMLSelectElement) initializeSelect(root);
        root.querySelectorAll('select').forEach(initializeSelect);
    }

    document.addEventListener('spa:page-loaded', event => {
        const main = document.querySelector('main');
        initializeAll(main || document);
        if (event.detail?.signal) {
            event.detail.signal.addEventListener('abort', () => {
                document.querySelectorAll('.select-search-wrapper').forEach(wrapper => {
                    if (!wrapper.isConnected) wrapper.remove();
                });
            }, { once: true });
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeAll(), { once: true });
    } else {
        initializeAll();
    }

    const addedSelectObserver = new MutationObserver(records => {
        records.forEach(record => {
            record.addedNodes.forEach(node => {
                if (!(node instanceof Element)) return;
                initializeAll(node);
                const containingSelect = node.closest('select');
                if (containingSelect) initializeSelect(containingSelect);
            });
        });
    });
    addedSelectObserver.observe(document.body, { childList: true, subtree: true });
})();
