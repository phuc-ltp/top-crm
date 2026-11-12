<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Sao lưu database bằng PHP (không cần mysqldump / Terminal — dùng được trên hosting dùng chung).
 * Tệp .sql.gz lưu ở storage/app/private/backups (KHÔNG truy cập được từ web), giữ $keep bản mới nhất.
 * Khôi phục: phpMyAdmin → Import tệp .sql.gz (hoặc giải nén rồi import .sql).
 */
class DatabaseBackup
{
    public const PREFIX = 'saoluu-tu-dong-';

    public static function directory(): string
    {
        return storage_path('app/private/backups');
    }

    /** @return array{file:string, tables:int, rows:int, bytes:int} */
    public static function run(int $keep = 14): array
    {
        $dir = self::directory();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir.'/'.self::PREFIX.now()->format('Ymd-His').'.sql.gz';
        $tmp = $file.'.part';
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($tmp, 'wb6');
        gzwrite($gz, '-- TOP CRM sao lưu tự động '.now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        $rows = 0;
        foreach ($tables as [$t]) {
            $create = $pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$t`;\n$create;\n");
            $stmt = $pdo->query('SELECT * FROM `'.$t.'`');
            $batch = [];
            while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)).')';
                $rows++;
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO `$t` VALUES ".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `$t` VALUES ".implode(",\n", $batch).";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- HOÀN TẤT\n");
        gzclose($gz);
        rename($tmp, $file);   // chỉ xuất hiện khi đã ghi xong (không để lại bản dở dang)

        $result = ['file' => basename($file), 'tables' => count($tables), 'rows' => $rows, 'bytes' => filesize($file)];

        // Chỉ dọn bản sao lưu TỰ ĐỘNG (tên chứa mốc thời gian → xếp theo tên), không bao giờ xóa bản vừa tạo
        $auto = glob($dir.'/'.self::PREFIX.'*.sql.gz') ?: [];
        rsort($auto);
        foreach (array_slice($auto, $keep) as $old) {
            if ($old !== $file) {
                @unlink($old);
            }
        }

        return $result;
    }

    /** Các bản sao lưu (mới nhất trước). @return list<array{name:string, bytes:int, time:int}> */
    public static function list(): array
    {
        $files = glob(self::directory().'/*.sql.gz') ?: [];
        $out = array_map(fn ($f) => ['name' => basename($f), 'bytes' => filesize($f), 'time' => filemtime($f)], $files);
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    /** Tên tệp hợp lệ (chống đọc tệp ngoài thư mục sao lưu). */
    public static function path(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::directory().'/'.$name;

        return is_file($path) ? $path : null;
    }
}
