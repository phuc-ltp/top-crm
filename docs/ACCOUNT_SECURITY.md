# Bảo mật tài khoản (Phase 1.8)

## Quy định mật khẩu
- Áp dụng chung (`App\Support\Security\PasswordPolicy`, đăng ký qua `Password::defaults()`): tối thiểu 10, tối đa 128 ký tự, có chữ hoa, chữ thường và chữ số.
- Dùng ở: trang cài đặt lần đầu, tạo tổ chức, tự đổi mật khẩu, đặt lại qua email, quản trị viên tạo/đặt mật khẩu, lệnh `crm:create-admin`.
- Chỉ áp dụng khi ĐẶT mật khẩu mới; mật khẩu cũ vẫn đăng nhập bình thường.

## Xác thực 2 lớp (TOTP, RFC 6238)
- Ứng dụng tương thích: Google Authenticator, Microsoft Authenticator, Authy (6 số, 30 giây, cho lệch ±30 giây).
- Người dùng tự bật tại **Hồ sơ → Bảo mật tài khoản** (`/profile/security`): nhập mật khẩu → quét QR → nhập mã → nhận 8 mã khôi phục (hiển thị đúng 1 lần).
- Lưu trữ: `users.two_factor_secret` mã hóa bằng `APP_KEY`; mã khôi phục chỉ lưu bản băm SHA-256 (trong cột mã hóa); `two_factor_last_step` chặn dùng lại cùng một mã.
- Đăng nhập: mật khẩu đúng → CHƯA đăng nhập, chuyển tới `/two-factor-challenge` (hết hạn sau 5 phút, sai 5 lần/5 phút theo tài khoản → hủy, phải đăng nhập lại). Áp dụng cả đăng nhập Google/Facebook.
- Tắt: cần mật khẩu + mã hiện tại (hoặc mã khôi phục). Tạo lại mã khôi phục: cần mật khẩu.
- Mất điện thoại và mã khôi phục: quản trị viên vào trang nhân viên → **Tắt xác thực 2 lớp** (cùng điều kiện quyền như đặt mật khẩu; quản trị viên tổ chức không tắt được của chủ sở hữu; tài khoản thuộc nhiều tổ chức chỉ quản trị nền tảng tắt được).
- **Bắt buộc 2FA cho quản trị** (Cài đặt → Bảo mật đăng nhập, chỉ quản trị nền tảng đổi được): quản trị nền tảng, chủ sở hữu, quản trị viên tổ chức chưa bật sẽ bị chuyển tới trang thiết lập — không bị khóa tài khoản. Mặc định TẮT.

> **Lưu ý:** nếu `APP_KEY` thay đổi, khóa 2FA không giải mã được → người dùng cần quản trị viên tắt 2FA rồi bật lại. Sao lưu `.env` (đã có trong `SAO_LUU.bat`).

## Lịch sử đăng nhập & cảnh báo thiết bị mới
- Bảng `login_events` (thuộc tài khoản, không thuộc tổ chức): thành công, sai mật khẩu, sai mã 2 lớp, tài khoản bị khóa, tạm khóa do thử nhiều lần; phương thức; IP; trình duyệt.
- KHÔNG lưu chuỗi gõ vào ô đăng nhập khi không khớp tài khoản (có thể là mật khẩu gõ nhầm ô).
- Chỉ chủ tài khoản xem được (30 lần gần nhất). Tự xóa sau 180 ngày (`model:prune` chạy hằng ngày qua scheduler).
- Thiết bị nhận diện bằng cookie ngẫu nhiên `crm_device` (HttpOnly, mã hóa, 1 năm); DB chỉ lưu bản băm.
- Đăng nhập từ thiết bị chưa từng dùng (trừ lần đăng nhập đầu tiên của tài khoản) → thông báo trong chuông + email.

## Xác minh email
- Không chặn đăng nhập (máy chủ chưa cấu hình gửi email; `MAIL_MAILER=log` ghi thư vào `storage/logs/laravel.log`).
- Người dùng bấm **Gửi email xác minh** trong trang Bảo mật tài khoản; liên kết có chữ ký, hết hạn 60 phút, phải đăng nhập đúng tài khoản.
- Quản trị viên đổi email của nhân viên → trạng thái xác minh bị đặt lại.

## Kiểm thử
`tests/Feature/AccountSecurityTest.php` (20 test): chính sách mật khẩu, luồng 2FA, chống dùng lại mã, giới hạn số lần thử, mã khôi phục một lần, mã hóa bí mật, phân quyền đặt lại 2FA, lịch sử đăng nhập không lộ chuỗi gõ nhầm, cảnh báo thiết bị mới, dọn lịch sử cũ, xác minh email (chữ ký, đúng người), bắt buộc 2FA. Mã TOTP được kiểm tra với bộ giá trị mẫu của RFC 6238.
