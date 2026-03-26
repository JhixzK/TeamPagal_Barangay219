/**
 * Shared compact pagination: Previous | page numbers | Next (Bootstrap btn-group-sm).
 */
(function (global) {
    'use strict';

    function getVisiblePages(current, total, maxButtons) {
        const max = maxButtons || 5;
        if (total <= max) {
            return Array.from({ length: total }, function (_, i) { return i + 1; });
        }
        const half = Math.floor(max / 2);
        let start = Math.max(1, current - half);
        let end = Math.min(total, start + max - 1);
        start = Math.max(1, end - max + 1);
        const out = [];
        for (let p = start; p <= end; p++) {
            out.push(p);
        }
        return out;
    }

    /**
     * @param {Object} opts
     * @param {string} opts.containerId - element id (replaced with btn-group contents)
     * @param {string} [opts.outerWrapId] - optional wrapper display:none when total===0
     * @param {number} opts.currentPage
     * @param {number} opts.total - row count (0 hides bar)
     * @param {number} opts.totalPages
     * @param {function(number):void} opts.onPage
     * @param {number} [opts.maxButtons=5]
     */
    function renderModuleBtnPagination(opts) {
        const el = document.getElementById(opts.containerId || 'pagination');
        if (!el || typeof opts.onPage !== 'function') {
            return;
        }

        const outer = opts.outerWrapId ? document.getElementById(opts.outerWrapId) : null;
        const total = Number(opts.total != null ? opts.total : 0);
        if (total === 0) {
            el.innerHTML = '';
            el.className = '';
            el.removeAttribute('role');
            if (outer) {
                outer.style.display = 'none';
            }
            return;
        }
        if (outer) {
            outer.style.display = '';
        }

        let currentPage = Math.max(1, Number(opts.currentPage) || 1);
        let totalPages = Number(opts.totalPages);
        if (!totalPages || totalPages < 1) {
            totalPages = 1;
        }
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const maxB = opts.maxButtons || 5;
        const pages = getVisiblePages(currentPage, totalPages, maxB);
        const prevDisabled = currentPage <= 1;
        const nextDisabled = currentPage >= totalPages;

        const prevClass = prevDisabled ? 'btn-outline-secondary' : 'btn-outline-primary';
        const nextClass = nextDisabled ? 'btn-outline-secondary' : 'btn-outline-primary';

        const parts = [];
        parts.push(
            '<button type="button" class="btn btn-sm ' +
                prevClass +
                '" ' +
                (prevDisabled ? 'disabled' : '') +
                ' data-mp-page="' +
                (currentPage - 1) +
                '">Previous</button>'
        );
        pages.forEach(function (p) {
            const active = p === currentPage;
            parts.push(
                '<button type="button" class="btn btn-sm ' +
                    (active ? 'btn-primary' : 'btn-outline-primary') +
                    '" data-mp-page="' +
                    p +
                    '">' +
                    p +
                    '</button>'
            );
        });
        parts.push(
            '<button type="button" class="btn btn-sm ' +
                nextClass +
                '" ' +
                (nextDisabled ? 'disabled' : '') +
                ' data-mp-page="' +
                (currentPage + 1) +
                '">Next</button>'
        );

        el.className = 'btn-group btn-group-sm module-btn-pagination-group shadow-sm';
        el.setAttribute('role', 'group');
        el.innerHTML = parts.join('');

        el.querySelectorAll('[data-mp-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) {
                    return;
                }
                const pg = parseInt(btn.getAttribute('data-mp-page'), 10);
                if (!Number.isFinite(pg) || pg < 1 || pg > totalPages) {
                    return;
                }
                opts.onPage(pg);
            });
        });
    }

    global.renderModuleBtnPagination = renderModuleBtnPagination;
})(typeof window !== 'undefined' ? window : this);
