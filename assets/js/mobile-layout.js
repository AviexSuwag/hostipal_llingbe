(() => {
    const enhanceTables = (root) => {
        const tables = [];
        if (root instanceof HTMLTableElement) tables.push(root);
        if (root.querySelectorAll) tables.push(...root.querySelectorAll('table'));

        tables.forEach((table) => {
            if (table.dataset.mobileTableReady === 'true' || table.dataset.mobileTable === 'false') return;

            const headerRows = table.tHead?.rows;
            const headerRow = headerRows?.[headerRows.length - 1];
            if (!headerRow) return;

            const labels = Array.from(headerRow.cells, (cell) => cell.textContent.trim());
            if (!labels.length) return;

            Array.from(table.tBodies).forEach((body) => {
                Array.from(body.rows).forEach((row) => {
                    if (row.cells.length === 1 && row.cells[0].hasAttribute('colspan')) return;
                    Array.from(row.cells).forEach((cell, index) => {
                        if (!cell.hasAttribute('data-mobile-label') && labels[index]) {
                            cell.setAttribute('data-mobile-label', labels[index]);
                        }
                    });
                });
            });

            table.classList.add('mobile-card-table');
            table.dataset.mobileTableReady = 'true';
            table.closest('.overflow-x-auto')?.classList.add('mobile-card-table-wrapper');
        });
    };

    enhanceTables(document);
    new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node instanceof Element) enhanceTables(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
