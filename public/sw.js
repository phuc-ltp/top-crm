/*
 * Service worker của TOP CRM (PWA).
 * BẢO MẬT: không lưu bất kỳ trang / dữ liệu đã đăng nhập nào vào bộ nhớ đệm. Chỉ lưu sẵn trang "mất kết nối" và icon;
 * khi mở trang mà không có mạng thì hiện trang mất kết nối thay vì lỗi trình duyệt.
 */
const CACHE = 'topcrm-shell-v1';
const SHELL = ['offline.html', 'icons/icon-192.png', 'icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL.map((p) => new URL(p, self.registration.scope).href))));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;                       // form, API ghi dữ liệu: luôn đi thẳng lên máy chủ
    if (req.mode === 'navigate') {
        // Trang: luôn lấy mới từ máy chủ; chỉ khi mất mạng mới hiện trang mất kết nối (không lưu trang vào bộ nhớ đệm)
        event.respondWith(fetch(req).catch(() => caches.match(new URL('offline.html', self.registration.scope).href)));
    }
});
