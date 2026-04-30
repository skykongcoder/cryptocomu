<?php
/**
 * NuriBoard - 위젯 시스템
 * 메인 페이지를 관리자에서 자유롭게 조립
 */

class Widget
{
    private static function table(): string
    {
        return DB::getPrefix() . 'widgets';
    }

    public static function find(int $id): ?array
    {
        $row = DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
        if ($row) $row['config'] = json_decode($row['config'] ?? '{}', true) ?: [];
        return $row;
    }

    public static function create(array $data): int
    {
        return DB::insert(self::table(), [
            'widget_type' => $data['widget_type'],
            'position' => $data['position'],
            'title' => $data['title'] ?? '',
            'config' => is_array($data['config'] ?? null) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : ($data['config'] ?? '{}'),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }
        return DB::update(self::table(), $data, 'id = ?', [$id]) > 0;
    }

    public static function delete(int $id): bool
    {
        $widget = self::find($id);
        if ($widget) {
            self::cleanupImages($widget);
        }
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function listAll(): array
    {
        $rows = DB::fetchAll("SELECT * FROM " . self::table() . " ORDER BY position ASC, sort_order ASC, id ASC");
        foreach ($rows as &$r) $r['config'] = json_decode($r['config'] ?? '{}', true) ?: [];
        return $rows;
    }

    public static function listByPosition(string $position): array
    {
        $rows = DB::fetchAll(
            "SELECT * FROM " . self::table() . " WHERE position = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC",
            [$position]
        );
        foreach ($rows as &$r) $r['config'] = json_decode($r['config'] ?? '{}', true) ?: [];
        return $rows;
    }

    /**
     * 단일 위젯 렌더링
     */
    public static function render(array $widget): string
    {
        $config = is_array($widget['config']) ? $widget['config'] : (json_decode($widget['config'] ?? '{}', true) ?: []);
        $type = $widget['widget_type'];

        $theme = nb_setting('theme', 'default');
        $file = NB_ROOT . "/theme/{$theme}/widgets/{$type}.php";
        if (!file_exists($file)) {
            $file = NB_ROOT . "/theme/default/widgets/{$type}.php";
        }
        if (!file_exists($file)) return '';

        ob_start();
        require $file;
        return ob_get_clean();
    }

    /**
     * 특정 위치의 모든 위젯 렌더링
     */
    public static function renderPosition(string $position): string
    {
        $widgets = self::listByPosition($position);
        $html = '';
        foreach ($widgets as $w) {
            $html .= self::render($w);
        }
        return $html;
    }

    /**
     * 위젯 종류 라벨 (한글)
     */
    public static function typeLabel(string $type): string
    {
        $labels = [
            'banner' => '이미지 배너',
            'slider' => '배너 슬라이더',
            'latest_posts' => '최근글',
            'popular_posts' => '인기글',
            'login_box' => '로그인 박스',
            'board_preview' => '게시판 미리보기',
            'html' => 'HTML 자유',
        ];
        return $labels[$type] ?? $type;
    }

    /**
     * 위치 라벨 (한글)
     */
    public static function positionLabel(string $pos): string
    {
        $labels = [
            'top' => '상단 (전체너비)',
            'left' => '좌측 사이드',
            'center' => '중앙',
            'right' => '우측 사이드',
        ];
        return $labels[$pos] ?? $pos;
    }

    /**
     * 이미지 정리
     */
    private static function cleanupImages(array $widget): void
    {
        $config = is_array($widget['config']) ? $widget['config'] : (json_decode($widget['config'] ?? '{}', true) ?: []);

        // 배너 이미지
        if (!empty($config['image'])) {
            $path = NB_ROOT . '/' . $config['image'];
            if (file_exists($path)) unlink($path);
        }

        // 슬라이더 이미지들
        if (!empty($config['slides'])) {
            foreach ($config['slides'] as $slide) {
                if (!empty($slide['image'])) {
                    $path = NB_ROOT . '/' . $slide['image'];
                    if (file_exists($path)) unlink($path);
                }
            }
        }
    }
}
