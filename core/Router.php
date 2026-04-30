<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * Router.php - URL 라우팅
 */

class Router
{
    private static array $routes = [];
    private static string $basePath = '';

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function get(string $pattern, callable $handler): void
    {
        self::$routes[] = ['GET', $pattern, $handler];
    }

    public static function post(string $pattern, callable $handler): void
    {
        self::$routes[] = ['POST', $pattern, $handler];
    }

    public static function any(string $pattern, callable $handler): void
    {
        self::$routes[] = ['GET', $pattern, $handler];
        self::$routes[] = ['POST', $pattern, $handler];
    }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // basePath 제거
        if (self::$basePath && str_starts_with($uri, self::$basePath)) {
            $uri = substr($uri, strlen(self::$basePath));
        }
        $uri = '/' . trim($uri, '/');

        foreach (self::$routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) continue;

            $regex = self::patternToRegex($pattern);
            if (preg_match($regex, $uri, $matches)) {
                // 이름이 있는 매칭만 추출
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        // 404
        http_response_code(404);
        self::loadTheme('error/404');
    }

    private static function patternToRegex(string $pattern): string
    {
        // {param} -> (?P<param>[^/]+)
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    public static function loadTheme(string $view, array $data = []): void
    {
        $config = file_exists(NB_ROOT . '/config/config.php')
            ? require NB_ROOT . '/config/config.php'
            : [];
        $theme = 'default';

        // 설정에서 테마 가져오기 시도
        if (!empty($config['db_host'])) {
            try {
                require_once NB_ROOT . '/core/DB.php';
                $row = DB::fetch("SELECT setting_value FROM " . DB::getPrefix() . "settings WHERE setting_key = 'theme'");
                if ($row) $theme = $row['setting_value'];
            } catch (Exception $e) {
                // 무시
            }
        }

        $viewFile = NB_ROOT . "/theme/{$theme}/{$view}.php";
        if (!file_exists($viewFile)) {
            $viewFile = NB_ROOT . "/theme/default/{$view}.php";
        }
        $viewFile = Plugin::applyFilter('theme_view_file', $viewFile, $view, $theme);

        if (file_exists($viewFile)) {
            Plugin::doHook('router.before_render', $view, $data, $viewFile);
            unset($view); // 파라미터가 템플릿 스코프로 새는 것 방지
            extract($data);
            require $viewFile;
            Plugin::doHook('router.after_render');
        } else {
            echo "<h1>404</h1><p>페이지를 찾을 수 없습니다.</p>";
        }
    }

    public static function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    public static function url(string $path = ''): string
    {
        return self::$basePath . '/' . ltrim($path, '/');
    }
}
