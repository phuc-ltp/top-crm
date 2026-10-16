<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\SalesOrder;
use App\Support\JsTranslations;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_login_page_defaults_to_vietnamese(): void
    {
        $this->admin();
        $this->get('/login')->assertOk()
            ->assertSee('<html lang="vi">', false)
            ->assertSee('Chào mừng trở lại')
            ->assertSee('English');   // lựa chọn ngôn ngữ có sẵn trong menu
    }

    public function test_guest_can_switch_login_page_to_english(): void
    {
        $this->admin();
        $this->from('/login')->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect('/login')
            ->assertCookie(SetLocale::COOKIE, 'en');

        $this->get('/login')->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Welcome back')->assertSee('Sign in')->assertSee('Forgot password?')
            ->assertSee('All rights reserved.')
            ->assertDontSee('Chào mừng trở lại');
    }

    public function test_cookie_alone_selects_language_for_guests(): void
    {
        $this->admin();
        $this->withCookie(SetLocale::COOKIE, 'en')->get('/login')->assertSee('Welcome back');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->admin();
        $this->from('/login')->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->get('/login')->assertSee('Chào mừng trở lại');
        $this->withCookie(SetLocale::COOKIE, 'xx')->get('/login')->assertSee('Chào mừng trở lại');   // cookie lạ bị bỏ qua
    }

    public function test_signed_in_user_preference_is_saved_and_applied_everywhere(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->from(route('dashboard'))->post(route('locale.update'), ['locale' => 'en'])->assertRedirect(route('dashboard'));
        $this->assertSame('en', $user->fresh()->locale);

        // Phiên mới (không còn session/cookie) vẫn theo lựa chọn đã lưu của tài khoản
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Overview')->assertSee('Customers')->assertSee('Today\'s tasks')
            ->assertSee('window.CRM_I18N', false)
            ->assertDontSee('Công việc hôm nay');
    }

    public function test_account_preference_wins_over_cookie(): void
    {
        $user = $this->admin();
        $user->forceFill(['locale' => 'vi'])->save();
        $this->actingAs($user)->withCookie(SetLocale::COOKIE, 'en')->get(route('dashboard'))
            ->assertSee('<html lang="vi"', false)->assertSee('Công việc hôm nay')
            ->assertDontSee('window.CRM_I18N', false);   // tiếng Việt không cần chèn bản dịch JS
    }

    public function test_language_chosen_on_login_page_is_kept_after_sign_in(): void
    {
        $this->admin();
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $this->assertNull($user->locale);

        $this->withCookie(SetLocale::COOKIE, 'en')->post('/login', ['login' => 'nv@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_validation_and_status_labels_are_translated(): void
    {
        $this->admin();
        $this->withCookie(SetLocale::COOKIE, 'en')->from('/login')->post('/login', [])
            ->assertSessionHasErrors(['login' => 'The Email or username field is required.']);

        app()->setLocale('en');
        $this->assertStringContainsString('>Completed<', badge(SalesOrder::STATUSES, 'completed'));
        $this->assertSame('Pending approval', label_of(SalesOrder::STATUSES, 'pending'));
        $this->assertSame('1,5 B ₫', money_short(1_500_000_000));
        app()->setLocale('vi');
        $this->assertSame('Hoàn thành', label_of(SalesOrder::STATUSES, 'completed'));
        $this->assertSame('1,5 tỷ ₫', money_short(1_500_000_000));
    }

    /** Tệp mã nguồn có giao diện / thông báo cho người dùng. */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([resource_path('views'), resource_path('js'), app_path('Http'), app_path('Services'), app_path('Notifications'), app_path('Support')] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $f) {
                if (preg_match('/\.(php|js)$/', $f)) $files[] = (string) $f;
            }
        }
        $files[] = app_path('helpers.php');

        return $files;
    }

    /**
     * Nhãn hiển thị lấy từ hằng số (trạng thái, loại, danh mục quyền, loại báo cáo...) — được dịch động
     * qua badge()/label_of()/__($label) nên không thấy được bằng cách dò __('...') trong mã.
     */
    private function constantLabels(): array
    {
        $labels = [];
        $collect = function ($value) use (&$labels, &$collect) {
            if (is_string($value)) {
                $labels[] = $value;
            } elseif (is_array($value)) {
                // ['Nhãn', 'màu/icon'] -> chỉ lấy nhãn; mảng định nghĩa -> lấy 'label'
                if (array_is_list($value) && isset($value[0]) && is_string($value[0]) && count($value) <= 3) {
                    $labels[] = $value[0];
                } elseif (isset($value['label'])) {
                    $labels[] = $value['label'];
                } else {
                    foreach ($value as $v) $collect($v);
                }
            }
        };
        $skip = ['DEFAULTS', 'TIMEZONES', 'DATE_FORMATS', 'ROLES', 'ALIASES', 'SCOPE_PERMISSIONS', 'ORGANIZATION_KEYS', 'SUBJECTS', 'ENTITIES'];
        $classes = array_map(fn ($f) => 'App\\Models\\'.basename($f, '.php'), glob(app_path('Models/*.php')));
        $classes = array_merge($classes, [\App\Support\PermissionCatalog::class, \App\Services\ReportService::class, \App\Http\Controllers\SettingController::class]);
        foreach ($classes as $class) {
            foreach ((new \ReflectionClass($class))->getReflectionConstants() as $const) {
                if ($const->isPublic() && ! in_array($const->getName(), $skip, true) && is_array($const->getValue())) {
                    $collect($const->getValue());
                }
            }
        }

        // Bỏ mã kỹ thuật thuần (vd: VND) — chỉ giữ chuỗi có chữ cái và không phải toàn chữ in hoa
        return array_filter($labels, fn ($l) => preg_match('/\p{L}/u', $l) && $l !== mb_strtoupper($l));
    }

    /** Mọi câu __('...') / t('...') trong toàn bộ giao diện phải có bản dịch tiếng Anh. */
    public function test_all_screens_have_complete_english_translations(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $keys = JsTranslations::KEYS;
        foreach ($this->sourceFiles() as $file) {
            preg_match_all('/(?<![\w>$])(?:__|t)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', file_get_contents($file), $m);
            $keys = array_merge($keys, array_map('stripcslashes', $m[1]));
        }
        $keys = array_merge($keys, $this->constantLabels());
        $menu = file_get_contents(app_path('Support/Menu.php'));
        preg_match_all("/\[\s*'([^']+)',\s*(?:'bi-[^']+'|null),/u", $menu, $m);
        preg_match_all("/^\s*'([^']+)' => \[\s*$/mu", $menu, $g);
        $keys = array_merge($keys, $m[1], $g[1]);

        $missing = collect($keys)->unique()
            ->reject(fn ($k) => str_starts_with($k, 'labels.') || ! preg_match('/\p{L}/u', $k))
            ->reject(fn ($k) => array_key_exists($k, $en))
            ->values()->all();
        $this->assertSame([], $missing, 'Thiếu bản dịch tiếng Anh trong lang/en.json');
    }

    /** Câu dùng trong JavaScript (t('...')) phải được khai báo để server chèn bản dịch vào trang. */
    public function test_javascript_strings_are_registered(): void
    {
        $used = [];
        foreach ($this->sourceFiles() as $file) {
            if (! str_ends_with($file, '.blade.php') && ! str_ends_with($file, '.js')) continue;
            preg_match_all('/(?<![\w>$])t\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', file_get_contents($file), $m);
            $used = array_merge($used, array_map('stripcslashes', $m[1]));
        }
        $missing = array_values(array_diff(array_unique($used), JsTranslations::KEYS));
        $this->assertSame([], $missing, 'Thêm các câu này vào App\Support\JsTranslations::KEYS');
    }

    public function test_english_json_has_no_empty_or_untranslated_values(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($en as $vi => $text) {
            $this->assertNotSame('', trim($text), "Bản dịch rỗng: $vi");
            $this->assertDoesNotMatchRegularExpression('/[ăâđêôơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/iu', $text, "Còn tiếng Việt: $vi");
            preg_match_all('/:([a-z]+)/', $vi, $a);
            preg_match_all('/:([a-z]+)/', $text, $b);
            $this->assertEqualsCanonicalizing($a[1], $b[1], "Tham số không khớp: $vi");
        }
    }
}
