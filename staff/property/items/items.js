(() => {
    'use strict';

    const search = document.getElementById(
        'itemsSearch'
    );

    const category = document.getElementById(
        'itemsCategory'
    );

    const status = document.getElementById(
        'itemsStatus'
    );

    const count = document.getElementById(
        'itemsResultCount'
    );

    const empty = document.getElementById(
        'itemsEmpty'
    );

    const tableBody = document.getElementById(
        'itemsTableBody'
    );

    const rows = Array.from(
        document.querySelectorAll(
            '[data-item-row]'
        )
    );

    function update() {
        const keywords = search.value
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(Boolean);

        let visible = 0;

        rows.forEach((row) => {
            const categoryMatches =
                !category.value
                || row.dataset.category
                    === category.value;

            const statusMatches =
                !status.value
                || row.dataset.status
                    === status.value;

            const target =
                row.dataset.search || '';

            const searchMatches =
                keywords.every(
                    (keyword) =>
                        target.includes(keyword)
                );

            const show =
                categoryMatches
                && statusMatches
                && searchMatches;

            row.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        count.textContent = `${visible}件`;
        empty.hidden = visible !== 0;
        tableBody.closest('table').hidden =
            visible === 0;
    }

    search.addEventListener('input', update);
    category.addEventListener('change', update);
    status.addEventListener('change', update);

    update();
})();
