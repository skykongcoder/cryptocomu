<?php
/**
 * NuriBoard - 플러그인 시스템
 * 폴더 기반 자동 로더 + 훅/필터/라이프사이클/에셋 큐
 *
 * 사용법: plugins/{name}/plugin.php 넣으면 자동 활성화, 폴더 제거하면 비활성화
 */

class Plugin
{
    public static array $hooks = [];
    public static array $filters = [];
    private static array $loaded = [];
    private static array $headerAssets = [];
    private static array $footerAssets = [];
    private static $cacheDriver = null;

    /**
     * 플러그인 자동 로드 (plugins/ 폴더 스캔)
     * 활성화된 플러그인만 require. 신규 폴더는 DB에 비활성 상태로 자동 등록.
     * [성능] 활성화된 플러그인 경로 목록을 캐시하여 매 요청 scandir + DB 조회를 스킵.
     */
    public static function loadAll(): void
    {
        $dir = NB_ROOT . '/plugins';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); return; }

        // 캐시 우선 조회 (1시간 유효)
        $cacheFile = NB_ROOT . '/data/cache/plugins_active.php';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $cached = @include $cacheFile;
            if (is_array($cached) && isset($cached['dir_mtime'])) {
                // plugins 폴더가 변경되지 않았다면 캐시 사용
                $curMtime = @filemtime($dir);
                if ($curMtime && $curMtime === $cached['dir_mtime']) {
                    foreach ($cached['files'] as $name => $info) {
                        require_once $info['file'];
                        self::$loaded[$name] = $info['meta'];
                    }
                    return;
                }
            }
        }

        $enabledMap = self::getEnabledMap();
        $cacheData = ['dir_mtime' => @filemtime($dir), 'files' => []];

        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') continue;
            $pluginDir = $dir . '/' . $name;
            $mainFile = $pluginDir . '/plugin.php';
            if (!is_dir($pluginDir) || !file_exists($mainFile)) continue;

            // 신규 폴더는 비활성(0) 상태로 DB 등록 - 수동 활성화 필요
            if (!array_key_exists($name, $enabledMap)) {
                self::registerNew($name);
                $enabledMap[$name] = false;
            }

            if (empty($enabledMap[$name])) continue;

            $meta = ['name' => $name, 'description' => '', 'version' => '1.0', 'author' => ''];
            $jsonFile = $pluginDir . '/plugin.json';
            if (file_exists($jsonFile)) {
                $meta = array_merge($meta, json_decode(file_get_contents($jsonFile), true) ?: []);
            }
            require_once $mainFile;
            self::$loaded[$name] = $meta;
            $cacheData['files'][$name] = ['file' => $mainFile, 'meta' => $meta];
        }

        // 캐시 저장
        @file_put_contents($cacheFile, '<?php return ' . var_export($cacheData, true) . ';');
    }

    /** 플러그인 활성화/비활성화 시 캐시 무효화 */
    public static function invalidateCache(): void
    {
        $cacheFile = NB_ROOT . '/data/cache/plugins_active.php';
        if (file_exists($cacheFile)) @unlink($cacheFile);
    }

    /**
     * DB에서 플러그인 활성화 상태 조회 (한번에)
     */
    private static function getEnabledMap(): array
    {
        try {
            $prefix = DB::getPrefix();
            $rows = DB::fetchAll("SELECT setting_key, setting_value FROM {$prefix}settings WHERE setting_key LIKE 'plugin_%_enabled'");
        } catch (Exception $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            if (preg_match('/^plugin_(.+)_enabled$/', $row['setting_key'], $m)) {
                $map[$m[1]] = $row['setting_value'] === '1';
            }
        }
        return $map;
    }

    /**
     * 신규 플러그인을 DB에 비활성 상태로 등록
     */
    private static function registerNew(string $name): void
    {
        try {
            $prefix = DB::getPrefix();
            DB::insert("{$prefix}settings", [
                'setting_key' => "plugin_{$name}_enabled",
                'setting_value' => '0',
            ]);
        } catch (Exception $e) {
            // 동시 요청 race 등은 무시
        }
    }

    /**
     * 훅 등록 (액션)
     */
    public static function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        self::$hooks[$hook][] = ['callback' => $callback, 'priority' => $priority];
    }

    /**
     * 훅 실행
     */
    public static function doHook(string $hook, ...$args): void
    {
        if (empty(self::$hooks[$hook])) return;
        usort(self::$hooks[$hook], fn($a, $b) => $a['priority'] - $b['priority']);
        foreach (self::$hooks[$hook] as $h) {
            call_user_func_array($h['callback'], $args);
        }
    }

    /**
     * 필터 등록
     */
    public static function addFilter(string $filter, callable $callback, int $priority = 10): void
    {
        self::$filters[$filter][] = ['callback' => $callback, 'priority' => $priority];
    }

    /**
     * 필터 적용
     */
    public static function applyFilter(string $filter, $value, ...$args)
    {
        if (empty(self::$filters[$filter])) return $value;
        usort(self::$filters[$filter], fn($a, $b) => $a['priority'] - $b['priority']);
        foreach (self::$filters[$filter] as $f) {
            $value = call_user_func($f['callback'], $value, ...$args);
        }
        return $value;
    }

    /**
     * 헤더(<head>) 에셋 큐 - <style>, <link>, <script> 등
     */
    public static function queueHeaderAsset(string $html): void
    {
        self::$headerAssets[] = $html;
    }

    /**
     * 푸터(</body> 직전) 에셋 큐
     */
    public static function queueFooterAsset(string $html): void
    {
        self::$footerAssets[] = $html;
    }

    public static function renderHeaderAssets(): string
    {
        $html = implode("\n", self::$headerAssets);
        return self::applyFilter('header.assets', $html);
    }

    public static function renderFooterAssets(): string
    {
        $html = implode("\n", self::$footerAssets);
        return self::applyFilter('footer.assets', $html);
    }

    /**
     * 캐시 드라이버 등록 (플러그인이 Redis/Memcached 등으로 교체 가능)
     * 드라이버는 get/set/delete/deletePattern/flush 메서드를 가진 객체
     */
    public static function setCacheDriver($driver): void
    {
        self::$cacheDriver = $driver;
    }

    public static function getCacheDriver()
    {
        return self::$cacheDriver;
    }

    /**
     * 로드된 플러그인 목록
     */
    public static function getLoaded(): array
    {
        return self::$loaded;
    }

    /**
     * 설치된 플러그인 목록 (plugins/ 폴더 스캔)
     */
    public static function getAll(): array
    {
        $dir = NB_ROOT . '/plugins';
        if (!is_dir($dir)) return [];

        $enabledMap = self::getEnabledMap();
        $list = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') continue;
            $pluginDir = $dir . '/' . $name;
            if (!is_dir($pluginDir) || !file_exists($pluginDir . '/plugin.php')) continue;
            $meta = ['name' => $name, 'description' => '', 'version' => '1.0', 'author' => ''];
            $jsonFile = $pluginDir . '/plugin.json';
            if (file_exists($jsonFile)) {
                $meta = array_merge($meta, json_decode(file_get_contents($jsonFile), true) ?: []);
            }
            $meta['enabled'] = !empty($enabledMap[$name]);
            $meta['dir_name'] = $name;
            $list[] = $meta;
        }
        return $list;
    }
}
