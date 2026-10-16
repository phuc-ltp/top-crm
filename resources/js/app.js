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
/* Dịch câu giao diện: bản dịch do server chèn vào window.CRM_I18N (khóa = câu tiếng Việt) */
const t = (s) => (window.CRM_I18N && window.CRM_I18N[s]) || s;
window.t = t;

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
        <button type="button" class="btn-close btn-sm" aria-label="${t('Đóng')}"></button>`;
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
            401: t('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'),
            403: t('Bạn không có quyền thực hiện thao tác này.'),
            404: t('Không tìm thấy dữ liệu.'),
            419: t('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'),
            429: t('Bạn thao tác quá nhanh, vui lòng thử lại sau.'),
        };
        const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const err = new Error(firstError || data?.message || messages[res.status] || t('Đã có lỗi xảy ra, vui lòng thử lại.'));
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
    // Nút xanh lá kiểu macOS: bật / tắt toàn màn hình
    $$('[data-fullscreen]').forEach((btn) => btn.addEventListener('click', () => {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen?.().catch(() => { });
        else document.exitFullscreen?.();
    }));
}

/* ============ Forms: validation + chống gửi trùng ============ */
function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.dataset.originalHtml = btn.innerHTML;
        btn.dataset.loading = 'true';
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${btn.dataset.loadingText || t('Đang xử lý...')}`;
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
                toast('warning', t('Vui lòng kiểm tra lại các trường được đánh dấu.'));
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
function confirmDialog(message, buttonText) {
    return new Promise((resolve) => {
        const el = $('#confirmModal');
        if (!el) { resolve(window.confirm(message)); return; }
        $('.confirm-message', el).textContent = message;
        const okBtn = $('.confirm-ok', el);
        okBtn.textContent = buttonText || t('Xác nhận');
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
    let active = -1;
    const open = (html) => { box.innerHTML = html; box.classList.add('show'); input.setAttribute('aria-expanded', 'true'); active = -1; };
    const close = () => { box.classList.remove('show'); input.setAttribute('aria-expanded', 'false'); active = -1; };
    const links = () => $$('a[data-option]', box);
    const highlight = (i) => {
        const all = links();
        if (!all.length) return;
        active = (i + all.length) % all.length;
        all.forEach((a, k) => { a.classList.toggle('active', k === active); a.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
        all[active].scrollIntoView({ block: 'nearest' });
    };
    const resultsUrl = (q) => `${input.dataset.resultsUrl}?q=${encodeURIComponent(q)}`;
    const option = (url, icon, title, subtitle) => `<a href="${escapeHtml(url)}" data-option role="option"><i class="bi ${escapeHtml(icon)} text-orange"></i><div class="min-w-0"><div class="fw-semibold text-truncate">${escapeHtml(title)}</div>${subtitle ? `<div class="small text-muted text-truncate">${escapeHtml(subtitle)}</div>` : ''}</div></a>`;

    // Ô trống: hiện tìm gần đây
    const showRecent = async () => {
        try {
            const data = await api(input.dataset.url);
            if (!data.recent?.length || input.value.trim() !== '') return;
            open(`<div class="group-title d-flex justify-content-between"><span>${t('Tìm gần đây')}</span><button type="button" class="btn btn-link btn-sm p-0" data-clear-recent>${t('Xóa')}</button></div>` +
                data.recent.map((q) => option(resultsUrl(q), 'bi-clock-history', q, '')).join(''));
            $('[data-clear-recent]', box)?.addEventListener('click', async (e) => {
                e.preventDefault();
                try { await api(input.dataset.clearUrl, { method: 'DELETE' }); close(); } catch (err) { toast('error', err.message); }
            });
        } catch (e) { /* bỏ qua */ }
    };

    input.addEventListener('focus', () => { if (input.value.trim() === '') showRecent(); });
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q === '') { showRecent(); return; }
        if (q.length < 2) { close(); return; }
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            open('<div class="p-3"><div class="skeleton mb-2"></div><div class="skeleton w-75"></div></div>');
            try {
                const res = await fetch(`${input.dataset.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'same-origin' });
                const data = await res.json();
                const all = `<a href="${escapeHtml(resultsUrl(q))}" data-option role="option" class="border-top"><i class="bi bi-search text-orange"></i><div class="fw-semibold">${escapeHtml(t('Xem tất cả kết quả cho “:q”').replace(':q', q))}</div></a>`;
                if (!data.groups?.length) { open(`<div class="p-3 text-muted small">${t('Không tìm thấy kết quả phù hợp.')}</div>`); return; }
                open(data.groups.map((g) => `<div class="group-title">${escapeHtml(g.label)}</div>` + g.items.map((i) => option(i.url, g.icon, i.title, i.subtitle)).join('')).join('') + all);
            } catch (e) {
                if (e.name !== 'AbortError') open(`<div class="p-3 text-danger small">${t('Không thể tìm kiếm lúc này.')}</div>`);
            }
        }, 250);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.crm-search')) close(); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { close(); input.blur(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            const chosen = links()[active];
            const q = input.value.trim();
            if (chosen) window.location.href = chosen.href;
            else if (q.length >= 2) window.location.href = resultsUrl(q);
        }
    });
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
            if (!data.items.length) { list.innerHTML = '<div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-bell"></i></div><div class="small">' + t('Chưa có thông báo nào') + '</div></div>'; return; }
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
                const revert = () => { evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null); refreshTotals(); };
                const body = { pipeline_stage_id: Number(stageId), position: evt.newDraggableIndex ?? evt.newIndex };
                // Vào cột thất bại (từ cột khác): hỏi lý do trước khi lưu; hủy → trả thẻ về chỗ cũ
                if (evt.to.dataset.lost === '1' && evt.from !== evt.to) {
                    const reason = await askLostReason();
                    if (!reason) { revert(); return; }
                    body.lost_reason_id = Number(reason.id);
                    body.lost_reason = reason.note;
                }
                refreshTotals();
                try {
                    const res = await api(card.dataset.moveUrl, { method: 'PATCH', body });
                    toast('success', res.message);
                    if (res.probability !== undefined) { const p = $('.card-prob', card); if (p) p.textContent = res.probability + '%'; }
                } catch (e) {
                    toast('error', e.message);
                    revert();   // Hoàn tác vị trí thẻ khi lỗi
                }
            },
        });
    });
    refreshTotals();
}

/* Hộp chọn lý do thất bại cho Kanban: trả về {id, note} hoặc null nếu hủy */
function askLostReason() {
    return new Promise((resolve) => {
        const el = document.getElementById('lostReasonModal');
        if (!el) { resolve(null); return; }
        const select = $('#lostReasonSelect', el), note = $('#lostReasonNote', el), err = $('#lostReasonError', el), ok = $('#lostReasonOk', el);
        select.value = ''; note.value = ''; err.classList.add('d-none');
        let result = null;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        const onOk = () => {
            if (!select.value) { err.classList.remove('d-none'); select.focus(); return; }
            result = { id: select.value, note: note.value.trim() };
            modal.hide();
        };
        ok.addEventListener('click', onOk);
        el.addEventListener('hidden.bs.modal', () => { ok.removeEventListener('click', onOk); resolve(result); }, { once: true });
        modal.show();
    });
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
    surface: cssVar('--crm-surface-solid') || cssVar('--crm-surface'), dark: cssVar('--crm-text'),
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
        // Lưu vào tài khoản để mọi thiết bị dùng chung lựa chọn (lỗi mạng không ảnh hưởng giao diện hiện tại)
        const url = document.querySelector('meta[name="crm-theme-url"]')?.content;
        if (url) {
            document.documentElement.setAttribute('data-user-theme', next);
            fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ theme: next }),
            }).catch(() => { });
        }
        applyChartTheme();
        Object.values(charts).forEach((c) => { c.options.scales && Object.values(c.options.scales).forEach((s) => { if (s.grid) s.grid.color = Chart.defaults.scale.grid.color; if (s.ticks) s.ticks.color = Chart.defaults.color; }); c.update(); });
        document.dispatchEvent(new CustomEvent('crm:theme', { detail: next }));
    }));
}

/* ============ Tìm chức năng trong menu ============ */
const fold = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();
function initNavSearch() {
    const input = $('[data-nav-search]');
    const nav = $('.crm-nav');
    if (!input || !nav) return;
    const empty = $('[data-nav-empty]', nav);
    const groups = $$('.nav-group-toggle', nav).map((btn) => ({ btn, box: document.getElementById(btn.getAttribute('aria-controls')) }));
    const topLinks = $$(':scope > .nav-link', nav);
    const sections = $$(':scope > .nav-section', nav);
    const initiallyOpen = new Map(groups.map((g) => [g, g.box?.classList.contains('show')]));

    const apply = () => {
        const q = fold(input.value);
        let visible = 0;
        topLinks.forEach((a) => { const ok = !q || fold(a.textContent).includes(q); a.classList.toggle('d-none', !ok); if (ok) visible++; });
        groups.forEach((g) => {
            if (!g.box) return;
            const groupHit = q && fold(g.btn.textContent).includes(q);
            let childHits = 0;
            $$('.nav-link', g.box).forEach((a) => { const ok = !q || groupHit || fold(a.textContent).includes(q); a.classList.toggle('d-none', !ok); if (ok) childHits++; });
            const show = !q || childHits > 0;
            g.btn.classList.toggle('d-none', !show);
            g.box.classList.toggle('d-none', !show);
            if (show) visible++;
            // Đang tìm: mở nhóm có kết quả; xóa tìm kiếm: trả nhóm về trạng thái ban đầu
            const open = q ? childHits > 0 : initiallyOpen.get(g);
            g.box.classList.toggle('show', !!open);
            g.btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        // Ẩn tiêu đề nhóm khi không còn mục nào hiển thị phía dưới
        sections.forEach((sec) => {
            let el = sec.nextElementSibling, any = false;
            while (el && !el.classList.contains('nav-section')) {
                if ((el.classList.contains('nav-link') || el.classList.contains('nav-group-toggle')) && !el.classList.contains('d-none')) any = true;
                el = el.nextElementSibling;
            }
            sec.classList.toggle('d-none', !any);
        });
        empty?.classList.toggle('d-none', visible > 0);
    };
    input.addEventListener('input', apply);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { input.value = ''; apply(); input.blur(); }
        if (e.key === 'Enter') {
            const first = $$('.nav-link', nav).find((a) => !a.classList.contains('d-none') && a.offsetParent !== null);
            if (first) { e.preventDefault(); window.location.href = first.href; }
        }
    });
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
    if (Math.abs(n) >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' ' + t('tỷ');
    if (Math.abs(n) >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' ' + t('tr');
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
            if ($$('tr', tbody).length <= 1) { toast('warning', t('Chứng từ cần ít nhất một dòng.')); return; }
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
    initNavSearch();
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
