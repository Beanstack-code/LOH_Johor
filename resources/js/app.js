import './bootstrap';
import ApexCharts from 'apexcharts';
import Collapse from '@alpinejs/collapse';

window.ApexCharts = ApexCharts;

const tableState = new WeakMap();

const toNumber = (value) => {
    const parsed = Number.parseFloat(value.replace(/[^0-9.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : null;
};

const toDate = (value) => {
    const parsed = Date.parse(value);
    return Number.isNaN(parsed) ? null : parsed;
};

const compareValues = (a, b, direction) => {
    const numA = toNumber(a);
    const numB = toNumber(b);
    if (numA !== null && numB !== null) {
        return direction === 'asc' ? numA - numB : numB - numA;
    }

    const dateA = toDate(a);
    const dateB = toDate(b);
    if (dateA !== null && dateB !== null) {
        return direction === 'asc' ? dateA - dateB : dateB - dateA;
    }

    const stringA = a.toLowerCase();
    const stringB = b.toLowerCase();
    if (stringA === stringB) {
        return 0;
    }

    return direction === 'asc'
        ? stringA.localeCompare(stringB, undefined, { numeric: true, sensitivity: 'base' })
        : stringB.localeCompare(stringA, undefined, { numeric: true, sensitivity: 'base' });
};

const applyTableSort = (table, columnIndex, direction) => {
    const tbody = table.tBodies[0];
    if (!tbody) {
        return;
    }

    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((rowA, rowB) => {
        const valueA = rowA.cells[columnIndex]?.innerText.trim() ?? '';
        const valueB = rowB.cells[columnIndex]?.innerText.trim() ?? '';
        return compareValues(valueA, valueB, direction);
    });

    rows.forEach((row) => tbody.appendChild(row));
};

const syncIndicators = (table, activeIndex, direction) => {
    const headers = Array.from(table.querySelectorAll('thead th'));
    headers.forEach((th, index) => {
        const indicator = th.querySelector('[data-sort-indicator]');
        if (!indicator) {
            return;
        }

        if (index !== activeIndex) {
            indicator.textContent = '';
            return;
        }

        indicator.textContent = direction === 'asc' ? '↑' : '↓';
    });
};

const setupTableSort = (table) => {
    if (table.dataset.disableClientSort === '1') {
        return;
    }

    if (table.dataset.pkSortReady === '1') {
        return;
    }

    const headers = Array.from(table.querySelectorAll('thead th'));
    headers.forEach((th, columnIndex) => {
        const label = th.innerText.trim().toLowerCase();
        if (label === '' || label === 'action' || label === 'actions' || th.classList.contains('text-right')) {
            return;
        }

        th.classList.add('cursor-pointer', 'select-none');
        th.insertAdjacentHTML('beforeend', '<span data-sort-indicator class="ml-1 text-xs"></span>');
        th.addEventListener('click', () => {
            const state = tableState.get(table) ?? { index: -1, direction: 'asc' };
            const direction = state.index === columnIndex && state.direction === 'asc' ? 'desc' : 'asc';

            tableState.set(table, { index: columnIndex, direction });
            applyTableSort(table, columnIndex, direction);
            syncIndicators(table, columnIndex, direction);
        });
    });

    table.dataset.pkSortReady = '1';
};

const initTableSorting = (root = document) => {
    root.querySelectorAll('.pk-table').forEach(setupTableSort);
};

const showToast = (message, type = 'success') => {
    const container = document.getElementById('pk-toast-container');
    if (!container || !message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `pk-toast ${type === 'error' ? 'pk-toast-error' : 'pk-toast-success'}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('pk-toast-visible'));
    setTimeout(() => {
        toast.classList.remove('pk-toast-visible');
        setTimeout(() => toast.remove(), 220);
    }, 2600);
};

window.pkToast = showToast;
window.addEventListener('pk-toast', (event) => {
    showToast(event.detail?.message ?? '', event.detail?.type ?? 'success');
});

document.addEventListener('livewire:init', () => {
    if (window.Alpine) {
        window.Alpine.plugin(Collapse);
    }

    if (!window.Livewire || typeof window.Livewire.on !== 'function') {
        return;
    }

    window.Livewire.on('pk-toast', (payload = {}) => {
        showToast(payload.message ?? '', payload.type ?? 'success');
    });
});

document.addEventListener('DOMContentLoaded', () => {
    initTableSorting(document);
});

const mutationObserver = new MutationObserver(() => {
    initTableSorting(document);
});

mutationObserver.observe(document.body, { childList: true, subtree: true });
