# TOP CRM REST API (v1)

Kết nối phần mềm khác (website, kế toán, Zapier, Google Sheets...) với dữ liệu CRM.

## 1. Lấy khóa API
1. Đăng nhập → ảnh đại diện → **Khóa API** (cần quyền *Kết nối API*; mặc định có ở Chủ sở hữu và Quản trị viên tổ chức).
2. Đặt tên, chọn **phạm vi** cần dùng, chọn thời hạn (30/90/180/365 ngày), nhập mật khẩu.
3. Sao chép khóa ngay: khóa dạng `12|topcrm_xxxxxxxx...` chỉ hiển thị **một lần** (hệ thống chỉ lưu bản băm SHA-256).

## 2. Gọi API
```http
GET /api/v1/customers?q=minh&per_page=20 HTTP/1.1
Host: localhost
Authorization: Bearer 12|topcrm_xxxxxxxx...
Accept: application/json
```
Ví dụ PowerShell:
```powershell
Invoke-RestMethod http://localhost/orangecrm/api/v1/customers -Headers @{ Authorization = "Bearer $env:TOPCRM_KEY"; Accept = 'application/json' }
```

## 3. Endpoint và phạm vi
| Method | Endpoint | Phạm vi cần |
|---|---|---|
| GET | `/api/v1/stats?period=this_month` | `stats:read` |
| GET | `/api/v1/customers`, `/api/v1/customers/{id}` | `customers:read` |
| POST | `/api/v1/customers` | `customers:write` |
| PUT/PATCH/DELETE | `/api/v1/customers/{id}` | `customers:write` |
| GET | `/api/v1/leads`, `/api/v1/leads/{id}` | `leads:read` |
| POST, PUT/PATCH | `/api/v1/leads`, `/api/v1/leads/{id}` | `leads:write` |
| GET | `/api/v1/opportunities`, `/api/v1/opportunities/{id}` | `opportunities:read` |

Danh sách hỗ trợ `q`, `status`, `per_page`, `page`. Phản hồi:
```json
{ "success": true, "message": "OK", "data": [ ... ], "meta": { "current_page": 1, "per_page": 20, "total": 30, "last_page": 2 } }
```

## 4. Quy tắc bảo mật
- **Theo tổ chức:** khóa chỉ thấy dữ liệu của tổ chức đang chọn khi tạo khóa. Bản ghi của tổ chức khác trả về `404`.
- **Không vượt quyền:** quyền thực tế = quyền của người tạo trong tổ chức ∩ phạm vi khóa. Ví dụ nhân viên chỉ xem khách hàng mình phụ trách thì khóa cũng vậy; quyền bị thu hẹp → khóa bị thu hẹp ngay.
- **Tự ngừng hoạt động** khi: hết hạn, bị thu hồi, tài khoản bị khóa/xóa, thành viên bị tạm ngưng/gỡ khỏi tổ chức, tổ chức bị tạm ngưng, hoặc tài khoản quản trị chưa bật 2FA khi tổ chức bắt buộc.
- Mỗi người tối đa 10 khóa còn hiệu lực trong một tổ chức. Không có khóa vĩnh viễn.
- Khóa API **không** dùng được để mở các trang web của hệ thống.
- Quản trị người dùng (`users.manage`) xem và thu hồi được khóa của mọi thành viên trong tổ chức.
- Mọi lần tạo/thu hồi khóa được ghi nhật ký hoạt động (không ghi khóa gốc). Lần dùng gần nhất và IP được hiển thị trong trang Khóa API.

## 5. Mã lỗi
| Mã | Ý nghĩa |
|---|---|
| 401 | Thiếu khóa, khóa sai, hết hạn hoặc đã thu hồi |
| 403 | Khóa thiếu phạm vi, tài khoản không có quyền, hoặc mất quyền truy cập tổ chức |
| 404 | Không có bản ghi trong tổ chức của khóa |
| 409 | Khách hàng có thể bị trùng (gửi `confirm_duplicate=1` để vẫn tạo) |
| 422 | Dữ liệu không hợp lệ (`errors` chứa chi tiết từng trường) |
| 429 | Quá 120 yêu cầu/phút |

## 6. Ghi chú kỹ thuật
- Laravel Sanctum 4 (`config/sanctum.php`, tiền tố khóa `topcrm_`), model `App\Models\ApiToken` (cột `organization_id`, `last_used_ip`).
- `App\Http\Middleware\ApiContext` chạy trước khi tìm bản ghi trên route (priority trước `SubstituteBindings`) để ràng buộc tổ chức; `CurrentOrganization` ưu tiên tổ chức của khóa.
- Kiểm thử: `tests/Feature/ApiTokenTest.php` (14 test).
