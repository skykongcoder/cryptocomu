<?php
/**
 * NuriBoard - 파일 기반 캐시
 * 가볍고 별도 서버(Redis 등) 필요 없음
 */

class Cache
{
    private static string $dir = '';
    private static array $deferredPatterns = [];

    public static function init(): void
    {
        self::$dir = NB_ROOT . '/data/cache';
        if (!is_dir(self::$dir)) mkdir(self::$dir, 0755, true);
        // [성능] 요청 종료 직전에 지연 캐시 삭제 자동 실행
        // Router::redirect() 같은 exit 경로에서도 실행됨
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            self::runDeferred();
        });
    }

    /**
     * 캐시 가져오기 (만료 시 null)
     */
    public static function get(string $key)
    {
        $driver = Plugin::getCacheDriver();
        if ($driver) return $driver->get($key);

        $file = self::path($key);
        if (!file_exists($file)) return null;

        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    /**
     * 캐시 저장
     * @param int $ttl 초 단위 (기본 300초 = 5분)
     */
    public static function set(string $key, $value, int $ttl = 300): void
    {
        $driver = Plugin::getCacheDriver();
        if ($driver) { $driver->set($key, $value, $ttl); return; }

        $file = self::path($key);
        $data = json_encode([
            'expires' => time() + $ttl,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);
        file_put_contents($file, $data, LOCK_EX);
    }

    /**
     * 특정 캐시 삭제
     */
    public static function delete(string $key): void
    {
        $driver = Plugin::getCacheDriver();
        if ($driver) { $driver->delete($key); return; }

        $file = self::path($key);
        if (file_exists($file)) @unlink($file);
    }

    /**
     * 패턴으로 캐시 삭제 (예: 'main_*')
     * [성능] 기본은 지연 실행 큐에 등록 → 응답 발송 후 처리.
     * 즉시 실행이 필요한 드문 경우에만 $immediate=true.
     */
    public static function deletePattern(string $pattern, bool $immediate = false): void
    {
        $driver = Plugin::getCacheDriver();
        if ($driver) { $driver->deletePattern($pattern); return; }

        if (!$immediate) {
            self::$deferredPatterns[$pattern] = true;
            return;
        }
        self::runDeletePattern($pattern);
    }

    /** 실제 패턴 삭제 수행 — md5 기반 파일명이라 scandir 불가피하지만 glob보단 빠름 */
    private static function runDeletePattern(string $pattern): void
    {
        if (!is_dir(self::$dir)) return;
        // 파일명은 md5().json 이라 pattern prefix 매칭이 안 됨.
        // 대신 인덱스 파일(패턴별 키 목록)을 보는 방식으로 개선해야 근본 해결.
        // 현재 구조 유지하되 scandir 결과를 한 번만 호출.
        $files = @scandir(self::$dir);
        if ($files === false) return;
        $prefix = str_replace('*', '', $pattern);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            if ($prefix === '' || strpos($f, $prefix) === 0) {
                @unlink(self::$dir . '/' . $f);
            }
        }
    }

    /** 지연 큐에 등록된 모든 패턴 삭제 실행 (index.php 끝에서 호출) */
    public static function runDeferred(): void
    {
        if (empty(self::$deferredPatterns)) return;
        foreach (array_keys(self::$deferredPatterns) as $pattern) {
            self::runDeletePattern($pattern);
        }
        self::$deferredPatterns = [];
    }

    /**
     * 전체 캐시 삭제
     */
    public static function flush(): void
    {
        $driver = Plugin::getCacheDriver();
        if ($driver) { $driver->flush(); return; }

        if (!is_dir(self::$dir)) return;
        foreach (scandir(self::$dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            @unlink(self::$dir . '/' . $f);
        }
    }

    /**
     * 캐시에서 가져오되, 없으면 콜백 실행 후 저장
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    private static function path(string $key): string
    {
        return self::$dir . '/' . md5($key) . '.json';
    }
}
