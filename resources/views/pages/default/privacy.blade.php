<p>Chính sách này mô tả cách {{ $crmSettings['company_name'] }} thu thập, sử dụng và bảo vệ dữ liệu.</p>
<h3>1. Dữ liệu được lưu trữ</h3>
<p>Thông tin tài khoản nhân viên (họ tên, email, số điện thoại, phòng ban), dữ liệu khách hàng và giao dịch do người dùng nhập, cùng nhật ký hoạt động (thời điểm, thao tác, địa chỉ IP).</p>
<h3>2. Bảo mật</h3>
<ul>
    <li>Mật khẩu được mã hóa một chiều (bcrypt), không ai đọc được, kể cả quản trị viên.</li>
    <li>Phân quyền theo vai trò: mỗi người chỉ xem được dữ liệu thuộc phạm vi của mình.</li>
    <li>Giới hạn số lần đăng nhập sai, tự đăng xuất khi tài khoản bị khóa.</li>
    <li>Nhật ký hoạt động không ghi mật khẩu hay mã bí mật.</li>
</ul>
<h3>3. Chia sẻ dữ liệu</h3>
<p>Dữ liệu được lưu trên máy chủ của doanh nghiệp, không được chia sẻ với bên thứ ba. Khi bạn đăng nhập bằng Google hoặc Facebook, hệ thống chỉ nhận địa chỉ email để xác định tài khoản đã được cấp.</p>
<h3>4. Liên hệ</h3>
<p>Mọi yêu cầu liên quan đến dữ liệu cá nhân, vui lòng liên hệ quản trị viên hệ thống
    @if ($crmSettings['company_email'])
        qua email <a href="mailto:{{ $crmSettings['company_email'] }}">{{ $crmSettings['company_email'] }}</a>
    @endif
</p>
<p class="small mt-4" style="color: var(--ta-muted)"><i>Đây là nội dung mẫu. Quản trị viên có thể chỉnh sửa tại Cài đặt → Trang đăng nhập & thương hiệu.</i></p>
