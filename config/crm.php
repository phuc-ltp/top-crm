<?php

return [
    // Cho phép nạp dữ liệu demo khi chạy db:seed. Không bao giờ nạp khi APP_ENV=production.
    'demo_data' => (bool) env('CRM_DEMO_DATA', false),

    // Tệp đính kèm cho phép tải lên (công việc)
    'attachment_mimes' => 'pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,gif,webp,zip',
    'attachment_max_kb' => 10240,

    'per_page' => 15,
];
