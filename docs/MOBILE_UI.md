# Giao diện điện thoại: hướng dẫn tích hợp

Tài liệu này mô tả cấu trúc đã chuẩn bị sẵn để ghép **thiết kế/code giao diện điện thoại** vào các trang truy cập (đăng nhập, khởi tạo, quên mật khẩu, đặt lại mật khẩu, điều khoản, bảo mật, hỗ trợ).

## 1. Nguyên tắc hiện tại

| Màn hình | Hành vi |
|---|---|
| Máy tính (≥ 992px) | **Cố định đúng 1 màn hình**, không cuộn trang. Kích thước tự co theo chiều cao màn hình (các mốc 900 / 800 / 700 / 620px) |
| Máy tính bảng & điện thoại (< 992px) | Ẩn cột giới thiệu bên trái, chỉ giữ thẻ đăng nhập. **Cho phép cuộn** tự nhiên. Ô nhập cỡ chữ 16px (iPhone không tự phóng to) |

Đã kiểm tra bằng ảnh chụp ở các cỡ: 1920×1080, 1672×941, 1440×860, 1366×768, 1280×650, 820×1180 (máy tính bảng), 390×844 và 360×740 (điện thoại).

## 2. Các tệp liên quan

| Tệp | Vai trò |
|---|---|
| `resources/views/layouts/auth.blade.php` | Khung HTML chung của mọi trang truy cập |
| `resources/views/auth/login.blade.php` | Nội dung thẻ đăng nhập |
| `resources/css/auth.css` | Giao diện **máy tính** (đừng sửa phần điện thoại ở đây) |
| `resources/css/auth-mobile.css` | Giao diện **điện thoại / máy tính bảng**. **Đặt code điện thoại vào đây** |
| `resources/views/components/auth-field.blade.php` | Ô nhập có biểu tượng và nút hiện/ẩn mật khẩu |

`auth-mobile.css` được nạp **sau** `auth.css`, nên quy tắc trong đó luôn ghi đè được giao diện máy tính.

## 3. Các "móc" có sẵn trong HTML

| Class | Ý nghĩa |
|---|---|
| `.ta-mobile-only` | Phần tử chỉ hiện trên điện thoại |
| `.ta-desktop-only` | Phần tử chỉ hiện trên máy tính (cột giới thiệu bên trái đang dùng class này) |
| `.ta-shell` | Khung trang (lưới 3 hàng trên điện thoại: thanh trên / nội dung / chân trang) |
| `.ta-main`, `.ta-card-wrap`, `.ta-card` | Vùng chính, khung chứa thẻ, thẻ đăng nhập |
| `.ta-topbar`, `.ta-footer` | Thanh chọn ngôn ngữ, chân trang |
| `.ta-photo`, `.ta-shade` | Ảnh nền và lớp phủ tối |

Biến CSS chỉnh nhanh trong khối `@media (max-width: 991.98px)` của `auth-mobile.css`: `--ta-input-h` (chiều cao ô nhập), `--ta-card-logo`, `--ta-card-pad-y`, `--ta-card-pad-x`, `--ta-card-gap`.

Thẻ `<meta viewport>` đã có `viewport-fit=cover`, và CSS đã chừa khoảng an toàn cho tai thỏ/thanh điều hướng (`env(safe-area-inset-*)`).

## 4. Cách ghép code điện thoại khi có

1. **Chỉ CSS:** thay nội dung trong các khối `@media` của `resources/css/auth-mobile.css`.
2. **Thêm phần tử HTML riêng cho điện thoại** (ví dụ thanh tiêu đề, banner): thêm vào `layouts/auth.blade.php` với class `ta-mobile-only`.
3. Build lại giao diện: mở Command Prompt tại `C:\phuc-crm`, chạy `npm run build`.
4. Kiểm tra: mở trình duyệt, nhấn **F12**, rồi **Ctrl + Shift + M** (chế độ điện thoại), chọn iPhone/Android.

Nếu gửi code cho trợ lý AI, hãy gửi kèm ảnh thiết kế. Trợ lý sẽ ghép theo đúng các bước trên.
