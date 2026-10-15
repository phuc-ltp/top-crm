# Sao lưu TOP CRM & cách dùng Git (dành cho người không chuyên)

## 1. Cách sao lưu: bấm đúp `SAO_LUU.bat`

Mỗi lần bấm, hệ thống tự động:

| Phần | Lưu ở đâu | Ghi chú |
|---|---|---|
| Cơ sở dữ liệu (khách hàng, hóa đơn...) | `C:\TOPCRM_Backup\database\*.sql.zip` | Giữ 30 bản gần nhất. MySQL phải đang bật. |
| Tệp tải lên (logo, ảnh nền, đính kèm) | `C:\TOPCRM_Backup\uploads\` | Bản sao mới nhất |
| Cấu hình bí mật `.env` (có APP_KEY) | `C:\TOPCRM_Backup\secrets\` | Giữ 10 bản. **Tuyệt đối không gửi cho ai.** |
| Mã nguồn + lịch sử thay đổi (Git) | `C:\TOPCRM_Backup\git\phuc-crm.git` | Tự tạo "commit" rồi đẩy sang kho này |

Sao lưu chỉ **đọc** dữ liệu, không xóa hay sửa gì của dự án đang chạy.
Nhật ký: `C:\TOPCRM_Backup\backup.log`.

**Nên làm:** bấm sao lưu cuối mỗi ngày làm việc, và trước mỗi lần nâng cấp lớn.
Thỉnh thoảng hãy chép cả thư mục `C:\TOPCRM_Backup` sang USB hoặc ổ cứng ngoài,
vì nếu ổ C hỏng thì bản sao lưu nằm cùng ổ cũng mất theo.

## 2. Git là gì?

Git giống "lịch sử phiên bản" của mã nguồn. Mỗi **commit** là một ảnh chụp toàn bộ code tại một thời điểm,
nên có thể xem lại hoặc quay về bất kỳ mốc nào. Git **không** chứa cơ sở dữ liệu hay tệp `.env`,
vì `.gitignore` đã chặn các tệp đó (script sao lưu còn kiểm tra thêm một lần nữa).

Xem lịch sử: mở PowerShell trong `C:\phuc-crm` rồi gõ `git log --oneline`.

## 3. Khôi phục khi gặp sự cố

- **Mất / hỏng dữ liệu CSDL:** giải nén tệp `.sql.zip` mới nhất rồi nhập bằng phpMyAdmin (tab Import)
  vào một CSDL **mới** để kiểm tra trước. Hãy nhờ Claude làm bước này để tránh ghi đè nhầm dữ liệu đang dùng.
- **Mất thư mục code:** `git clone C:\TOPCRM_Backup\git\phuc-crm.git C:\phuc-crm-moi`, chép lại `.env` từ `secrets\`
  (đổi tên thành `.env`), rồi chạy `composer install`, `npm install`, `npm run build`.

## 4. (Tùy chọn) Sao lưu lên mạng bằng GitHub — kho RIÊNG TƯ

Cách này giúp code vẫn còn kể cả khi máy hỏng. Chỉ mã nguồn được đẩy lên; dữ liệu khách hàng và `.env` thì không.

1. Tạo tài khoản tại https://github.com (miễn phí).
2. Bấm **New repository**, đặt tên `top-crm`, chọn **Private** (bắt buộc), **không** tick thêm README.
3. Gửi đường link kho (dạng `https://github.com/<tên>/top-crm.git`) cho Claude. Claude sẽ chạy:
   `git remote add origin <link>` rồi `git push -u origin main`.
   Lần đầu Windows sẽ mở trình duyệt để bạn đăng nhập GitHub. Đây là bước xác thực an toàn của Git, Claude không thấy mật khẩu của bạn.
4. Từ đó, mỗi lần bấm `SAO_LUU.bat` sẽ tự đẩy thêm lên GitHub.

Không bao giờ chọn **Public**, và không đưa `.env` hay tệp `.sql` có dữ liệu thật lên GitHub.
