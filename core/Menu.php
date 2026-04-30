<?php
/**
 * NuriBoard - 메뉴 관리
 */

class Menu
{
    private static function table(): string
    {
        return DB::getPrefix() . 'menus';
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return DB::insert(self::table(), [
            'parent_id' => $data['parent_id'] ?? 0,
            'title' => $data['title'],
            'link' => $data['link'] ?? '',
            'board_id' => $data['board_id'] ?? '',
            'target' => $data['target'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        return DB::update(self::table(), $data, 'id = ?', [$id]) > 0;
    }

    public static function delete(int $id): bool
    {
        // 하위 메뉴도 삭제
        DB::delete(self::table(), 'parent_id = ?', [$id]);
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function listAll(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::table() . " ORDER BY sort_order ASC, id ASC");
    }

    /**
     * 프론트용: 활성 메뉴를 부모-자식 트리로 반환
     */
    public static function getTree(): array
    {
        $all = DB::fetchAll("SELECT * FROM " . self::table() . " WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $parents = [];
        $children = [];
        foreach ($all as $m) {
            if ($m['parent_id'] == 0) {
                $m['children'] = [];
                $parents[$m['id']] = $m;
            } else {
                $children[$m['parent_id']][] = $m;
            }
        }
        foreach ($children as $pid => $kids) {
            if (isset($parents[$pid])) {
                $parents[$pid]['children'] = $kids;
            }
        }
        return array_values($parents);
    }

    /**
     * 메뉴의 실제 URL 반환
     */
    public static function getUrl(array $menu): string
    {
        if (!empty($menu['board_id'])) {
            return Router::url("board/{$menu['board_id']}");
        }
        if (!empty($menu['link'])) {
            return $menu['link'];
        }
        return '#';
    }

    public static function getParents(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::table() . " WHERE parent_id = 0 ORDER BY sort_order ASC, id ASC");
    }
}
