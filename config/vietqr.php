<?php

/*
| VietQR (chuẩn NAPAS 247 / EMVCo): mã BIN ngân hàng thụ hưởng. Dùng tạo mã QR chuyển khoản trên hóa đơn —
| khách quét bằng app ngân hàng, tiền chuyển thẳng vào tài khoản của công ty (CRM không giữ tiền).
| Khi thêm ngân hàng mới: tra mã BIN 6 số theo danh sách NAPAS. Kiểm tra bằng cách quét thử — app ngân hàng sẽ hiện
| đúng tên chủ tài khoản trước khi chuyển.
*/
return [
    'banks' => [
        '970436' => 'Vietcombank (VCB)',
        '970415' => 'VietinBank (CTG)',
        '970418' => 'BIDV',
        '970405' => 'Agribank',
        '970407' => 'Techcombank (TCB)',
        '970422' => 'MB Bank',
        '970416' => 'ACB',
        '970432' => 'VPBank',
        '970423' => 'TPBank',
        '970403' => 'Sacombank (STB)',
        '970437' => 'HDBank',
        '970441' => 'VIB',
        '970443' => 'SHB',
        '970431' => 'Eximbank',
        '970426' => 'MSB',
        '970448' => 'OCB',
        '970440' => 'SeABank',
        '970449' => 'LPBank (LienVietPostBank)',
        '970428' => 'Nam A Bank',
        '970409' => 'Bac A Bank',
        '970425' => 'ABBANK',
        '970427' => 'VietABank',
        '970412' => 'PVcomBank',
        '970452' => 'Kienlongbank',
        '970438' => 'BaoViet Bank',
        '970454' => 'BVBank (Viet Capital)',
        '970400' => 'Saigonbank',
        '970429' => 'SCB',
        '970430' => 'PGBank',
        '970419' => 'NCB',
        '970406' => 'DongA Bank',
        '970408' => 'GPBank',
        '970424' => 'Shinhan Bank',
        '970457' => 'Woori Bank',
    ],
];
