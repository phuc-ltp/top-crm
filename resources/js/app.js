import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import Sortable from 'sortablejs';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.Sortable = Sortable;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ============ Toast ============ */
window.toast = function (type, message) {
    let box = $('.crm-toast-container');
    if (!box) {
        box = document.createElement('div');
        box.className = 'crm-toast-container';
        document.body.appendChild(box);
    }
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const el = document.createElement('div');
    el.className = `crm-toast ${type}`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i><div class="flex-grow-1">${escapeHtml(message)}</div>
        <button type="button" class="btn-close btn-sm" aria-label="Đóng"></button>`;
    box.appendChild(el);
    const close = () => { el.classList.add('hide'); setTimeout(() => el.remove(), 250); };
    el.querySelector('.btn-close').addEventListener('click', close);
    setTimeout(close, type === 'error' ? 7000 : 4500);
};

/* ============ AJAX helper ============ */
window.api = async function (url, { method = 'GET', body = null } = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? (body instanceof FormData ? body : JSON.stringify(body)) : null,
        credentials: 'same-origin',
    });
    let data = null;
    try { data = await res.json(); } catch (e) { /* phản hồi không phải JSON */ }
    if (!res.ok) {
        const messages = {
            401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
            403: 'Bạn không có quyền thực hiện thao tác này.',
            404: 'Không tìm thấy dữ liệu.',
            419: 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
            429: 'Bạn thao tác quá nhanh, vui lòng thử lại sau.',
        };
        const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const err = new Error(firstError || data?.message || messages[res.status] || 'Đã có lỗi xảy ra, vui lòng thử lại.');
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
};

/* ============ Sidebar ============ */
function initSidebar() {
    const body = document.body;
    try { if (localStorage.getItem('crm.sidebar') === 'collapsed' && window.innerWidth >= 992) body.classList.add('sidebar-collapsed'); } catch (e) { }
    $$('[data-toggle-sidebar]').forEach((btn) => btn.addEventListener('click', () => {
        if (window.innerWidth < 992) {
            body.classList.toggle('sidebar-open');
        } else {
            body.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('crm.sidebar', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'); } catch (e) { }
        }
    }));
    $('.crm-backdrop')?.addEventListener('click', () => body.classList.remove('sidebar-open'));
}

/* ============ Forms: validation + chống gửi trùng ============ */
function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.dataset.originalHtml = btn.innerHTML;
        btn.dataset.loading = 'true';
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${btn.dataset.loadingText || 'Đang xử lý...'}`;
    } else {
        btn.dataset.loading = 'false';
        btn.disabled = false;
        if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
    }
}
window.setLoading = setLoading;

function initForms() {
    $$('form').forEach((form) => {
        if (form.dataset.crmBound) return;
        form.dataset.crmBound = '1';
        form.addEventListener('submit', (e) => {
            if (form.classList.contains('needs-validation') && !form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
                form.querySelector(':invalid')?.focus();
                toast('warning', 'Vui lòng kiểm tra lại các trường được đánh dấu.');
                return;
            }
            if (form.dataset.confirm && !form.dataset.confirmed) {
                e.preventDefault();
                confirmDialog(form.dataset.confirm, form.dataset.confirmButton).then((ok) => {
                    if (ok) { form.dataset.confirmed = '1'; form.requestSubmit(e.submitter || undefined); }
                });
                return;
            }
            if (form.dataset.submitting === '1') { e.preventDefault(); return; }
            if (form.method.toLowerCase() !== 'get' && !form.hasAttribute('data-no-lock')) {
                form.dataset.submitting = '1';
                setLoading(e.submitter || form.querySelector('[type="submit"]'), true);
                // Cho phép tải tệp (download) mà không khóa nút vĩnh viễn
                if (form.hasAttribute('data-download')) setTimeout(() => { form.dataset.submitting = ''; setLoading(e.submitter || form.querySelector('[type="submit"]'), false); }, 2500);
            }
        });
    });
}

/* ============ Hộp thoại xác nhận ============ */
function confirmDialog(message, buttonText = 'Xác nhận') {
    return new Promise((resolve) => {
        const el = $('#confirmModal');
        if (!el) { resolve(window.confirm(message)); return; }
        $('.confirm-message', el).textContent = message;
        const okBtn = $('.confirm-ok', el);
        okBtn.textContent = buttonText || 'Xác nhận';
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        let result = false;
        const onOk = () => { result = true; modal.hide(); };
        okBtn.addEventListener('click', onOk, { once: true });
        el.addEventListener('hidden.bs.modal', () => { okBtn.removeEventListener('click', onOk); resolve(result); }, { once: true });
        modal.show();
    });
}
window.confirmDialog = confirmDialog;

/* ============ Tìm kiếm toàn cục ============ */
function initGlobalSearch() {
    const input = $('#globalSearch');
    const box = $('#globalSearchResults');
    if (!input || !box) return;
    let timer;
    let controller;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { box.classList.remove('show'); return; }
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            box.innerHTML = '<div class="p-3"><div class="skeleton mb-2"></div><div class="skeleton w-75"></div></div>';
            box.classList.add('show');
            try {
                const res = await fetch(`${input.dataset.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const data = await res.json();
                if (!data.groups?.length) { box.innerHTML = '<div class="p-3 text-muted small">Không tìm thấy kết quả phù hợp.</div>'; return; }
                box.innerHTML = data.groups.map((g) => `<div class="group-title">${escapeHtml(g.label)}</div>` +
                    g.items.map((i) => `<a href="${escapeHtml(i.url)}"><i class="bi ${escapeHtml(g.icon)} text-orange"></i><div class="min-w-0"><div class="fw-semibold text-truncate">${escapeHtml(i.title)}</div><div class="small text-muted text-truncate">${escapeHtml(i.subtitle || '')}</div></div></a>`).join('')).join('');
            } catch (e) {
                if (e.name !== 'AbortError') box.innerHTML = '<div class="p-3 text-danger small">Không thể tìm kiếm lúc này.</div>';
            }
        }, 300);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.crm-search')) box.classList.remove('show'); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') { box.classList.remove('show'); input.blur(); } });
}

/* ============ Thông báo ============ */
function initNotifications() {
    const dropdown = $('#notifDropdown');
    if (!dropdown) return;
    const list = $('#notifList');
    const badge = $('#notifBadge');
    const load = async () => {
        list.innerHTML = '<div class="p-3"><div class="skeleton mb-2"></div><div class="skeleton mb-2"></div><div class="skeleton w-50"></div></div>';
        try {
            const data = await api(dropdown.dataset.url);
            if (!data.items.length) { list.innerHTML = '<div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-bell"></i></div><div class="small">Chưa có thông báo nào</div></div>'; return; }
            list.innerHTML = data.items.map((n) => `<a class="notif-item ${n.read ? '' : 'unread'}" href="${escapeHtml(n.open_url)}">
                <span class="avatar avatar-sm tone-orange"><i class="bi ${escapeHtml(n.icon)}"></i></span>
                <span class="min-w-0"><span class="d-block small fw-semibold text-truncate-2">${escapeHtml(n.message)}</span><span class="small-xs text-muted">${escapeHtml(n.time)}</span></span></a>`).join('');
        } catch (e) { list.innerHTML = `<div class="p-3 text-danger small">${escapeHtml(e.message)}</div>`; }
    };
    dropdown.addEventListener('show.bs.dropdown', load);
    $('#notifMarkAll')?.addEventListener('click', async (e) => {
        e.preventDefault();
        try { await api(e.currentTarget.dataset.url, { method: 'POST' }); badge?.remove(); load(); } catch (err) { toast('error', err.message); }
    });
}

/* ============ Kanban pipeline ============ */
function initKanban() {
    const board = $('[data-kanban]');
    if (!board) return;
    const fmt = new Intl.NumberFormat('vi-VN');
    const refreshTotals = () => $$('.kanban-col', board).forEach((col) => {
        const cards = $$('.kanban-card', col);
        const total = cards.reduce((s, c) => s + Number(c.dataset.value || 0), 0);
        $('.col-count', col).textContent = cards.length;
        $('.col-total', col).textContent = fmt.format(total) + ' ₫';
        $('.kanban-empty', col)?.classList.toggle('d-none', cards.length > 0);
    });
    $$('.kanban-list', board).forEach((list) => {
        Sortable.create(list, {
            group: 'pipeline',
            animation: 160,
            ghostClass: 'kanban-ghost',
            chosenClass: 'kanban-chosen',
            draggable: '.kanban-card',
            disabled: board.dataset.readonly === '1',
            onEnd: async (evt) => {
                const card = evt.item;
                const stageId = evt.to.dataset.stage;
                if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                refreshTotals();
                try {
                    const res = await api(card.dataset.moveUrl, { method: 'PATCH', body: { pipeline_stage_id: Number(stageId), position: evt.newDraggableIndex ?? evt.newIndex } });
                    toast('success', res.message);
                    if (res.probability !== undefined) { const p = $('.card-prob', card); if (p) p.textContent = res.probability + '%'; }
                } catch (e) {
                    toast('error', e.message);
                    // Hoàn tác vị trí thẻ khi lỗi
                    evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                    refreshTotals();
                }
            },
        });
    });
    refreshTotals();
}

/* ============ Biểu đồ ============ */
Chart.defaults.font.family = "'Be Vietnam Pro','Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.maintainAspectRatio = false;

/* Màu biểu đồ lấy từ design token (tự đổi theo sáng/tối) */
const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
window.crmPalette = () => ({
    primary: cssVar('--crm-primary'), primarySoft: cssVar('--crm-primary-soft'),
    success: cssVar('--crm-success'), danger: cssVar('--crm-danger'), warning: cssVar('--crm-warning'),
    info: cssVar('--crm-info'), purple: cssVar('--crm-purple'), text: cssVar('--chart-text'), grid: cssVar('--chart-grid'),
    surface: cssVar('--crm-surface'), dark: cssVar('--crm-text'),
});
function applyChartTheme() {
    const p = crmPalette();
    Chart.defaults.color = p.text;
    Chart.defaults.borderColor = p.grid;
    Chart.defaults.scale.grid.color = p.grid;
    Chart.defaults.plugins.tooltip.backgroundColor = p.dark;
    Chart.defaults.plugins.tooltip.titleColor = p.surface;
    Chart.defaults.plugins.tooltip.bodyColor = p.surface;
}
applyChartTheme();

/* ============ Giao diện sáng / tối ============ */
function initThemeToggle() {
    $$('[data-theme-toggle]').forEach((btn) => btn.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('crm.theme', next); } catch (e) { }
        applyChartTheme();
        Object.values(charts).forEach((c) => { c.options.scales && Object.values(c.options.scales).forEach((s) => { if (s.grid) s.grid.color = Chart.defaults.scale.grid.color; if (s.ticks) s.ticks.color = Chart.defaults.color; }); c.update(); });
        document.dispatchEvent(new CustomEvent('crm:theme', { detail: next }));
    }));
}

/* Ctrl/Cmd + K → ô tìm kiếm toàn cục */
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        const input = document.getElementById('globalSearch');
        if (input) { e.preventDefault(); input.focus(); input.select(); }
    }
});

const moneyTick = (v) => {
    const n = Number(v);
    if (Math.abs(n) >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ';
    if (Math.abs(n) >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tr';
    return n.toLocaleString('vi-VN');
};
window.moneyTick = moneyTick;

const charts = {};
window.renderChart = function (canvas, config) {
    if (typeof canvas === 'string') canvas = document.getElementById(canvas);
    if (!canvas) return null;
    canvas.parentElement.querySelector('.skeleton')?.remove();
    charts[canvas.id]?.destroy();
    if (config.options?.money) {
        const axis = config.options.indexAxis === 'y' ? 'x' : 'y';
        config.options.scales = config.options.scales || {};
        config.options.scales[axis] = { ...(config.options.scales[axis] || {}), ticks: { callback: moneyTick } };
        config.options.plugins = config.options.plugins || {};
        config.options.plugins.tooltip = { callbacks: { label: (ctx) => `${ctx.dataset.label || ctx.label}: ${Number(ctx.raw).toLocaleString('vi-VN')} ₫` } };
        delete config.options.money;
    }
    charts[canvas.id] = new Chart(canvas, config);
    return charts[canvas.id];
};

function initDataCharts() {
    $$('canvas[data-chart]').forEach((c) => {
        try { renderChart(c, JSON.parse(c.dataset.chart)); } catch (e) { console.error('Chart error', e); }
    });
}

/* ============ Dòng sản phẩm (báo giá / đơn hàng / hóa đơn) ============ */
function initLineItems() {
    const table = $('[data-line-items]');
    if (!table) return;
    const tbody = $('tbody', table);
    const template = $('#lineItemTemplate');
    const fmt = new Intl.NumberFormat('vi-VN');
    let index = $$('tr', tbody).length;

    const recalc = () => {
        let subtotal = 0;
        $$('tr', tbody).forEach((tr) => {
            const q = Number($('[data-field="quantity"]', tr).value || 0);
            const p = Number($('[data-field="unit_price"]', tr).value || 0);
            const d = Math.min(100, Number($('[data-field="discount_percent"]', tr).value || 0));
            const line = Math.round(q * p * (1 - d / 100));
            $('[data-line-total]', tr).textContent = fmt.format(line);
            subtotal += line;
        });
        const discount = Math.min(subtotal, Number($('#discount_amount')?.value || 0));
        const tax = Math.round((subtotal - discount) * Number($('#tax_rate')?.value || 0) / 100);
        $('#sumSubtotal').textContent = fmt.format(subtotal) + ' ₫';
        $('#sumTax').textContent = fmt.format(tax) + ' ₫';
        $('#sumTotal').textContent = fmt.format(subtotal - discount + tax) + ' ₫';
    };

    const bindRow = (tr) => {
        $('[data-field="product_id"]', tr)?.addEventListener('change', (e) => {
            const opt = e.target.selectedOptions[0];
            if (opt?.dataset.price) {
                $('[data-field="unit_price"]', tr).value = opt.dataset.price;
                const desc = $('[data-field="description"]', tr);
                if (!desc.value) desc.value = opt.dataset.name;
            }
            recalc();
        });
        $('[data-remove-row]', tr)?.addEventListener('click', () => {
            if ($$('tr', tbody).length <= 1) { toast('warning', 'Chứng từ cần ít nhất một dòng.'); return; }
            tr.remove();
            recalc();
        });
    };

    $$('tr', tbody).forEach(bindRow);
    $('[data-add-row]')?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', index++);
        tbody.insertAdjacentHTML('beforeend', html);
        bindRow(tbody.lastElementChild);
        recalc();
    });
    table.closest('form').addEventListener('input', recalc);
    recalc();
}

/* ============ Khởi động ============ */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initThemeToggle();
    initForms();
    initGlobalSearch();
    initNotifications();
    initKanban();
    initDataCharts();
    initLineItems();

    $$('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Hiện / ẩn mật khẩu
    $$('[data-toggle-password]').forEach((btn) => btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
    }));

    // Thông báo flash từ server
    const flash = $('#flashData');
    if (flash) {
        try { JSON.parse(flash.textContent).forEach(([type, msg]) => toast(type, msg)); } catch (e) { }
    }

    // Tự gửi form lọc khi đổi select
    $$('form[data-auto-submit] select').forEach((s) => s.addEventListener('change', () => s.form.requestSubmit()));
});
