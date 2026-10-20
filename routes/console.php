<?php

use App\Models\Customer;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
| Tạo / đặt lại tài khoản Super Admin từ dòng lệnh. Mật khẩu do người dùng tự nhập (ẩn ký tự).
*/
Artisan::command('crm:create-admin {--email=} {--name=}', function () {
    $email = $this->option('email') ?: $this->ask('Email quản trị');
    $name = $this->option('name') ?: $this->ask('Họ tên', 'Quản trị viên');
    $password = $this->secret('Mật khẩu (tối thiểu 10 ký tự, có chữ hoa, chữ thường, số)');
    $confirm = $this->secret('Nhập lại mật khẩu');

    $validator = Validator::make(compact('email', 'name', 'password') + ['password_confirmation' => $confirm], [
        'email' => ['required', 'email'],
        'name' => ['required', 'max:255'],
        'password' => ['required', 'confirmed', Password::defaults()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::withTrashed()->firstOrNew(['email' => $email]);
    $user->fill(['name' => $name, 'password' => $password, 'status' => 'active', 'is_demo' => false]);
    $user->deleted_at = null;
    $user->save();
    $user->forceFill(['is_platform_admin' => true])->save();   // quản trị nền tảng
    app(\App\Support\Tenancy\RoleProvisioner::class)->provision(\App\Models\Organization::DEFAULT_ID);
    app(\App\Support\Tenancy\CurrentOrganization::class)->run(\App\Models\Organization::DEFAULT_ID, function () use ($user) {
        if (! $user->belongsToOrganization(\App\Models\Organization::DEFAULT_ID)) {
            $user->organizations()->syncWithoutDetaching([\App\Models\Organization::DEFAULT_ID => ['status' => 'active', 'joined_at' => now()]]);
        }
        $user->syncRoles([\App\Support\PermissionCatalog::OWNER]);
    });
    \App\Support\AppInitialization::mark();   // đóng trang /setup vĩnh viễn
    $this->info("Đã tạo/cập nhật Super Admin: {$email}");

    return 0;
})->purpose('Tạo hoặc đặt lại tài khoản Super Admin');

/*
| Xóa toàn bộ dữ liệu demo (is_demo = 1). Không ảnh hưởng dữ liệu thật.
*/
Artisan::command('crm:purge-demo {--force}', function () {
    if (! $this->option('force') && ! $this->confirm('Xóa VĨNH VIỄN toàn bộ dữ liệu demo (is_demo = 1)?')) {
        return 1;
    }

    DB::transaction(function () {
        // Kho (Phase 3): sổ kho / dòng phiếu của phiếu demo, giữ hàng của đơn demo, tồn kho của sản phẩm demo
        $demoDocs = DB::table('stock_documents')->where('is_demo', true)->pluck('id');
        DB::table('stock_movements')->whereIn('stock_document_id', $demoDocs)->delete();
        DB::table('stock_document_items')->whereIn('stock_document_id', $demoDocs)->delete();
        DB::table('stock_reservations')->whereIn('sales_order_id', DB::table('sales_orders')->where('is_demo', true)->select('id'))->delete();
        $this->line('  - stock_documents: '.DB::table('stock_documents')->whereIn('id', $demoDocs)->delete().' bản ghi');
        $demoProducts = DB::table('products')->where('is_demo', true)->pluck('id');
        if (DB::table('stock_movements')->whereIn('product_id', $demoProducts)->doesntExist()) {
            DB::table('stock_levels')->whereIn('product_id', $demoProducts)->delete();
        }
        DB::table('price_list_items')->whereIn('product_id', $demoProducts)->delete();
        DB::table('credit_notes')->where('is_demo', true)->delete();

        $tables = ['payments', 'invoices', 'sales_orders', 'quotations', 'support_tickets', 'tasks', 'opportunities',
            'leads', 'contacts', 'campaigns', 'customers', 'products', 'suppliers'];
        foreach ($tables as $table) {
            $count = DB::table($table)->where('is_demo', true)->delete();
            $this->line("  - $table: $count bản ghi");
        }
        $demoUsers = User::withTrashed()->where('is_demo', true)->get();
        foreach ($demoUsers as $u) {
            $u->syncRoles([]);
            $u->forceDelete();
        }
        $this->line('  - users: '.$demoUsers->count().' bản ghi');
        // Hoạt động / lịch hẹn gắn với bản ghi demo vừa xóa (khách hàng, người liên hệ, lead, cơ hội)
        $orphans = 0;
        foreach (['customer' => 'customers', 'contact' => 'contacts', 'lead' => 'leads', 'opportunity' => 'opportunities'] as $type => $table) {
            $orphans += DB::table('interactions')->where('interactable_type', $type)->whereNotIn('interactable_id', DB::table($table)->select('id'))->delete();
        }
        $this->line("  - interactions: $orphans bản ghi");
    });
    // Cờ "có dữ liệu demo" được cache theo từng tổ chức
    foreach (DB::table('organizations')->pluck('id') as $orgId) {
        cache()->forget('crm.has_demo.'.$orgId);
    }
    $this->info('Đã xóa dữ liệu demo.');

    return 0;
})->purpose('Xóa toàn bộ dữ liệu demo');

/*
| Nhắc việc sắp đến hạn / đánh dấu quá hạn. Chạy mỗi 15 phút qua scheduler,
| hoặc thủ công: php artisan crm:task-reminders
*/
/*
| Tác vụ bán hàng định kỳ: báo giá quá hạn → hết hạn. Chạy mỗi giờ qua scheduler, hoặc: php artisan crm:sales-automation
*/
Artisan::command('crm:sales-automation', function (\App\Services\SalesAutomationService $service) {
    $result = $service->run();
    $this->info('Báo giá chuyển hết hạn: '.$result['expired_quotations'].' · Nhắc hóa đơn sắp đến hạn: '.$result['due_reminders'].' · Báo hóa đơn quá hạn: '.$result['overdue_invoices']);
})->purpose('Tác vụ bán hàng định kỳ (báo giá hết hạn, nhắc thanh toán)');

/*
| Dọn thông báo cũ: đã đọc quá 90 ngày, chưa đọc quá 180 ngày (giữ bảng thông báo gọn nhẹ).
*/
Artisan::command('crm:prune-notifications', function () {
    $read = DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', now()->subDays(90))->delete();
    $unread = DB::table('notifications')->whereNull('read_at')->where('created_at', '<', now()->subDays(180))->delete();
    $this->info("Đã xóa $read thông báo đã đọc và $unread thông báo chưa đọc quá hạn lưu.");
})->purpose('Dọn thông báo cũ');

/*
| SLA ticket: cảnh báo sắp trễ (≤ 1 giờ) và leo thang ticket quá hạn. Chạy mỗi 10 phút, hoặc: php artisan crm:ticket-sla
*/
Artisan::command('crm:ticket-sla', function (\App\Services\SlaService $service) {
    $r = $service->runEscalations();
    $this->info('Cảnh báo sắp trễ: '.$r['warned'].' · Leo thang: '.$r['escalated']);
})->purpose('Cảnh báo và leo thang ticket vi phạm SLA');

Artisan::command('crm:task-reminders', function (TaskReminderService $service) {
    $result = $service->run();
    $this->info("Nhắc sắp đến hạn: {$result['due_soon']} · Chuyển quá hạn: {$result['overdue']} · Nhắc hoạt động: {$result['activities']}");
})->purpose('Gửi nhắc việc và đánh dấu công việc quá hạn');

/*
| Cài đặt / cập nhật an toàn (idempotent): tạo database nếu chưa có, chạy migration còn thiếu,
| nạp dữ liệu cấu hình. KHÔNG xóa dữ liệu, KHÔNG nạp dữ liệu demo.
*/
Artisan::command('crm:install', function () {
    $cfg = config('database.connections.mysql');
    try {
        $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']}", $cfg['username'], $cfg['password']);
        $db = str_replace('`', '', $cfg['database']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->line("  [OK]   Database {$db} sẵn sàng");
    } catch (\Throwable $e) {
        $this->error('  [LỖI]  Không kết nối được MySQL: '.$e->getMessage());

        return 1;
    }

    $this->call('migrate', ['--force' => true]);
    \App\Services\ActivityLogger::$enabled = false;
    $this->call('db:seed', ['--class' => \Database\Seeders\RolePermissionSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => \Database\Seeders\ReferenceDataSeeder::class, '--force' => true]);
    if (! file_exists(public_path('storage'))) {
        $this->call('storage:link');
    }

    return 0;
})->purpose('Cài đặt/cập nhật cấu trúc database an toàn (không xóa dữ liệu)');

/*
| Kiểm tra tình trạng hệ thống (dùng bởi START_CRM.bat / CHECK_CRM.bat). Mã thoát 0 = OK.
*/
Artisan::command('crm:health', function () {
    $ok = true;
    $line = function (bool $pass, string $text) use (&$ok) {
        $ok = $ok && $pass;
        $this->line(($pass ? '  [OK]   ' : '  [LỖI]  ').$text);
    };

    $line(PHP_VERSION_ID >= 80200, 'PHP '.PHP_VERSION.' (yêu cầu >= 8.2)');
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd', 'zip', 'intl'] as $ext) {
        $line(extension_loaded($ext), "PHP extension: $ext");
    }
    $line(file_exists(base_path('.env')) && config('app.key'), 'Tệp .env và APP_KEY');
    $line(is_dir(base_path('vendor')), 'Thư viện PHP (vendor)');
    $line(file_exists(public_path('build/manifest.json')), 'Giao diện đã build (public/build)');
    $line(is_writable(storage_path('logs')), 'Thư mục storage ghi được');

    try {
        DB::connection()->getPdo();
        $line(true, 'Kết nối MySQL: '.config('database.connections.mysql.database').'@'.config('database.connections.mysql.host'));
        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
            ->diff(DB::table('migrations')->pluck('migration'));
        $line($pending->isEmpty(), 'Cấu trúc database đầy đủ'.($pending->isEmpty() ? '' : ' (thiếu '.$pending->count().' migration)'));
        $this->line(User::where('is_platform_admin', true)->exists() ? '  [OK]   Đã có tài khoản quản trị nền tảng' : '  [!]    Chưa có quản trị nền tảng -> mở trang /setup trên trình duyệt để tạo');
        $this->line('  [i]    Khách hàng: '.Customer::count().(Customer::where('is_demo', true)->exists() ? ' (có dữ liệu DEMO)' : ''));
    } catch (\Throwable $e) {
        $line(false, 'Kết nối MySQL thất bại: '.$e->getMessage());
    }

    $this->newLine();
    $this->line($ok ? '  => Hệ thống sẵn sàng.' : '  => Có lỗi cần xử lý (xem docs/TROUBLESHOOTING.md).');

    return $ok ? 0 : 1;
})->purpose('Kiểm tra tình trạng hệ thống OrangeCRM');

Schedule::command('crm:task-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('crm:sales-automation')->hourly()->withoutOverlapping();
Schedule::command('crm:prune-notifications')->dailyAt('02:30');
Schedule::command('crm:ticket-sla')->everyTenMinutes()->withoutOverlapping();
// Xóa lịch sử đăng nhập quá thời hạn lưu (LoginEvent::RETENTION_DAYS)
Schedule::command('model:prune', ['--model' => [\App\Models\LoginEvent::class]])->daily();
