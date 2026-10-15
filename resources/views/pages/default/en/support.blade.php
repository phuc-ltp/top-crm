<p>Need help using {{ $crmSettings['company_name'] }}? Contact us:</p>
<ul class="list-unstyled">
    <li class="mb-2"><i class="bi bi-building me-2" style="color: var(--ta-copper)"></i>{{ $crmSettings['copyright_text'] }}</li>
    @if ($crmSettings['company_phone'])<li class="mb-2"><i class="bi bi-telephone me-2" style="color: var(--ta-copper)"></i><a href="tel:{{ $crmSettings['company_phone'] }}">{{ $crmSettings['company_phone'] }}</a></li>@endif
    @if ($crmSettings['company_email'])<li class="mb-2"><i class="bi bi-envelope me-2" style="color: var(--ta-copper)"></i><a href="mailto:{{ $crmSettings['company_email'] }}">{{ $crmSettings['company_email'] }}</a></li>@endif
    @if ($crmSettings['company_address'])<li class="mb-2"><i class="bi bi-geo-alt me-2" style="color: var(--ta-copper)"></i>{{ $crmSettings['company_address'] }}</li>@endif
    @if (! $crmSettings['company_phone'] && ! $crmSettings['company_email'])<li class="mb-2"><i class="bi bi-person-gear me-2" style="color: var(--ta-copper)"></i>Contact your company's system administrator directly.</li>@endif
</ul>
<h3>Frequently asked questions</h3>
<p><strong>Forgot your password?</strong> Click "Forgot password?" on the sign-in page, or ask an administrator to reset it under Users.</p>
<p><strong>Account locked?</strong> After 5 wrong password attempts, wait 5 minutes. If an administrator locked your account, please contact them.</p>
<p><strong>Can't find a feature?</strong> The menu shows items based on your role. Contact your administrator for additional permissions.</p>