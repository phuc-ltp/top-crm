<p>Cần hỗ trợ khi sử dụng {{ $crmSettings['company_name'] }}? Hãy liên hệ:</p>
<ul class="list-unstyled">
    <li class="mb-2"><i class="bi bi-building me-2" style="color: var(--ta-copper)"></i>{{ $crmSettings['copyright_text'] }}</li>
    @if ($crmSettings['company_phone'])<li class="mb-2"><i class="bi bi-telephone me-2" style="color: var(--ta-copper)"></i><a href="tel:{{ $crmSettings['company_phone'] }}">{{ $crmSettings['company_phone'] }}</a></li>@endif
    @if ($crmSettings['company_email'])<li class="mb-2"><i class="bi bi-envelope me-2" style="color: var(--ta-copper)"></i><a href="mailto:{{ $crmSettings['company_email'] }}">{{ $crmSettings['company_email'] }}</a></li>@endif
    @if ($crmSettings['company_address'])<li class="mb-2"><i class="bi bi-geo-alt me-2" style="color: var(--ta-copper)"></i>{{ $crmSettings['company_address'] }}</li>@endif
    @if (! $crmSettings['company_phone'] && ! $crmSettings['company_email'])<li class="mb-2"><i class="bi bi-person-gear me-2" style="color: var(--ta-copper)"></i>Liên hệ trực tiếp quản trị viên hệ thống trong công ty.</li>@endif
</ul>
<h3>Câu hỏi thường gặp</h3>
<p><strong>Quên mật khẩu?</strong> Bấm "Quên mật khẩu?" ở trang đăng nhập, hoặc nhờ quản trị viên đặt lại trong mục Người dùng.</p>
<p><strong>Tài khoản bị khóa?</strong> Sau 5 lần nhập sai mật khẩu, hãy đợi 5 phút. Nếu quản trị viên đã khóa tài khoản, hãy liên hệ họ.</p>
<p><strong>Không thấy một chức năng?</strong> Menu hiển thị theo vai trò của bạn. Liên hệ quản trị viên để được cấp thêm quyền.</p>
