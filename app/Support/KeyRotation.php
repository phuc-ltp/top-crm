<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Đổi APP_KEY và MÃ HÓA LẠI mọi dữ liệu đã mã hóa bằng khóa cũ (CCCD, 2FA, mật khẩu email, API key…).
 *
 * An toàn:
 *  - Tìm dữ liệu mã hóa bằng cách QUÉT mọi cột chữ của mọi bảng có cột id (không phụ thuộc danh sách viết tay).
 *  - Kiểm tra trước: TẤT CẢ giá trị phải giải mã được bằng khóa hiện tại, nếu không → dừng, không đổi gì.
 *  - Ghi .env mới ra tệp tạm TRƯỚC; mã hóa lại trong một giao dịch, mỗi giá trị được kiểm tra giải mã lại bằng khóa mới;
 *    xong mới thay .env (rename). Thay .env thất bại → hoàn tác dữ liệu về giá trị cũ.
 *  - Sau đó xóa toàn bộ phiên đăng nhập (mọi người đăng nhập lại) và bộ nhớ đệm cấu hình.
 */
class KeyRotation
{
    /** Bảng không quét: phiên / cache sẽ bị xóa; còn lại là bảng hệ thống không chứa dữ liệu mã hóa. */
    private const SKIP_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

    public static function parseKey(string $key): string
    {
        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }

    /** Chuỗi có dạng dữ liệu mã hóa của Laravel (base64 của JSON có iv, value, mac). */
    public static function looksEncrypted(?string $v): bool
    {
        if ($v === null || strlen($v) < 60 || ! preg_match('/^[A-Za-z0-9+\/=]+$/', $v)) {
            return false;
        }
        $j = json_decode((string) base64_decode($v, true), true);

        return is_array($j) && isset($j['iv'], $j['value'], $j['mac']);
    }

    /** @return list<array{table:string, column:string, id:int|string, value:string}> */
    public static function scan(): array
    {
        $found = [];
        $db = DB::connection()->getDatabaseName();
        $tables = DB::select('SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"', [$db]);
        foreach ($tables as $row) {
            $t = $row->t;
            if (in_array($t, self::SKIP_TABLES, true) || ! Schema::hasColumn($t, 'id')) {
                continue;
            }
            $cols = DB::select('SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                AND DATA_TYPE IN ("varchar","text","mediumtext","longtext","tinytext","char") AND (CHARACTER_MAXIMUM_LENGTH IS NULL OR CHARACTER_MAXIMUM_LENGTH >= 60)', [$db, $t]);
            foreach ($cols as $c) {
                DB::table($t)->select(['id', $c->c])->where($c->c, 'like', 'eyJ%')->orderBy('id')
                    ->each(function ($r) use (&$found, $t, $c) {
                        $v = $r->{$c->c};
                        if (self::looksEncrypted($v)) {
                            $found[] = ['table' => $t, 'column' => $c->c, 'id' => $r->id, 'value' => $v];
                        }
                    }, 500);
            }
        }

        return $found;
    }

    /** Thống kê theo bảng.cột + danh sách giá trị KHÔNG giải mã được bằng $key. */
    public static function preflight(string $key): array
    {
        $enc = new Encrypter(self::parseKey($key), config('app.cipher'));
        $by = [];
        $bad = [];
        foreach (self::scan() as $f) {
            $by[$f['table'].'.'.$f['column']] = ($by[$f['table'].'.'.$f['column']] ?? 0) + 1;
            try {
                $enc->decryptString($f['value']);
            } catch (\Throwable) {
                $bad[] = $f['table'].'.'.$f['column'].' #'.$f['id'];
            }
        }
        ksort($by);

        return ['fields' => $by, 'total' => array_sum($by), 'unreadable' => $bad];
    }

    public static function newKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Thực hiện đổi khóa. $envPath = đường dẫn .env đang dùng. Trả về thống kê; ném lỗi nếu không an toàn.
     *
     * @return array{reencrypted:int, fields:array<string,int>, sessions_cleared:int}
     */
    public static function rotate(string $envPath, ?string $newKey = null): array
    {
        $oldKey = (string) config('app.key');
        $newKey ??= self::newKey();
        if ($oldKey === '' || $newKey === $oldKey) {
            throw new RuntimeException('Khóa hiện tại trống hoặc trùng khóa mới.');
        }
        if (! is_file($envPath) || ! is_writable($envPath) || ! is_writable(dirname($envPath))) {
            throw new RuntimeException('Không ghi được tệp .env / thư mục chứa nó.');
        }
        $env = file_get_contents($envPath);
        if (preg_match_all('/^APP_KEY=.*$/m', $env) !== 1 || ! str_contains($env, 'APP_KEY='.$oldKey)) {
            throw new RuntimeException('Tệp .env không chứa đúng một dòng APP_KEY khớp khóa đang chạy (có thể cấu hình đang được cache).');
        }
        $check = self::preflight($oldKey);
        if ($check['unreadable']) {
            throw new RuntimeException(count($check['unreadable']).' giá trị không giải mã được bằng khóa hiện tại — dừng, không thay đổi gì: '.implode(', ', array_slice($check['unreadable'], 0, 10)));
        }

        $old = new Encrypter(self::parseKey($oldKey), config('app.cipher'));
        $new = new Encrypter(self::parseKey($newKey), config('app.cipher'));

        // .env mới ghi ra tệp tạm trước (bỏ APP_PREVIOUS_KEYS: khóa cũ bị lộ, không giữ lại)
        $newEnv = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$newKey, $env);
        $newEnv = preg_replace('/^APP_PREVIOUS_KEYS=.*\R?/m', '', $newEnv);
        $tmp = $envPath.'.rotating';
        if (file_put_contents($tmp, $newEnv) === false) {
            throw new RuntimeException('Không ghi được tệp tạm .env.rotating');
        }

        $targets = self::scan();
        $done = [];
        try {
            DB::transaction(function () use ($targets, $old, $new, &$done) {
                foreach ($targets as $f) {
                    $plain = $old->decryptString($f['value']);
                    $value = $new->encryptString($plain);
                    if ($new->decryptString($value) !== $plain) {
                        throw new RuntimeException('Kiểm tra giải mã lại thất bại ở '.$f['table'].'.'.$f['column'].' #'.$f['id']);
                    }
                    $n = DB::table($f['table'])->where('id', $f['id'])->where($f['column'], $f['value'])->update([$f['column'] => $value]);
                    if ($n !== 1) {
                        throw new RuntimeException('Dữ liệu vừa thay đổi trong lúc đổi khóa: '.$f['table'].'.'.$f['column'].' #'.$f['id']);
                    }
                    $done[] = $f + ['new' => $value];
                }
            });
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;   // giao dịch đã tự hoàn tác
        }

        if (! @rename($tmp, $envPath)) {
            // Không thay được .env → đưa dữ liệu về khóa cũ
            DB::transaction(function () use ($done) {
                foreach ($done as $f) {
                    DB::table($f['table'])->where('id', $f['id'])->where($f['column'], $f['new'])->update([$f['column'] => $f['value']]);
                }
            });
            @unlink($tmp);
            throw new RuntimeException('Không thay được tệp .env — đã hoàn tác dữ liệu về khóa cũ, không có gì thay đổi.');
        }

        $sessions = Schema::hasTable('sessions') ? DB::table('sessions')->delete() : 0;
        @unlink(app()->getCachedConfigPath());
        // Bộ nhớ đệm còn giữ bản mã hóa CŨ (VD cài đặt: mật khẩu email, API key SePay) → phải xóa, nếu không sẽ lỗi "MAC is invalid"
        \Illuminate\Support\Facades\Cache::flush();

        return ['reencrypted' => count($done), 'fields' => $check['fields'], 'sessions_cleared' => $sessions];
    }
}
