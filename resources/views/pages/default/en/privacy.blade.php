<p>This policy describes how {{ $crmSettings['company_name'] }} collects, uses and protects data.</p>
<h3>1. Data we store</h3>
<p>Employee account details (name, email, phone number, department), customer and transaction data entered by users, and the activity log (time, action, IP address).</p>
<h3>2. Security</h3>
<ul>
    <li>Passwords are stored with one-way hashing (bcrypt); nobody can read them, administrators included.</li>
    <li>Role-based permissions: each person only sees data within their own scope.</li>
    <li>Failed sign-in attempts are limited, and users are signed out automatically when their account is locked.</li>
    <li>The activity log never records passwords or secret keys.</li>
</ul>
<h3>3. Data sharing</h3>
<p>Data is stored on the company's own server and is not shared with third parties. When you sign in with Google or Facebook, the system only receives your email address to identify your existing account.</p>
<h3>4. Contact</h3>
<p>For any request regarding personal data, please contact the system administrator
    @if ($crmSettings['company_email'])
        at <a href="mailto:{{ $crmSettings['company_email'] }}">{{ $crmSettings['company_email'] }}</a>
    @endif
</p>
<p class="small mt-4" style="color: var(--ta-muted)"><i>This is sample content. Administrators can edit it in System settings → Sign-in & branding.</i></p>