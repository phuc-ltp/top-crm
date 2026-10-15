<?php

return [
    // Cho phép nạp dữ liệu demo khi chạy db:seed. Không bao giờ nạp khi APP_ENV=production.
    'demo_data' => (bool) env('CRM_DEMO_DATA', false),

    // Tệp đính kèm cho phép tải lên (công việc)
    'attachment_mimes' => 'pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,gif,webp,zip',
    'attachment_max_kb' => 10240,

    'per_page' => 15,

    // Ngôn ngữ giao diện: mã => tên hiển thị (tên luôn viết bằng chính ngôn ngữ đó)
    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],
];
