<?php
/**
 * NuriBoard - 파일 업로드 관리
 */

class Upload
{
    private static function table(): string
    {
        return DB::getPrefix() . 'attachments';
    }

    /**
     * 이미지를 webp로 변환 (GD 필요)
     * 변환 성공 시 원본 삭제 후 webp 경로 반환, 실패 시 원본 경로 반환
     */
    public static function convertToWebpPublic(string $filePath, string $ext): ?string
    {
        return self::convertToWebp($filePath, $ext);
    }

    private static function convertToWebp(string $filePath, string $ext): ?string
    {
        $imageExts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $imageExts)) return null;
        if (!function_exists('imagewebp')) return null;

        $image = null;
        switch ($ext) {
            case 'jpg': case 'jpeg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($filePath);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            case 'gif':
            // GIF는 애니메이션 보존을 위해 WebP 변환 건너뜀
            return null;
        }

        if (!$image) return null;

        // 큰 이미지는 최대 1920px로 다운스케일 (WebP 변환 속도 + 용량 절감)
        $maxDim = 1920;
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w > $maxDim || $h > $maxDim) {
            $ratio = $w >= $h ? $maxDim / $w : $maxDim / $h;
            $newW = (int)round($w * $ratio);
            $newH = (int)round($h * $ratio);
            $resized = imagescale($image, $newW, $newH);
            if ($resized) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $filePath);
        $result = imagewebp($image, $webpPath, 90);
        imagedestroy($image);

        if ($result && file_exists($webpPath)) {
            unlink($filePath); // 원본 삭제
            return $webpPath;
        }

        return null;
    }

    public static function upload(array $file, int $postId): ?array
    {
        $maxSize = (int)(nb_setting('upload_max_size', '10')) * 1024 * 1024;
        $allowedExt = array_map('trim', explode(',', nb_setting('upload_extensions', 'jpg,jpeg,png,gif,pdf,zip')));

        // 플러그인 필터 - null 반환 시 업로드 거부
        $file = Plugin::applyFilter('upload.before_save', $file, $postId);
        if ($file === null) return null;

        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > $maxSize) return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) return null;

        // 저장 경로: uploads/YYYY/MM/
        $dir = 'uploads/' . date('Y') . '/' . date('m');
        $fullDir = NB_ROOT . '/' . $dir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        // 고유 파일명 생성 (암호학적 난수)
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $savePath = $fullDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $savePath)) return null;

        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 1 : 0;

        // 이미지 → webp 자동 변환
        if ($isImage && $ext !== 'webp') {
            $webpPath = self::convertToWebp($savePath, $ext);
            if ($webpPath) {
                $newName = preg_replace('/\.[^.]+$/', '.webp', $newName);
                $ext = 'webp';
                $savePath = $webpPath;
            }
        }

        $fileSize = file_exists($savePath) ? filesize($savePath) : $file['size'];

        // 원본 파일명: 이미지가 webp로 변환된 경우만 확장자 변경
        $origName = $file['name'];
        if ($isImage && $ext === 'webp' && !preg_match('/\.webp$/i', $origName)) {
            $origName = preg_replace('/\.[^.]+$/', '.webp', $origName);
        }

        $id = DB::insert(self::table(), [
            'post_id' => $postId,
            'file_name' => $dir . '/' . $newName,
            'orig_name' => $origName,
            'file_size' => $fileSize,
            'file_type' => $ext,
            'is_image' => $isImage,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = [
            'id' => $id,
            'file_name' => $dir . '/' . $newName,
            'orig_name' => $origName,
            'is_image' => $isImage,
            'file_size' => $fileSize,
        ];

        Plugin::doHook('upload.after_save', $result, $postId, $savePath);

        return $result;
    }

    public static function uploadEditorImage(array $file): ?string
    {
        $maxSize = (int)(nb_setting('upload_max_size', '10')) * 1024 * 1024;
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $file = Plugin::applyFilter('upload.before_save', $file, 0);
        if ($file === null) return null;

        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > $maxSize) return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) return null;

        $dir = 'uploads/' . date('Y') . '/' . date('m');
        $fullDir = NB_ROOT . '/' . $dir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        $newName = 'img_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $savePath = $fullDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $savePath)) return null;

        // 이미지 → webp 자동 변환
        if ($ext !== 'webp') {
            $webpPath = self::convertToWebp($savePath, $ext);
            if ($webpPath) {
                $newName = preg_replace('/\.[^.]+$/', '.webp', $newName);
            }
        }

        $path = $dir . '/' . $newName;
        Plugin::doHook('upload.after_save', ['file_name' => $path, 'is_image' => 1], 0, $savePath);
        return $path;
    }

    public static function listByPost(int $postId): array
    {
        return DB::fetchAll("SELECT * FROM " . self::table() . " WHERE post_id = ? ORDER BY id ASC", [$postId]);
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function deleteByPost(int $postId): void
    {
        $files = self::listByPost($postId);
        foreach ($files as $f) {
            $path = NB_ROOT . '/' . $f['file_name'];
            if (file_exists($path)) unlink($path);
        }
        DB::delete(self::table(), 'post_id = ?', [$postId]);
    }

    public static function delete(int $id): void
    {
        $file = self::find($id);
        if ($file) {
            $path = NB_ROOT . '/' . $file['file_name'];
            if (file_exists($path)) unlink($path);
            DB::delete(self::table(), 'id = ?', [$id]);
        }
    }

    /**
     * 게시글의 첨부파일을 다른 게시글로 복사 (물리적 파일 + DB)
     */
    public static function copyByPost(int $sourcePostId, int $targetPostId): void
    {
        $files = self::listByPost($sourcePostId);
        foreach ($files as $f) {
            $sourcePath = NB_ROOT . '/' . $f['file_name'];
            if (!file_exists($sourcePath)) continue;

            // 새 파일명 생성
            $ext = pathinfo($f['file_name'], PATHINFO_EXTENSION);
            $dir = 'uploads/' . date('Y') . '/' . date('m');
            $fullDir = NB_ROOT . '/' . $dir;
            if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

            $newName = bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $fullDir . '/' . $newName;

            // 물리적 파일 복사
            if (copy($sourcePath, $targetPath)) {
                DB::insert(self::table(), [
                    'post_id' => $targetPostId,
                    'file_name' => $dir . '/' . $newName,
                    'orig_name' => $f['orig_name'],
                    'file_size' => $f['file_size'],
                    'file_type' => $f['file_type'],
                    'is_image' => $f['is_image'],
                    'download_point' => $f['download_point'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . 'MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . 'KB';
        return $bytes . 'B';
    }
}
