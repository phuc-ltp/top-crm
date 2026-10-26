/**
 * Chuyển động toàn hệ thống: gợn sóng khi bấm nút, thanh tiến độ chạy từ 0, số đếm tăng dần ([data-countup]).
 * Tôn trọng "Giảm chuyển động" của hệ điều hành.
 */
const reduce = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Gợn sóng tại vị trí bấm trên mọi nút. */
document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest('.btn');
    if (!btn || btn.disabled || reduce()) return;
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const dot = document.createElement('span');
    dot.className = 'ripple';
    dot.style.width = dot.style.height = `${size}px`;
    dot.style.left = `${e.clientX - rect.left - size / 2}px`;
    dot.style.top = `${e.clientY - rect.top - size / 2}px`;
    btn.appendChild(dot);
    dot.addEventListener('animationend', () => dot.remove());
});

/** Modal luôn nằm trực tiếp trong <body>: tránh bị kẹt dưới lớp nền mờ khi tổ tiên có transform / animation. */
document.addEventListener('show.bs.modal', (e) => {
    // Bỏ qua modal nằm trong một <form> bao ngoài: chuyển đi sẽ tách các ô nhập khỏi form đó
    if (e.target.parentElement !== document.body && !e.target.parentElement.closest('form')) document.body.appendChild(e.target);
});

/** Thanh tiến độ: hiện từ 0 rồi chạy tới giá trị thật. */
function animateProgress(root = document) {
    if (reduce()) return;
    root.querySelectorAll('.progress-bar').forEach((bar) => {
        const target = bar.style.width;
        if (!target || bar.dataset.animated) return;
        bar.dataset.animated = '1';
        bar.style.width = '0';
        requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = target; }));
    });
}

/** Đếm số tăng dần: <span data-countup="41085000" data-format="vnd">. */
export function countUp(el, duration = 900) {
    const target = Number(el.dataset.countup);
    if (!Number.isFinite(target)) return;
    const fmt = (v) => (el.dataset.format === 'vnd' ? `${Math.round(v).toLocaleString('vi-VN')} ₫` : Math.round(v).toLocaleString('vi-VN'));
    if (reduce()) { el.textContent = fmt(target); return; }
    const start = performance.now();
    const step = (now) => {
        const p = Math.min(1, (now - start) / duration);
        el.textContent = fmt(target * (1 - (1 - p) ** 3));   // ease-out
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', () => {
    animateProgress();
    document.querySelectorAll('[data-countup][data-countup-auto]').forEach((el) => countUp(el));
});

window.crmMotion = { countUp, animateProgress };
