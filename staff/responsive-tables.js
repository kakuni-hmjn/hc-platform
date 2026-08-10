(() => {
    'use strict';

    const prepareTable = (table) => {
        if (!(table instanceof HTMLTableElement)) return;
        const labels = Array.from(table.querySelectorAll('thead th')).map((header) => header.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (!(cell instanceof HTMLTableCellElement)) return;
                cell.dataset.label = labels[index] || '';
            });
        });
        table.dataset.responsiveReady = 'true';
    };

    const prepareTree = (root) => {
        if (root instanceof HTMLTableElement && root.matches('.ops-table, .hpmc-items-table')) prepareTable(root);
        if (root instanceof Element || root instanceof Document || root instanceof DocumentFragment) {
            root.querySelectorAll('.ops-table, .hpmc-items-table').forEach(prepareTable);
        }
    };

    prepareTree(document);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target instanceof Element) {
                const table = mutation.target.closest('.ops-table, .hpmc-items-table');
                if (table) prepareTable(table);
            }
            mutation.addedNodes.forEach((node) => prepareTree(node));
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
