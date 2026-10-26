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
/*
| Nạp dữ liệu DEMO (is_demo = 1) vào MỘT tổ chức để thử nghiệm: php artisan crm:seed-demo 1
| Không chạy ở production; bỏ qua nếu tổ chức đã có dữ liệu demo. Xóa lại bằng: php artisan crm:purge-demo
*/
Artisan::command('crm:seed-demo {organization : ID tổ chức} {--force}', function () {
    $org = \App\Models\Organization::find((int) $this->argument('organization'));
    if (! $org) {
        $this->error('Không tìm thấy tổ chức.');

        return 1;
    }
    if (app()->environment('production')) {
        $this->error('Không nạp dữ liệu demo trên môi trường production.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm("Nạp dữ liệu DEMO vào tổ chức #{$org->id} \"{$org->name}\"?")) {
        return 1;
    }
    \App\Services\ActivityLogger::$enabled = false;
    app(\App\Support\Tenancy\CurrentOrganization::class)->run($org->id, function () {
        $seeder = new \Database\Seeders\DemoDataSeeder;
        $seeder->setCommand($this);
        $seeder->run();
    });
    \App\Services\ActivityLogger::$enabled = true;
    cache()->forget('crm.has_demo.'.$org->id);

    return 0;
})->purpose('Nạp dữ liệu demo vào một tổ chức');

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

        $tables = ['contracts', 'payments', 'invoices', 'sales_orders', 'quotations', 'support_tickets', 'tasks', 'projects', 'opportunities',
            'leads', 'contacts', 'campaigns', 'customers', 'products', 'suppliers',
            // Phase 5 (lịch sử luân chuyển, kết quả kiểm kê, lần gán giấy phép, phiếu lương tự xóa theo khóa ngoại)
            'software_licenses', 'asset_audits', 'assets', 'payroll_periods'];
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
        // Thông báo của tài khoản demo vừa xóa (bảng notifications không có khóa ngoại tới users)
        $this->line('  - notifications: '.DB::table('notifications')->where('notifiable_type', 'user')->whereNotIn('notifiable_id', DB::table('users')->select('id'))->delete().' bản ghi');
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

/*
| Xóa ảnh selfie chấm công quá hạn lưu (cài đặt attendance_selfie_days của từng tổ chức, mặc định 90 ngày). Giờ công giữ nguyên.
*/
Artisan::command('crm:prune-attendance-selfies', function () {
    $deleted = 0;
    foreach (\App\Models\Organization::pluck('id') as $orgId) {
        app(\App\Support\Tenancy\CurrentOrganization::class)->run((int) $orgId, function () use (&$deleted) {
            $before = now()->subDays((int) \App\Services\AttendanceService::setting('attendance_selfie_days'))->toDateString();
            \App\Models\AttendanceRecord::where('work_date', '<', $before)->where(fn ($q) => $q->whereNotNull('check_in_selfie')->orWhereNotNull('check_out_selfie'))
                ->chunkById(200, function ($records) use (&$deleted) {
                    foreach ($records as $r) {
                        foreach (['check_in_selfie', 'check_out_selfie'] as $f) {
                            if ($r->{$f}) {
                                \Illuminate\Support\Facades\Storage::disk('local')->delete($r->{$f});
                                $deleted++;
                            }
                        }
                        $r->forceFill(['check_in_selfie' => null, 'check_out_selfie' => null])->saveQuietly();
                    }
                });
        });
    }
    $this->info("Đã xóa $deleted ảnh selfie quá hạn lưu.");
})->purpose('Xóa ảnh selfie chấm công quá hạn lưu');

/*
| Nhắc giấy phép phần mềm sắp / đã hết hạn (mốc 30 / 7 / 0 ngày) và tài sản mượn quá hạn. Chạy 08:00 mỗi ngày, hoặc: php artisan crm:asset-reminders
*/
Artisan::command('crm:asset-reminders', function (\App\Services\LicenseService $service) {
    $r = $service->runReminders();
    $this->info('Nhắc giấy phép: '.$r['licenses'].' · Nhắc mượn quá hạn: '.$r['loans']);
})->purpose('Nhắc giấy phép sắp hết hạn và tài sản mượn quá hạn');

/*
| Nhắc hợp đồng sắp / đã hết hạn (mốc 30 / 7 / 0 ngày). Chạy 08:05 mỗi ngày, hoặc: php artisan crm:contract-reminders
*/
Artisan::command('crm:contract-reminders', function (\App\Services\ContractService $service) {
    $this->info('Nhắc hợp đồng: '.$service->runReminders());
})->purpose('Nhắc hợp đồng sắp hết hạn');

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

/*
| Kiểm tra trước khi đưa lên web công khai (go-live). [LỖI] = bắt buộc sửa; [CẢNH BÁO] = nên sửa.
| Chạy trên máy chủ thật sau khi cấu hình .env: php artisan crm:production-check
| Chỉ đọc cấu hình đã nạp (config), không in ra bất kỳ giá trị bí mật nào.
*/
Artisan::command('crm:production-check', function () {
    $fail = 0;
    $warn = 0;
    $check = function (bool $pass, string $text, bool $critical = true) use (&$fail, &$warn) {
        if ($pass) {
            $this->line('  [OK]        '.$text);
        } elseif ($critical) {
            $fail++;
            $this->line('  <fg=red>[LỖI]</>       '.$text);
        } else {
            $warn++;
            $this->line('  <fg=yellow>[CẢNH BÁO]</>  '.$text);
        }
    };

    $this->info('Cấu hình ứng dụng');
    $check(app()->isProduction(), 'APP_ENV=production');
    $check(! config('app.debug'), 'APP_DEBUG=false (không lộ chi tiết lỗi, mã nguồn, cấu hình cho người lạ)');
    $check((bool) config('app.key'), 'APP_KEY đã đặt (dùng mã hóa thông tin nhạy cảm — phải sao lưu, không được đổi)');
    $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL dùng https://');
    $check((bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true (cookie phiên chỉ gửi qua HTTPS)');
    $check((bool) config('session.encrypt'), 'SESSION_ENCRYPT=true');
    $check(! config('crm.demo_data'), 'CRM_DEMO_DATA=false (không tự tạo dữ liệu demo)');
    $check(config('logging.channels.single.level', 'debug') !== 'debug', 'LOG_LEVEL không phải debug (khuyến nghị warning hoặc error)', false);
    $check(! in_array(config('mail.default'), ['log', 'array'], true) && filled(config('mail.from.address')),
        'Máy chủ gửi thư (MAIL_*) đã cấu hình — cần cho quên mật khẩu, thông báo email', false);
    $check(app()->configurationIsCached(), 'Đã cache cấu hình (php artisan config:cache)', false);
    $check(app()->routesAreCached(), 'Đã cache route (php artisan route:cache)', false);

    $this->info('Cơ sở dữ liệu');
    try {
        DB::connection()->getPdo();
        $check(true, 'Kết nối MySQL');
        $check(config('database.connections.mysql.username') !== 'root', 'Không dùng tài khoản MySQL root (tạo user riêng chỉ có quyền trên database CRM)');
        $check(filled(config('database.connections.mysql.password')), 'Tài khoản MySQL có mật khẩu');
        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()->diff(DB::table('migrations')->pluck('migration'));
        $check($pending->isEmpty(), 'Đã chạy đủ migration'.($pending->isEmpty() ? '' : ' (thiếu '.$pending->count().')'));
        $demo = collect(['customers', 'leads', 'opportunities', 'invoices', 'products', 'tasks', 'users', 'projects'])
            ->sum(fn ($t) => DB::table($t)->where('is_demo', true)->count());
        $check($demo === 0, 'Không còn dữ liệu demo'.($demo ? " (còn $demo bản ghi — chạy php artisan crm:purge-demo sau khi sao lưu)" : ''));
        $check(User::where('is_platform_admin', true)->exists(), 'Đã có tài khoản quản trị nền tảng');
        $check(\App\Models\Setting::get('require_2fa_admins') === '1', 'Bắt buộc xác thực 2 lớp cho quản trị (Cài đặt › Bảo mật)', false);
    } catch (\Throwable $e) {
        $check(false, 'Kết nối MySQL thất bại');
    }

    $this->info('Tệp & thư mục');
    $check(file_exists(public_path('build/manifest.json')), 'Giao diện đã build (npm run build)');
    $check(is_writable(storage_path('logs')) && is_writable(storage_path('framework')), 'storage/ ghi được');
    $check(file_exists(public_path('storage')), 'Đã tạo liên kết storage (php artisan storage:link) — cần cho ảnh đại diện, logo', false);
    $check(! file_exists(public_path('.env')), 'Không có tệp .env trong thư mục public');

    $this->newLine();
    if ($fail === 0) {
        $this->info("=> Sẵn sàng go-live".($warn ? " (còn $warn cảnh báo nên xử lý)." : '.'));
    } else {
        $this->error("=> CHƯA sẵn sàng: $fail lỗi bắt buộc, $warn cảnh báo. Xem docs/GO_LIVE.md.");
    }

    return $fail === 0 ? 0 : 1;
})->purpose('Kiểm tra cấu hình trước khi đưa lên web công khai');

Schedule::command('crm:task-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('crm:sales-automation')->hourly()->withoutOverlapping();
Schedule::command('crm:prune-notifications')->dailyAt('02:30');
Schedule::command('crm:ticket-sla')->everyTenMinutes()->withoutOverlapping();
Schedule::command('crm:prune-attendance-selfies')->dailyAt('03:00');
Schedule::command('crm:asset-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('crm:contract-reminders')->dailyAt('08:05')->withoutOverlapping();
// Xóa lịch sử đăng nhập quá thời hạn lưu (LoginEvent::RETENTION_DAYS)
Schedule::command('model:prune', ['--model' => [\App\Models\LoginEvent::class]])->daily();
