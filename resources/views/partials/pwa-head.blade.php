{{-- Cài CRM như ứng dụng (PWA): manifest, icon, màu thanh trạng thái; service worker chỉ lưu trang "mất kết nối" (không lưu dữ liệu) --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#9A4A1C">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($crmSettings['company_name'] ?? config('app.name'), 12, '') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register(@json(asset('sw.js'))).catch(function () {}); });
    }
    // Nút "Cài ứng dụng" chỉ hiện khi trình duyệt cho phép cài (sự kiện beforeinstallprompt)
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.crmInstallPrompt = e;
        document.querySelectorAll('[data-pwa-install]').forEach(function (el) { el.classList.remove('d-none'); });
    });
    window.addEventListener('appinstalled', function () {
        window.crmInstallPrompt = null;
        document.querySelectorAll('[data-pwa-install]').forEach(function (el) { el.classList.add('d-none'); });
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pwa-install] button');
        if (!btn || !window.crmInstallPrompt) return;
        window.crmInstallPrompt.prompt();
        window.crmInstallPrompt.userChoice.finally(function () { window.crmInstallPrompt = null; document.querySelectorAll('[data-pwa-install]').forEach(function (el) { el.classList.add('d-none'); }); });
    });
</script>
