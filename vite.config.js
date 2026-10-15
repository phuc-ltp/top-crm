import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // Đường dẫn tương đối: font/ảnh trong CSS hoạt động cả ở http://localhost/orangecrm (Apache) lẫn http://127.0.0.1:8000
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/auth.css', 'resources/css/auth-mobile.css','resources/js/app.js'],
            refresh: true,
        }),
    ],
});
