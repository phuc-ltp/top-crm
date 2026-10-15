# Bảo mật TOP CRM

*Tổng hợp các lớp bảo vệ, cách vận hành an toàn và việc phải làm trước khi đưa lên Internet. Cập nhật: hết Phase 1.*
Chi tiết từng phần: [ACCOUNT_SECURITY.md](ACCOUNT_SECURITY.md) · [API.md](API.md) · [MULTI_TENANCY.md](MULTI_TENANCY.md) · [RBAC.md](RBAC.md) · [GIT_BACKUP.md](GIT_BACKUP.md).

## 1. Hiện trạng triển khai
- Chạy trên máy Windows (XAMPP). Apache chỉ trỏ vào `public/` và **`Require local`** — chỉ truy cập được từ chính máy này.
- MariaDB chỉ lắng nghe `127.0.0.1`. Tài khoản `root` chưa có mật khẩu (chấp nhận được vì chỉ truy cập nội bộ — **phải đổi** trước khi mở ra mạng).
- `APP_ENV=local`, `APP_DEBUG=true` (chỉ dùng trên máy cá nhân).

## 2. Các lớp bảo vệ

### Tài khoản & đăng nhập
| Biện pháp | Chi tiết |
|---|---|
| Mật khẩu | bcrypt; quy định chung 10–128 ký tự, chữ hoa + thường + số (`PasswordPolicy`) |
| Chống dò mật khẩu | 5 lần sai / 5 phút theo tài khoản + IP; giới hạn tần suất các route nhạy cảm |
| Xác thực 2 lớp | TOTP (RFC 6238), khóa bí mật mã hóa, 8 mã khôi phục lưu dạng băm, chặn dùng lại mã, 5 lần sai / 5 phút theo tài khoản; áp dụng cả Google/Facebook |
| Bắt buộc 2FA cho quản trị | Tùy chọn (mặc định tắt), chỉ quản trị nền tảng bật/tắt |
| Lịch sử đăng nhập | `login_events`, chỉ chủ tài khoản xem, không lưu chuỗi gõ nhầm, xóa sau 180 ngày |
| Cảnh báo thiết bị mới | Chuông + email; thiết bị nhận diện bằng cookie ngẫu nhiên (DB chỉ lưu băm) |
| Phiên | Lưu DB, **mã hóa** (`SESSION_ENCRYPT=true`), HttpOnly, SameSite=Lax, hết hạn 120 phút; đổi mật khẩu → đăng xuất thiết bị khác; khóa tài khoản → đẩy ra ngay; xem & đăng xuất từng thiết bị |
| Trang /setup | Khóa vĩnh viễn sau khi tạo quản trị đầu tiên (khóa DB + cờ cài đặt) |
| Quên mật khẩu | Luôn trả cùng thông báo (không lộ email tồn tại), liên kết hết hạn |

### Phân quyền & cách ly dữ liệu
- **Nhiều tổ chức:** mọi truy vấn nghiệp vụ tự lọc theo tổ chức; không xác định được tổ chức → không trả dữ liệu (fail-closed). Tổ chức được xác định **trước khi** tìm bản ghi trên route. 15 bài test cách ly (`TenantIsolationTest`) + test API theo tổ chức.
- **Quyền hành động** theo vai trò của từng tổ chức; **phạm vi dữ liệu** "chỉ của mình" cho người không có quyền xem tất cả.
- Chủ sở hữu và quản trị nền tảng được bảo vệ: quản trị viên thường không sửa/xóa/khóa/tắt 2FA của họ.
- Tài khoản thuộc nhiều tổ chức: chỉ chủ tài khoản hoặc quản trị nền tảng đổi được email/mật khẩu/2FA.

### API
- Khóa Sanctum gắn một tổ chức, có thời hạn (≤ 365 ngày), 6 phạm vi, quyền thực tế = quyền người tạo ∩ phạm vi; chỉ lưu băm; hiển thị một lần; tiền tố `topcrm_` để công cụ quét bí mật nhận ra; 120 yêu cầu/phút/khóa; tự vô hiệu khi mất quyền truy cập.

### Ứng dụng web
- CSRF cho mọi form; Blade escape chống XSS; Eloquent/binding chống SQL injection; cột sắp xếp theo whitelist; `LIKE` được escape.
- Upload kiểm tra MIME/kích thước/kích thước ảnh, tên tệp ngẫu nhiên; tệp đính kèm lưu ngoài `public/`, tải qua controller có kiểm tra quyền.
- Header: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (tắt camera, micro, vị trí).
- Xuất CSV chống formula injection; thông báo chống open-redirect.
- Tiền luôn tính lại ở backend; thanh toán khóa dòng hóa đơn chống ghi đồng thời.

### Dữ liệu nhạy cảm
| Dữ liệu | Cách lưu |
|---|---|
| Mật khẩu | bcrypt (không giải ngược được) |
| Khóa 2FA, secret OAuth | Mã hóa bằng `APP_KEY` |
| Mã khôi phục 2FA, khóa API, cookie thiết bị | Chỉ lưu băm SHA-256 |
| Nhật ký hoạt động | Không ghi mật khẩu, token, mã 2FA, khóa API |
| `.env`, bản sao lưu | Không bao giờ đưa lên GitHub (`.gitignore`); bản sao `.env` lưu ở `C:\TOPCRM_Backup\secrets` với quyền truy cập hạn chế |
| File SQL trong kho mã | Chỉ cấu trúc + dữ liệu demo (tạo từ database tạm), không có tài khoản quản trị hay dữ liệu thật |

## 3. Vận hành an toàn
- **Sao lưu:** bấm `SAO_LUU.bat` (database, tệp tải lên, `.env`, mã nguồn → `C:\TOPCRM_Backup` + GitHub riêng tư). Nên chạy hằng ngày và trước mỗi lần cập nhật.
- **Giữ kỹ `APP_KEY`** (trong `.env`): mất khóa này thì khóa 2FA và secret OAuth không giải mã được (người dùng phải bật lại 2FA).
- Quản trị viên nên bật 2FA; cân nhắc bật "Bắt buộc 2FA cho quản trị".
- Mỗi nhân viên một tài khoản; thu hồi khóa API không dùng; khóa tài khoản người nghỉ việc ngay.
- Kiểm tra lỗ hổng thư viện định kỳ: `composer audit` và `npm audit`.

## 4. Khi nghi ngờ bị xâm nhập
1. Đổi mật khẩu tài khoản bị nghi ngờ; vào **Phiên đăng nhập** → đăng xuất các thiết bị khác.
2. Xem **Bảo mật tài khoản → Lịch sử đăng nhập** và **Nhật ký hoạt động** để biết đã có gì xảy ra.
3. Thu hồi khóa API liên quan (**Khóa API**); quản trị viên có thể tắt 2FA/khóa tài khoản nhân viên.
4. Nếu nghi lộ `.env`: tạo `APP_KEY` mới **chỉ sau khi** đã có phương án cho dữ liệu mã hóa (2FA, secret OAuth), đổi mật khẩu database và secret OAuth.
5. Khôi phục từ bản sao lưu nếu dữ liệu bị sửa/xóa (xem [DATABASE_SETUP.md](DATABASE_SETUP.md)).

## 5. Bắt buộc trước khi đưa lên Internet
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` đúng tên miền.
- [ ] **HTTPS** (chứng chỉ hợp lệ), `SESSION_SECURE_COOKIE=true`, thêm header HSTS.
- [ ] Đặt mật khẩu mạnh cho tài khoản database, tạo tài khoản database riêng (không dùng `root`).
- [ ] Cấu hình gửi email thật (SMTP) để có cảnh báo đăng nhập, xác minh email, đặt lại mật khẩu.
- [ ] Đặt `SANCTUM_STATEFUL_DOMAINS` theo tên miền thật; xem lại CORS cho API.
- [ ] Bật "Bắt buộc 2FA cho quản trị".
- [ ] Tách cài đặt và phòng ban theo tổ chức (Phase 2) trước khi cho nhiều doanh nghiệp dùng chung.
- [ ] Sao lưu tự động hằng ngày ra ngoài máy chủ; thử khôi phục.
- [ ] `composer audit`, `npm audit` không còn lỗ hổng; `php artisan config:cache route:cache view:cache`.
- [ ] Thêm Content-Security-Policy và giới hạn tần suất ở tầng máy chủ web.

## 6. Hạn chế đã biết
- Cài đặt (tên công ty, thương hiệu...) và `users.department_id` còn dùng chung toàn hệ thống.
- Nội dung nhật ký hoạt động và thông báo lưu sẵn bằng tiếng Việt.
- Chưa có Content-Security-Policy; chưa đo hiệu năng dưới tải lớn.
- Chưa có kiểm thử xâm nhập (pentest) độc lập.
