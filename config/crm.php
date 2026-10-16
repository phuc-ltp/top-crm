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

    // Quốc gia của khách hàng: mã ISO 3166-1 alpha-2 => [tên tiếng Việt, tên tiếng Anh]
    'countries' => [
        'VN' => ['Việt Nam', 'Vietnam'], 'LA' => ['Lào', 'Laos'], 'KH' => ['Campuchia', 'Cambodia'],
        'TH' => ['Thái Lan', 'Thailand'], 'MY' => ['Malaysia', 'Malaysia'], 'SG' => ['Singapore', 'Singapore'],
        'ID' => ['Indonesia', 'Indonesia'], 'PH' => ['Philippines', 'Philippines'], 'MM' => ['Myanmar', 'Myanmar'],
        'CN' => ['Trung Quốc', 'China'], 'HK' => ['Hồng Kông', 'Hong Kong'], 'TW' => ['Đài Loan', 'Taiwan'],
        'JP' => ['Nhật Bản', 'Japan'], 'KR' => ['Hàn Quốc', 'South Korea'], 'IN' => ['Ấn Độ', 'India'],
        'AU' => ['Úc', 'Australia'], 'NZ' => ['New Zealand', 'New Zealand'], 'US' => ['Hoa Kỳ', 'United States'],
        'CA' => ['Canada', 'Canada'], 'GB' => ['Vương quốc Anh', 'United Kingdom'], 'FR' => ['Pháp', 'France'],
        'DE' => ['Đức', 'Germany'], 'NL' => ['Hà Lan', 'Netherlands'], 'IT' => ['Ý', 'Italy'],
        'ES' => ['Tây Ban Nha', 'Spain'], 'RU' => ['Nga', 'Russia'], 'AE' => ['Các Tiểu vương quốc Ả Rập Thống nhất', 'United Arab Emirates'],
    ],
];
