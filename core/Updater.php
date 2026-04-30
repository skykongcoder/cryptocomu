<?php
/**
 * NuriBoard 자동 업데이트
 *
 * 제외 경로: config/, data/uploads/, data/cache/
 * 버전 API : https://nurikorea.com/api/version.php
 * 캐시     : 1시간 (settings 테이블)
 */
class Updater
{
    const VERSION_API    = 'https://nurikorea.com/api/version.php';
    const CACHE_SECONDS  = 3600;
    const EXCLUDE_DIRS   = ['config', 'data/uploads', 'data/cache'];

    // ──────────────────────────────────────────
    // 최신 버전 정보 조회 (캐시 1시간)
    // ──────────────────────────────────────────
    public static function fetchLatest(bool $force = false): array
    {
        $cached    = self::setting('nbu_latest_info', '');
        $checkedAt = (int)self::setting('nbu_latest_checked_at', '0');

        if (!$force && $cached && (time() - $checkedAt) < self::CACHE_SECONDS) {
            $d = json_decode($cached, true);
            if (is_array($d)) return $d;
        }

        $raw = self::curlGet(self::VERSION_API);
        if ($raw === false) return ['version' => '', 'error' => '서버에 연결할 수 없습니다.'];

        $raw = ltrim($raw, "\xEF\xBB\xBF \t\n\r");
        $data = json_decode($raw, true);
        if (!is_array($data)) return ['version' => '', 'error' => '잘못된 응답 형식입니다.'];

        self::saveSetting('nbu_latest_info', json_encode($data, JSON_UNESCAPED_UNICODE));
        self::saveSetting('nbu_latest_checked_at', (string)time());
        return $data;
    }

    // ──────────────────────────────────────────
    // ZIP 다운로드
    // ──────────────────────────────────────────
    public static function download(string $url): string
    {
        if (!preg_match('~^https?://~', $url)) {
            throw new RuntimeException('유효한 다운로드 URL이 없습니다.');
        }

        $tmpDir = NB_ROOT . '/data/tmp';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);

        $tmpZip = $tmpDir . '/nb-update-' . time() . '.zip';
        $fp     = fopen($tmpZip, 'w');
        if (!$fp) throw new RuntimeException('임시 파일을 생성할 수 없습니다.');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE            => $fp,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_USERAGENT       => 'NuriBoard-Updater/' . NB_VERSION,
        ]);
        $ok       = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode >= 400 || !file_exists($tmpZip) || filesize($tmpZip) < 100) {
            @unlink($tmpZip);
            throw new RuntimeException("다운로드 실패 (HTTP {$httpCode})");
        }

        return $tmpZip;
    }

    // ──────────────────────────────────────────
    // ZIP 압축 해제 → 임시 폴더
    // ──────────────────────────────────────────
    public static function extract(string $zipPath): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('이 서버는 ZIP 압축 해제를 지원하지 않습니다. (ZipArchive 없음)');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP 파일을 열 수 없습니다.');
        }

        $extractDir = NB_ROOT . '/data/tmp/nb-extract-' . time();
        @mkdir($extractDir, 0755, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name       = str_replace('\\', '/', $zip->getNameIndex($i));
            if (substr($name, -1) === '/') continue;

            // 보안: 경로 순회 차단
            if (strpos($name, '..') !== false) continue;

            $data = $zip->getFromIndex($i);
            if ($data === false) continue;

            $dst = $extractDir . '/' . $name;
            @mkdir(dirname($dst), 0755, true);
            file_put_contents($dst, $data);
        }
        $zip->close();

        return $extractDir;
    }

    // ──────────────────────────────────────────
    // 파일 교체 (제외 폴더 건너뜀)
    // ──────────────────────────────────────────
    public static function apply(string $extractDir): array
    {
        $applied = [];
        $errors  = [];
        self::copyRecursive($extractDir, NB_ROOT, $extractDir, $applied, $errors);

        if (!empty($errors)) {
            $msg = implode("\n", array_slice($errors, 0, 5));
            throw new RuntimeException("파일 쓰기 실패:\n{$msg}");
        }

        return $applied;
    }

    // ──────────────────────────────────────────
    // 임시 파일 정리
    // ──────────────────────────────────────────
    public static function cleanup(string $zipPath, string $extractDir): void
    {
        if ($zipPath && file_exists($zipPath)) @unlink($zipPath);
        self::rmdirRecursive($extractDir);
    }

    // ──────────────────────────────────────────
    // 내부 헬퍼
    // ──────────────────────────────────────────
    private static function copyRecursive(
        string $src, string $dst, string $base,
        array &$applied, array &$errors
    ): void {
        $items = @scandir($src);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            $relPath = ltrim(str_replace('\\', '/', substr($srcPath, strlen($base) + 1)), '/');

            // 제외 폴더 검사
            foreach (self::EXCLUDE_DIRS as $excl) {
                if (strpos($relPath, $excl) === 0) continue 2;
            }

            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) @mkdir($dstPath, 0755, true);
                self::copyRecursive($srcPath, $dstPath, $base, $applied, $errors);
            } else {
                if (!@copy($srcPath, $dstPath)) {
                    $errors[] = $relPath;
                } else {
                    $applied[] = $relPath;
                }
            }
        }
    }

    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? self::rmdirRecursive($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private static function curlGet(string $url): string|false
    {
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'NuriBoard-Updater/' . NB_VERSION,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        return $raw;
    }

    private static function setting(string $key, string $default = ''): string
    {
        if (defined('NB_SETTINGS')) {
            $s = NB_SETTINGS;
            return isset($s[$key]) ? (string)$s[$key] : $default;
        }
        if (!class_exists('DB')) return $default;
        try {
            $prefix = DB::getPrefix();
            $row    = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            return $row ? (string)$row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    private static function saveSetting(string $key, string $value): void
    {
        if (!class_exists('DB')) return;
        try {
            $prefix = DB::getPrefix();
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            else         DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
        } catch (Exception $e) {}
    }
}
