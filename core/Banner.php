<?php
/**
 * NuriBoard - 배너 관리
 * position: main(메인 상단), left(좌측 사이드), right(우측 사이드)
 */

class Banner
{
    private static function table(): string
    {
        return DB::getPrefix() . 'banners';
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return DB::insert(self::table(), [
            'position' => $data['position'],
            'title' => $data['title'] ?? '',
            'image' => $data['image'],
            'link' => $data['link'] ?? '',
            'target' => $data['target'] ?? '_blank',
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
        $banner = self::find($id);
        if ($banner) {
            $path = NB_ROOT . '/' . $banner['image'];
            if (file_exists($path)) unlink($path);
        }
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function listAll(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::table() . " ORDER BY position ASC, sort_order ASC, id ASC");
    }

    public static function listByPosition(string $position): array
    {
        return DB::fetchAll("SELECT * FROM " . self::table() . " WHERE position = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC", [$position]);
    }
}
