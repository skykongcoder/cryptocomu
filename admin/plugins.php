<?php
/**
 * NuriBoard 관리자 - 플러그인 관리
 * 마켓: 전용 테이블 (nb_market_plugins) 사용, 게시판 의존 없음
 */

/**
 * 플러그인 이름으로 그라데이션 색상 쌍 생성 (썸네일 없을 때 플레이스홀더용)
 */
function nb_plugin_gradient(string $name): array {
    $palette = [
        ['#6366f1','#8b5cf6'], ['#0ea5e9','#6366f1'], ['#10b981','#0ea5e9'],
        ['#f59e0b','#ef4444'], ['#ec4899','#8b5cf6'], ['#14b8a6','#10b981'],
        ['#f43f5e','#f59e0b'], ['#8b5cf6','#ec4899'], ['#3b82f6','#06b6d4'],
    ];
    $hash = 0;
    for ($i = 0; $i < mb_strlen($name); $i++) {
        $hash = ($hash * 31 + mb_ord(mb_substr($name, $i, 1))) & 0xffffffff;
    }
    return $palette[abs($hash) % count($palette)];
}
function nb_plugin_placeholder_svg(): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.28a2.5 2.5 0 0 0-2.5-2.5h-3V3a2 2 0 0 0-4 0v1.78H7a2 2 0 0 0-2 2v3.72H3a2 2 0 0 0 0 4h2V18a2 2 0 0 0 2 2h3.22v-2a2.5 2.5 0 0 1 5 0v2H18a2.5 2.5 0 0 0 2.5-2.5v-3.22H22a2 2 0 0 0 0-4h-1.5V7.28z"/></svg>';
}

// 테이블 자동 생성
try {
    DB::query("SELECT 1 FROM {$prefix}market_plugins LIMIT 1");
} catch (Exception $e) {
    DB::getInstance()->exec("CREATE TABLE IF NOT EXISTS {$prefix}market_plugins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        version VARCHAR(20) DEFAULT '1.0',
        author VARCHAR(100) DEFAULT '',
        thumbnail VARCHAR(500) DEFAULT '',
        zip_file VARCHAR(500) NOT NULL,
        zip_orig_name VARCHAR(200) DEFAULT '',
        zip_size INT DEFAULT 0,
        price INT DEFAULT 0,
        downloads INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
// category 컬럼 추가
try { DB::getInstance()->exec("ALTER TABLE {$prefix}market_plugins ADD COLUMN category VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
try { DB::getInstance()->exec("ALTER TABLE {$prefix}market_plugins ADD COLUMN access_tier VARCHAR(10) DEFAULT 'all'"); } catch (Exception $e) {}

// ============================================================
// AJAX 처리
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $pluginName = trim($_POST['plugin'] ?? '');

    // --- 활성화/비활성화 ---
    if ($action === 'plugin_toggle' && $pluginName) {
        // 필수 플러그인은 비활성화 불가
        $protected_plugins = ['nurikorea-announcements', 'nuriboard-updater'];
        $newVal = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
        if (in_array($pluginName, $protected_plugins, true) && $newVal === '0') {
            echo json_encode(['success' => false, 'error' => '이 플러그인은 시스템 필수 플러그인이라 비활성화할 수 없습니다.']); exit;
        }
        $key = "plugin_{$pluginName}_enabled";
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            DB::update("{$prefix}settings", ['setting_value' => $newVal], "setting_key = ?", [$key]);
        } else {
            DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $newVal]);
        }
        Plugin::invalidateCache();
        AdminLog::write('plugin_toggle', 'plugin', 0, "{$pluginName}: " . ($newVal === '1' ? '활성화' : '비활성화'));
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ZIP 설치 (로컬 업로드 또는 마켓 URL 서버사이드 다운로드) ---
    if ($action === 'plugin_install') {
        $zipTmpPath = '';
        $zipOrigName = '';
        $cleanupTmp = false;

        // (1) URL로 설치 - 서버사이드 다운로드 (CORS 우회)
        $downloadUrl = trim($_POST['url'] ?? '');
        if ($downloadUrl) {
            if (!preg_match('#^https?://#i', $downloadUrl)) {
                echo json_encode(['success' => false, 'message' => '잘못된 URL입니다.']); exit;
            }
            // 누리코리아 라이선스가 있으면 URL에 쿼리 파라미터로 부착
            $nkConfigPath = NB_ROOT . '/config/nurikorea.php';
            if (file_exists($nkConfigPath)) {
                $nk = @include $nkConfigPath;
                if (is_array($nk) && !empty($nk['license_key']) && !empty($nk['domain'])) {
                    $sep = (strpos($downloadUrl, '?') !== false) ? '&' : '?';
                    $downloadUrl .= $sep
                        . 'license_key=' . urlencode($nk['license_key'])
                        . '&domain='      . urlencode($nk['domain']);
                }
            }
            $ch = curl_init($downloadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => 'NuriBoard/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $zipData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($zipData === false || $httpCode >= 400) {
                $errJson = json_decode((string)$zipData, true);
                if (is_array($errJson) && !empty($errJson['message'])) {
                    echo json_encode(['success' => false, 'message' => $errJson['message']]); exit;
                }
                echo json_encode(['success' => false, 'message' => '다운로드 실패: HTTP ' . $httpCode . ' ' . $curlErr]); exit;
            }
            if (strlen($zipData) < 4 || substr($zipData, 0, 2) !== 'PK') {
                $errJson = json_decode((string)$zipData, true);
                if (is_array($errJson) && !empty($errJson['message'])) {
                    echo json_encode(['success' => false, 'message' => $errJson['message']]); exit;
                }
                echo json_encode(['success' => false, 'message' => 'ZIP 파일이 아닙니다. 응답: ' . substr($zipData, 0, 200)]); exit;
            }
            $zipTmpPath = tempnam(sys_get_temp_dir(), 'nbplug_') . '.zip';
            file_put_contents($zipTmpPath, $zipData);
            $zipOrigName = basename(parse_url($downloadUrl, PHP_URL_PATH)) ?: 'plugin.zip';
            if (!preg_match('/\.zip$/i', $zipOrigName)) $zipOrigName .= '.zip';
            $cleanupTmp = true;
        }
        // (2) 파일 업로드로 설치
        else {
            if (empty($_FILES['plugin_zip']) || $_FILES['plugin_zip']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'ZIP 파일을 선택하세요.']); exit;
            }
            $ext = strtolower(pathinfo($_FILES['plugin_zip']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                echo json_encode(['success' => false, 'message' => 'ZIP 파일만 가능합니다.']); exit;
            }
            $zipTmpPath = $_FILES['plugin_zip']['tmp_name'];
            $zipOrigName = $_FILES['plugin_zip']['name'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipTmpPath) !== true) {
            if ($cleanupTmp) @unlink($zipTmpPath);
            echo json_encode(['success' => false, 'message' => 'ZIP을 열 수 없습니다.']); exit;
        }

        $destPath = NB_ROOT . '/plugins/';

        // plugin.php 위치 찾기 (Windows ZIP 백슬래시 호환)
        $pluginPhpPath = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $normalEntry = str_replace('\\', '/', $zip->getNameIndex($i));
            if (basename($normalEntry) === 'plugin.php') { $pluginPhpPath = $normalEntry; break; }
        }
        if (!$pluginPhpPath) {
            $zip->close();
            if ($cleanupTmp) @unlink($zipTmpPath);
            echo json_encode(['success' => false, 'message' => 'plugin.php가 없습니다.']); exit;
        }

        // ZIP 파일을 경로 정규화하며 수동 추출 (Windows 백슬래시 대응)
        if (!function_exists('nb_zip_extract_normalized')) {
            function nb_zip_extract_normalized(ZipArchive $zip, string $dest): void {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = str_replace('\\', '/', $zip->getNameIndex($i));
                    if (substr($entry, -1) === '/') {
                        @mkdir($dest . $entry, 0755, true);
                    } else {
                        $dir = dirname($dest . $entry);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $data = $zip->getFromIndex($i);
                        if ($data !== false) file_put_contents($dest . $entry, $data);
                    }
                }
            }
        }

        $parts = explode('/', $pluginPhpPath);
        $pluginDir = '';
        if (count($parts) === 1) {
            $pluginDir = pathinfo($zipOrigName, PATHINFO_FILENAME);
            $pluginDir = preg_replace('/[^a-zA-Z0-9_-]/', '-', $pluginDir);
            $pluginDir = trim($pluginDir, '-') ?: ('plugin-' . time());
            $target = $destPath . $pluginDir . '/';
            if (!is_dir($target)) mkdir($target, 0755, true);
            nb_zip_extract_normalized($zip, $target);
        } elseif (count($parts) === 2) {
            $pluginDir = $parts[0];
            nb_zip_extract_normalized($zip, $destPath);
        } else {
            nb_zip_extract_normalized($zip, $destPath);
            $actualDir = dirname($pluginPhpPath);
            $pluginDir = basename($actualDir);
            $srcPath = $destPath . $actualDir;
            $dstPath = $destPath . $pluginDir;
            if ($srcPath !== $dstPath && is_dir($srcPath) && !is_dir($dstPath)) {
                rename($srcPath, $dstPath);
            }
            @rmdir($destPath . $parts[0]);
        }
        $zip->close();

        if ($cleanupTmp) @unlink($zipTmpPath);
        if (!$pluginDir || !file_exists($destPath . $pluginDir . '/plugin.php')) {
            echo json_encode(['success' => false, 'message' => 'plugin.php를 찾을 수 없습니다.']); exit;
        }

        // 마켓 이름으로 plugin.json의 name 필드 덮어쓰기 (설치 탭 ↔ 마켓 이름 통일)
        $marketName = trim($_POST['market_name'] ?? '');
        if ($marketName !== '') {
            $pluginJsonPath = $destPath . $pluginDir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $pluginJson = json_decode(file_get_contents($pluginJsonPath), true);
                if (!is_array($pluginJson)) $pluginJson = [];
                $pluginJson['name'] = $marketName;
                file_put_contents(
                    $pluginJsonPath,
                    json_encode($pluginJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            } else {
                // plugin.json이 없으면 새로 생성
                file_put_contents(
                    $pluginJsonPath,
                    json_encode(['name' => $marketName, 'version' => '1.0'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            }
        }

        // 자동 활성화 대상 플러그인 (설치 즉시 enabled=1)
        $auto_activate = ['nurikorea-announcements', 'nuriboard-updater'];
        if (in_array($pluginDir, $auto_activate, true)) {
            $prefix = DB::getPrefix();
            $key = "plugin_{$pluginDir}_enabled";
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => '1'], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => '1']);
            }
        }

        Plugin::invalidateCache();
        AdminLog::write('plugin_install', 'plugin', 0, $pluginDir);
        $installMsg = in_array($pluginDir, $auto_activate, true)
            ? "'{$pluginDir}' 설치 및 자동 활성화 완료."
            : "'{$pluginDir}' 설치 완료. 활성화해주세요.";
        echo json_encode(['success' => true, 'message' => $installMsg]); exit;
    }

    // --- 마켓 등록 (nuribd.com 전용, 전용 테이블) ---
    if ($action === 'market_upload') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $version = trim($_POST['version'] ?? '1.0');
        if (!$name) { echo json_encode(['success' => false, 'message' => '이름을 입력하세요']); exit; }

        // uploads/market/ 폴더
        $marketDir = 'uploads/market';
        $fullDir = NB_ROOT . '/' . $marketDir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        // ZIP 저장
        $zipPath = '';
        $zipOrigName = '';
        $zipSize = 0;
        if (!empty($_FILES['plugin_zip']) && $_FILES['plugin_zip']['error'] === UPLOAD_ERR_OK) {
            $zipName = bin2hex(random_bytes(16)) . '.zip';
            $zipSavePath = $fullDir . '/' . $zipName;
            move_uploaded_file($_FILES['plugin_zip']['tmp_name'], $zipSavePath);
            $zipPath = $marketDir . '/' . $zipName;
            $zipOrigName = $_FILES['plugin_zip']['name'];
            $zipSize = filesize($zipSavePath);

            // ZIP에서 첫 이미지 자동 추출 (썸네일)
            $thumbPath = '';
            $tz = new ZipArchive();
            if ($tz->open($zipSavePath) === true) {
                for ($i = 0; $i < $tz->numFiles; $i++) {
                    $entry = $tz->getNameIndex($i);
                    $imgExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                    if (!in_array($imgExt, ['png','jpg','jpeg','gif','webp'])) continue;
                    $imgData = $tz->getFromIndex($i);
                    if (!$imgData) continue;
                    $thumbName = bin2hex(random_bytes(16)) . '.' . $imgExt;
                    file_put_contents($fullDir . '/' . $thumbName, $imgData);
                    $thumbPath = $marketDir . '/' . $thumbName;
                    break;
                }
                $tz->close();
            }
        }
        if (!$zipPath) { echo json_encode(['success' => false, 'message' => 'ZIP 파일을 업로드하세요']); exit; }

        // 별도 썸네일 업로드 (우선)
        if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['png','jpg','jpeg','gif','webp'])) {
                $thumbName = bin2hex(random_bytes(16)) . '.' . $thumbExt;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $fullDir . '/' . $thumbName);
                $thumbPath = $marketDir . '/' . $thumbName;
            }
        }

        $category = trim($_POST['category'] ?? '');

        DB::insert("{$prefix}market_plugins", [
            'name' => $name,
            'description' => $desc,
            'version' => $version,
            'author' => Auth::user()['nickname'] ?? 'admin',
            'thumbnail' => $thumbPath,
            'zip_file' => $zipPath,
            'zip_orig_name' => $zipOrigName,
            'zip_size' => $zipSize,
            'price' => $price,
            'category' => $category,
        ]);

        AdminLog::write('market_upload', 'plugin', 0, $name);
        echo json_encode(['success' => true]); exit;
    }

    // --- 마켓 플러그인 수정 ---
    if ($action === 'market_edit') {
        $id = (int)($_POST['id'] ?? 0);
        $mp = DB::fetch("SELECT * FROM {$prefix}market_plugins WHERE id = ?", [$id]);
        if (!$mp) { echo json_encode(['success' => false, 'message' => '플러그인을 찾을 수 없습니다']); exit; }

        $updates = [];
        $name = trim($_POST['name'] ?? '');
        if ($name) $updates['name'] = $name;
        $desc = trim($_POST['description'] ?? '');
        if ($desc) $updates['description'] = $desc;
        $version = trim($_POST['version'] ?? '');
        if ($version) $updates['version'] = $version;
        $price = $_POST['price'] ?? null;
        if ($price !== null) $updates['price'] = (int)$price;
        if (isset($_POST['category'])) $updates['category'] = trim($_POST['category']);

        $marketDir = 'uploads/market';
        $fullDir = NB_ROOT . '/' . $marketDir;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        // ZIP 교체
        if (!empty($_FILES['plugin_zip']) && $_FILES['plugin_zip']['error'] === UPLOAD_ERR_OK) {
            // 기존 ZIP 삭제
            if ($mp['zip_file'] && file_exists(NB_ROOT . '/' . $mp['zip_file'])) @unlink(NB_ROOT . '/' . $mp['zip_file']);
            $zipName = bin2hex(random_bytes(16)) . '.zip';
            move_uploaded_file($_FILES['plugin_zip']['tmp_name'], $fullDir . '/' . $zipName);
            $updates['zip_file'] = $marketDir . '/' . $zipName;
            $updates['zip_orig_name'] = $_FILES['plugin_zip']['name'];
            $updates['zip_size'] = filesize($fullDir . '/' . $zipName);

            // ZIP에서 이미지 추출 (썸네일 자동 교체)
            $tz = new ZipArchive();
            if ($tz->open($fullDir . '/' . $zipName) === true) {
                for ($i = 0; $i < $tz->numFiles; $i++) {
                    $entry = $tz->getNameIndex($i);
                    $imgExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                    if (!in_array($imgExt, ['png','jpg','jpeg','gif','webp'])) continue;
                    $imgData = $tz->getFromIndex($i);
                    if (!$imgData) continue;
                    if ($mp['thumbnail'] && file_exists(NB_ROOT . '/' . $mp['thumbnail'])) @unlink(NB_ROOT . '/' . $mp['thumbnail']);
                    $thumbName = bin2hex(random_bytes(16)) . '.' . $imgExt;
                    file_put_contents($fullDir . '/' . $thumbName, $imgData);
                    $updates['thumbnail'] = $marketDir . '/' . $thumbName;
                    break;
                }
                $tz->close();
            }
        }

        // 별도 썸네일 교체
        if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['png','jpg','jpeg','gif','webp'])) {
                if ($mp['thumbnail'] && file_exists(NB_ROOT . '/' . $mp['thumbnail'])) @unlink(NB_ROOT . '/' . $mp['thumbnail']);
                $thumbName = bin2hex(random_bytes(16)) . '.' . $thumbExt;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $fullDir . '/' . $thumbName);
                $updates['thumbnail'] = $marketDir . '/' . $thumbName;
            }
        }

        if (!empty($updates)) {
            DB::update("{$prefix}market_plugins", $updates, "id = ?", [$id]);
        }
        echo json_encode(['success' => true]); exit;
    }

    // --- 마켓 플러그인 삭제 ---
    if ($action === 'market_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $mp = DB::fetch("SELECT * FROM {$prefix}market_plugins WHERE id = ?", [$id]);
            if ($mp) {
                if ($mp['zip_file'] && file_exists(NB_ROOT . '/' . $mp['zip_file'])) @unlink(NB_ROOT . '/' . $mp['zip_file']);
                if ($mp['thumbnail'] && file_exists(NB_ROOT . '/' . $mp['thumbnail'])) @unlink(NB_ROOT . '/' . $mp['thumbnail']);
                DB::delete("{$prefix}market_plugins", "id = ?", [$id]);
                AdminLog::write('market_delete', 'plugin', $id, $mp['name']);
            }
        }
        echo json_encode(['success' => true]); exit;
    }

    // --- 플러그인 삭제 (로컬) ---
    if ($action === 'plugin_delete' && $pluginName) {
        // 필수 플러그인 보호 (누리코리아 알림 등 시스템 필수 플러그인은 삭제 불가)
        $protected_plugins = ['nurikorea-announcements', 'nuriboard-updater'];
        if (in_array($pluginName, $protected_plugins, true)) {
            echo json_encode(['success' => false, 'error' => '이 플러그인은 시스템 필수 플러그인이라 삭제할 수 없습니다.']); exit;
        }
        $dir = NB_ROOT . '/plugins/' . basename($pluginName);
        if (is_dir($dir)) {
            DB::update("{$prefix}settings", ['setting_value' => '0'], "setting_key = ?", ["plugin_{$pluginName}_enabled"]);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) { $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath()); }
            rmdir($dir);
            Plugin::invalidateCache();
            AdminLog::write('plugin_delete', 'plugin', 0, $pluginName);
        }
        echo json_encode(['success' => true]); exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// ============================================================
// 페이지 렌더링
// ============================================================
$plugins = Plugin::getAll();
adminHeader('plugins');

// 플러그인 설정 페이지
$settingsPlugin = $_GET['settings'] ?? '';
if ($settingsPlugin):
    $spMeta = null;
    foreach ($plugins as $_p) { if ($_p['dir_name'] === $settingsPlugin) { $spMeta = $_p; break; } }
?>
<div class="page-header">
    <h1><?= nb_e($spMeta['name'] ?? $settingsPlugin) ?> 설정</h1>
    <a href="?page=plugins" class="btn">← 플러그인 목록</a>
</div>
<div class="card">
    <div class="card-body">
        <?php
        $settingsLoaded = false;
        // 방법1: 플러그인 폴더에 settings.php 파일
        $settingsFile = NB_ROOT . '/plugins/' . basename($settingsPlugin) . '/settings.php';
        if (file_exists($settingsFile)) {
            include $settingsFile;
            $settingsLoaded = true;
        }
        // 방법2: 훅 방식
        if (!$settingsLoaded) {
            Plugin::doHook("plugin.settings.{$settingsPlugin}");
            if (!empty(Plugin::$hooks["plugin.settings.{$settingsPlugin}"] ?? [])) {
                $settingsLoaded = true;
            }
        }
        if (!$settingsLoaded): ?>
        <p style="text-align:center;padding:20px;color:#94a3b8">이 플러그인은 설정 페이지가 없습니다.</p>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1>플러그인</h1>
    <div style="display:flex;gap:8px">
        <button class="btn <?= empty($_GET['tab']) || $_GET['tab'] === 'installed' ? 'btn-primary' : '' ?>" onclick="location.href='?page=plugins&tab=installed'">설치됨</button>
        <button class="btn <?= ($_GET['tab'] ?? '') === 'market' ? 'btn-primary' : '' ?>" onclick="location.href='?page=plugins&tab=market'">마켓</button>
    </div>
</div>

<?php if (($_GET['tab'] ?? '') === 'market'): ?>
<!-- ============================================================ -->
<!-- 마켓 탭 -->
<!-- ============================================================ -->
<?php
$isMarketOwner = (strpos(nb_setting('site_url'), 'nuribd.com') !== false);

if ($isMarketOwner):
    // nuribd.com: DB에서 직접 조회
    $marketPlugins = DB::fetchAll("SELECT * FROM {$prefix}market_plugins ORDER BY id DESC");

    // 현재 관리자가 구매한 플러그인 목록 (plugin_id => purchase_id)
    $myPurchases = [];
    try {
        $pRows = DB::fetchAll(
            "SELECT plugin_id, id AS purchase_id FROM {$prefix}market_purchases WHERE member_id = ? AND status = 'paid'",
            [Auth::id()]
        );
        foreach ($pRows as $pr) {
            $myPurchases[(int)$pr['plugin_id']] = (int)$pr['purchase_id'];
        }
    } catch (Exception $e) { /* 테이블 없을 수 있음 */ }
?>
<div style="margin-bottom:16px;display:flex;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap">
    <div style="display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap">
        <button class="btn mp-tab-btn active" data-type="all">전체</button>
        <button class="btn mp-tab-btn" data-type="free">무료</button>
        <button class="btn mp-tab-btn" data-type="paid">유료</button>
        <span class="mp-sep"></span>
        <button class="btn mp-status-btn active" data-status="all">전체 상태</button>
        <button class="btn mp-status-btn" data-status="not">미설치</button>
        <button class="btn mp-status-btn" data-status="installed">설치됨</button>
        <input type="text" id="marketSearch" placeholder="플러그인 검색..."
               style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#334155">
    </div>
    <button class="btn btn-primary" onclick="openModal('marketUploadModal')">+ 플러그인 등록</button>
</div>
<div style="margin-bottom:16px;display:flex;gap:6px;flex-wrap:wrap">
    <button class="btn btn-sm mp-cat-btn active" data-cat="">전체</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="seo">SEO</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="security">보안</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="community">커뮤니티</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="content">콘텐츠</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="design">디자인</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="advertising">광고/수익</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="notification">알림/연동</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="management">관리</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="media">미디어</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="form">폼/설문</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="shopping">쇼핑몰</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="utility">유틸리티</button>
</div>

<?php if (empty($marketPlugins)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px">
    <p style="font-size:16px;font-weight:600;margin-bottom:8px">등록된 플러그인이 없습니다.</p>
    <p style="font-size:13px;color:#94a3b8">플러그인을 등록하면 모든 누리보드 사이트에서 볼 수 있습니다.</p>
</div></div>
<?php else: ?>
<div class="mp-grid" id="marketGrid">
<?php foreach ($marketPlugins as $mp): ?>
<?php
    $mpInstalled = false;
    $mpNameLower = mb_strtolower($mp['name']);
    $mpZipName = pathinfo($mp['zip_orig_name'] ?? '', PATHINFO_FILENAME);
    foreach ($plugins as $_ip) {
        $ipName = mb_strtolower($_ip['name'] ?? '');
        $ipDir = mb_strtolower($_ip['dir_name'] ?? '');
        if ($ipName === $mpNameLower || $ipDir === $mpNameLower || $ipDir === mb_strtolower($mpZipName) || $_ip['name'] === $mp['name'] || $_ip['dir_name'] === $mp['name']) {
            $mpInstalled = true; break;
        }
    }
?>
<div class="mp-card" data-category="<?= nb_e($mp['category'] ?? '') ?>" data-price="<?= (int)$mp['price'] ?>" data-installed="<?= $mpInstalled ? '1' : '0' ?>">
    <?php if ($mp['thumbnail']): ?>
    <div class="mp-thumb"><img src="../<?= nb_e($mp['thumbnail']) ?>" alt=""></div>
    <?php else: $_g = nb_plugin_gradient($mp['name']); ?>
    <div class="mp-thumb no-img" style="--pbg1:<?= $_g[0] ?>;--pbg2:<?= $_g[1] ?>">
        <?= nb_plugin_placeholder_svg() ?>
        <span class="mp-thumb-letter"><?= nb_e(mb_strtoupper(mb_substr($mp['name'], 0, 2))) ?></span>
    </div>
    <?php endif; ?>
    <div class="mp-body">
        <div class="mp-name"><?= nb_e($mp['name']) ?></div>
        <div class="mp-desc"><?= nb_e(mb_strimwidth($mp['description'], 0, 60, '...')) ?></div>
        <div class="mp-meta">
            <span>v<?= nb_e($mp['version']) ?> · <?= number_format($mp['downloads']) ?> DL</span>
            <?= $mp['price'] > 0 ? '<span class="mp-price paid">'.number_format($mp['price']).'원</span>' : '<span class="mp-price free">무료</span>' ?>
        </div>
    </div>
    <div class="mp-actions">
        <?php if ($mpInstalled): ?>
        <span class="mp-installed">설치됨 ✓</span>
        <?php elseif ($mp['price'] == 0): ?>
        <button class="btn btn-sm btn-primary" onclick="installFromMarket('<?= nb_e(nb_setting('site_url')) ?>/api/v1/market/download/<?= $mp['id'] ?>','<?= nb_e($mp['name']) ?>')">설치</button>
        <?php elseif (isset($myPurchases[(int)$mp['id']])): ?>
        <button class="btn btn-sm btn-primary" onclick="installFromMarket('<?= nb_url('market/download/' . $myPurchases[(int)$mp['id']]) ?>','<?= nb_e($mp['name']) ?>')">설치</button>
        <?php else: ?>
        <a href="<?= nb_url('market/buy/' . $mp['id']) ?>" target="_blank" class="btn btn-sm" style="background:#f59e0b;color:#fff;border-color:#f59e0b">구매</a>
        <?php endif; ?>
        <button class="btn btn-sm mp-detail-btn" data-plugin="<?= htmlspecialchars(json_encode(['name'=>$mp['name'],'desc'=>$mp['description']??'','version'=>$mp['version'],'author'=>$mp['author']??'admin','downloads'=>(int)$mp['downloads'],'price'=>(int)$mp['price'],'thumb'=>$mp['thumbnail']?'../'.$mp['thumbnail']:''], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">상세</button>
        <button class="btn btn-sm mp-edit-btn" data-edit="<?= htmlspecialchars(json_encode(['id'=>(int)$mp['id'],'name'=>$mp['name'],'desc'=>$mp['description']??'','version'=>$mp['version'],'price'=>(int)$mp['price'],'category'=>$mp['category']??''], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">수정</button>
        <button class="btn btn-sm btn-danger" onclick="if(confirm('삭제할까요?')){var d=new FormData();d.append('action','market_delete');d.append('id',<?= $mp['id'] ?>);ajaxPost(d).then(function(r){if(r.success)location.reload()})}">삭제</button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- 일반 사이트: API로 마켓 조회 -->

<!-- ===== 마켓 필터/검색 UI ===== -->
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <button class="btn mp-tab-btn active" data-type="all">전체</button>
    <button class="btn mp-tab-btn" data-type="free">무료</button>
    <button class="btn mp-tab-btn" data-type="paid">유료</button>
    <span class="mp-sep"></span>
    <button class="btn mp-status-btn active" data-status="all">전체 상태</button>
    <button class="btn mp-status-btn" data-status="not">미설치</button>
    <button class="btn mp-status-btn" data-status="installed">설치됨</button>
    <input type="text" id="marketSearch" placeholder="플러그인 검색..."
           style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#334155">
</div>
<div style="margin-bottom:16px;display:flex;gap:6px;flex-wrap:wrap">
    <button class="btn btn-sm mp-cat-btn active" data-cat="">전체</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="seo">SEO</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="security">보안</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="community">커뮤니티</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="content">콘텐츠</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="design">디자인</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="advertising">광고/수익</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="notification">알림/연동</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="management">관리</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="media">미디어</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="form">폼/설문</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="shopping">쇼핑몰</button>
    <button class="btn btn-sm mp-cat-btn" data-cat="utility">유틸리티</button>
</div>

<div id="marketLoading" style="text-align:center;padding:40px;color:#94a3b8">마켓에서 플러그인을 불러오는 중...</div>
<div class="mp-grid" id="marketList" style="display:none"></div>
<div id="marketEmpty" style="display:none">
    <div class="card"><div class="card-body" style="text-align:center;padding:40px">
        <p style="font-size:16px;font-weight:600;margin-bottom:8px">등록된 플러그인이 없습니다.</p>
    </div></div>
</div>
<?php endif; ?>

<style>
.mp-tab-btn{cursor:pointer;border:1px solid #e2e8f0;padding:8px 12px;border-radius:6px;background:#fff;font-size:14px;transition:all 0.15s}
.mp-tab-btn:hover{border-color:#3b82f6}
.mp-tab-btn.active{background:#3b82f6;color:#fff;border-color:#3b82f6;font-weight:600}
.mp-status-btn{cursor:pointer;border:1px solid #e2e8f0;padding:8px 12px;border-radius:6px;background:#fff;font-size:13px;color:#475569;transition:all 0.15s}
.mp-status-btn:hover{border-color:#10b981;color:#10b981}
.mp-status-btn.active{background:#10b981;color:#fff;border-color:#10b981;font-weight:600}
.mp-sep{display:inline-block;width:1px;height:24px;background:#e2e8f0;margin:0 4px}
.mp-cat-btn{cursor:pointer;border:1px solid #e2e8f0;border-radius:20px;background:#fff;font-size:12px;color:#64748b;transition:all 0.15s}
.mp-cat-btn:hover{border-color:#3b82f6;color:#3b82f6}
.mp-cat-btn.active{background:#3b82f6;color:#fff;border-color:#3b82f6}
.mp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.mp-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;transition:box-shadow .15s;display:flex;flex-direction:column}
.mp-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.mp-thumb{width:100%;aspect-ratio:16/9;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.mp-thumb img{width:100%;height:100%;object-fit:fill}
.mp-thumb.no-img{background:linear-gradient(135deg,var(--pbg1,#6366f1) 0%,var(--pbg2,#8b5cf6) 100%)}
.mp-thumb.no-img svg{width:44px;height:44px;stroke:#fff;opacity:.9}
.mp-thumb.no-img .mp-thumb-letter{position:absolute;bottom:8px;right:12px;color:#fff;font-weight:800;font-size:16px;letter-spacing:.5px;text-shadow:0 1px 4px rgba(0,0,0,.15);opacity:.9}
.mp-body{padding:12px;flex:1}
.mp-name{font-size:14px;font-weight:700;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mp-desc{font-size:12px;color:#64748b;line-height:1.4;height:34px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.mp-meta{display:flex;align-items:center;justify-content:space-between;margin-top:8px;font-size:11px;color:#94a3b8}
.mp-price.free{font-weight:700;color:#059669}
.mp-price.paid{font-weight:700;color:#f59e0b}
.mp-price.tier-basic{font-weight:700;color:#92400e}
.mp-price.tier-pro{font-weight:700;color:#16a34a}
/* 누리코리아 등급 뱃지 */
.mp-tier-badge{position:absolute;top:8px;left:8px;padding:3px 9px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.02em;box-shadow:0 2px 6px rgba(0,0,0,.2);z-index:2}
.mp-tier-basic{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
.mp-tier-pro{background:#16a34a;color:#fff}
.mp-actions{display:flex;gap:4px;padding:8px 12px;border-top:1px solid #f1f5f9;flex-wrap:wrap}
.mp-installed{font-size:12px;color:#059669;font-weight:600;padding:4px 8px}
@media(max-width:1200px){.mp-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.mp-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){
.mp-grid{grid-template-columns:1fr}
}

/* 상세 모달 */
.detail-modal-body{padding:24px}
.detail-thumb{width:100%;max-height:250px;object-fit:contain;border-radius:8px;background:#f1f5f9;margin-bottom:16px}
.detail-name{font-size:20px;font-weight:700;margin-bottom:8px}
.detail-info{display:flex;gap:16px;font-size:13px;color:#64748b;margin-bottom:16px}
.detail-desc{font-size:14px;line-height:1.7;color:#334155;white-space:pre-wrap;word-break:break-word}
</style>

<script>
<?php if (!$isMarketOwner): ?>
var installedPlugins = <?= json_encode(array_values(array_unique(array_merge(
    array_map(function($p){return mb_strtolower($p['name'] ?? '');}, $plugins),
    array_map(function($p){return mb_strtolower($p['dir_name'] ?? '');}, $plugins)
)))) ?>;
// 정규화 버전 (공백/하이픈/언더스코어/특수문자 제거)
function nbNormalizePluginName(s) {
    if (!s) return '';
    return String(s).toLowerCase().replace(/[\s\-_]+/g, '').replace(/[^\p{L}\p{N}]+/gu, '');
}
var installedPluginsNormalized = installedPlugins.map(nbNormalizePluginName).filter(function(s){return s.length > 1});

// 설치 여부 판정 (다중 매칭)
function nbIsPluginInstalled(p) {
    var candidates = [];
    if (p.name) candidates.push(p.name);
    if (p.slug) candidates.push(p.slug);
    if (p.dir_name) candidates.push(p.dir_name);
    // 다운로드 URL에서 파일명 추출
    if (p.download_url) {
        var m = String(p.download_url).match(/\/([^\/?#]+?)(?:\.zip)?(?:[?#]|$)/i);
        if (m) candidates.push(m[1]);
    }
    // 마켓 id 필드
    if (p.id) candidates.push(String(p.id));

    // 1단계: 완전 일치
    for (var i = 0; i < candidates.length; i++) {
        var raw = String(candidates[i] || '').toLowerCase();
        if (raw && installedPlugins.indexOf(raw) !== -1) return true;
        var norm = nbNormalizePluginName(candidates[i]);
        if (norm.length > 1 && installedPluginsNormalized.indexOf(norm) !== -1) return true;
    }

    // 2단계: 부분 매칭 (한쪽이 다른쪽을 포함, 최소 4자 이상)
    for (var i = 0; i < candidates.length; i++) {
        var cand = nbNormalizePluginName(candidates[i]);
        if (cand.length < 4) continue;
        for (var j = 0; j < installedPluginsNormalized.length; j++) {
            var inst = installedPluginsNormalized[j];
            if (inst.length < 4) continue;
            // 한쪽이 다른쪽 완전 포함
            if (cand.indexOf(inst) !== -1 || inst.indexOf(cand) !== -1) return true;
        }
    }
    return false;
}

var currentType = 'all';
var currentSearch = '';
var currentCat = '';
var marketLoadTimeout;

function nbPluginGradient(name){
    var palette=[['#6366f1','#8b5cf6'],['#0ea5e9','#6366f1'],['#10b981','#0ea5e9'],['#f59e0b','#ef4444'],['#ec4899','#8b5cf6'],['#14b8a6','#10b981'],['#f43f5e','#f59e0b'],['#8b5cf6','#ec4899'],['#3b82f6','#06b6d4']];
    var h=0; for(var i=0;i<name.length;i++){h=(h*31+name.charCodeAt(i))|0;}
    return palette[Math.abs(h)%palette.length];
}
function nbPluginSvg(){
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.28a2.5 2.5 0 0 0-2.5-2.5h-3V3a2 2 0 0 0-4 0v1.78H7a2 2 0 0 0-2 2v3.72H3a2 2 0 0 0 0 4h2V18a2 2 0 0 0 2 2h3.22v-2a2.5 2.5 0 0 1 5 0v2H18a2.5 2.5 0 0 0 2.5-2.5v-3.22H22a2 2 0 0 0 0-4h-1.5V7.28z"/></svg>';
}

function loadMarketPlugins(type, search, cat) {
    currentType = type || 'all';
    currentSearch = search || '';
    currentCat = cat || '';

    // 탭 활성화 표시
    document.querySelectorAll('.mp-tab-btn').forEach(function(btn) {
        if(btn.dataset.type === currentType) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 로딩 표시
    document.getElementById('marketLoading').style.display='block';
    document.getElementById('marketList').style.display='none';
    document.getElementById('marketEmpty').style.display='none';

    // API URL 구성
    var listUrl = 'https://nuribd.com/api/v1/market/plugins?type=' + encodeURIComponent(currentType);
    if (currentSearch) listUrl += '&search=' + encodeURIComponent(currentSearch);
    if (currentCat) listUrl += '&category=' + encodeURIComponent(currentCat);
    // site_token 전달 — 서버에서 구매 여부 판별해서 purchased/download_url 반환
    var NB_SITE_TOKEN = <?= json_encode(nb_setting('market_site_token', '')) ?>;
    if (NB_SITE_TOKEN) listUrl += '&site_token=' + encodeURIComponent(NB_SITE_TOKEN);

    fetch(listUrl)
    .then(function(r){return r.json()})
    .then(function(res){
        document.getElementById('marketLoading').style.display='none';
        if(!res.success || !res.plugins || !res.plugins.length){
            document.getElementById('marketEmpty').style.display='block'; return;
        }
        var html='';
        res.plugins.forEach(function(p){
            var pNameLower = (p.name||'').toLowerCase();
            var isInstalled = nbIsPluginInstalled(p);
            var shortDesc = p.description && p.description.length > 60 ? p.description.substring(0,60)+'...' : (p.description||'');
            var safeDesc = JSON.stringify(p.description||'');
            var safeName = p.name.replace(/'/g,"\\'").replace(/"/g,'&quot;');
            var tier = p.access_tier || 'all';
            var tierBadge = tier === 'pro' ? '<span class="mp-tier-badge mp-tier-pro">프로 전용</span>'
                          : tier === 'basic' ? '<span class="mp-tier-badge mp-tier-basic">베이직+</span>'
                          : '';
            html+='<div class="mp-card" data-price="'+(p.price||0)+'" data-tier="'+tier+'" data-installed="'+(isInstalled?'1':'0')+'">';
            if(p.thumbnail){
                html+='<div class="mp-thumb"><img src="'+p.thumbnail+'" alt="">'+tierBadge+'</div>';
            } else {
                var g = nbPluginGradient(p.name||'');
                var letter = (p.name||'P').substring(0,2).toUpperCase();
                html+='<div class="mp-thumb no-img" style="--pbg1:'+g[0]+';--pbg2:'+g[1]+'">'+nbPluginSvg()+'<span class="mp-thumb-letter">'+letter+'</span>'+tierBadge+'</div>';
            }
            html+='<div class="mp-body">';
            html+='<div class="mp-name">'+p.name+'</div>';
            html+='<div class="mp-desc">'+shortDesc+'</div>';
            html+='<div class="mp-meta"><span>by '+p.author+'</span>';
            html+= p.price>0 ? '<span class="mp-price paid">'+p.price.toLocaleString()+'원</span>'
                 : tier==='pro' ? '<span class="mp-price tier-pro">프로 포함</span>'
                 : tier==='basic' ? '<span class="mp-price tier-basic">베이직 포함</span>'
                 : '<span class="mp-price free">무료</span>';
            html+='</div></div>';
            html+='<div class="mp-actions">';
            if(isInstalled){
                html+='<span class="mp-installed">설치됨 ✓</span>';
            } else if(p.price>0 && p.purchased){
                // 유료 + 구매 완료: 라이선스 URL로 설치
                html+='<button class="btn btn-sm btn-primary" onclick="installFromMarket(\''+p.download_url+'\',\''+safeName+'\')">설치</button>';
            } else if(p.price>0){
                // 유료 + 미구매: nuribd.com 마켓에서 구매 (새 탭, site_token으로 계정 없이 구매 가능)
                var buyUrl = 'https://nuribd.com/market/buy/'+p.id;
                if(NB_SITE_TOKEN) buyUrl += '?site_token='+encodeURIComponent(NB_SITE_TOKEN)+'&site_url='+encodeURIComponent(location.origin)+'&return_url='+encodeURIComponent(location.origin+location.pathname+'?page=plugins&tab=market');
                html+='<a href="'+buyUrl+'" target="_blank" class="btn btn-sm" style="background:#f59e0b;color:#fff;border-color:#f59e0b">구매</a>';
            } else if(p.download_url){
                html+='<button class="btn btn-sm btn-primary" onclick="installFromMarket(\''+p.download_url+'\',\''+safeName+'\')">설치</button>';
            }
            html+='<a class="btn btn-sm" href="market-plugin.php?id='+p.id+'" target="_blank">상세</a>';
            html+='</div></div>';
        });
        document.getElementById('marketList').innerHTML=html;
        document.getElementById('marketList').style.display='grid';
        applyMarketStatusFilter();
    })
    .catch(function(e){
        document.getElementById('marketLoading').innerHTML='<div class="card"><div class="card-body" style="text-align:center;padding:40px"><p style="color:#dc2626">마켓 서버에 연결할 수 없습니다.</p></div></div>';
    });
}

// 렌더된 카드에 설치 상태 필터 적용 (서버 재조회 없이 show/hide)
var currentStatus = 'all';
function applyMarketStatusFilter(){
    document.querySelectorAll('#marketList .mp-card').forEach(function(card){
        var installed = card.getAttribute('data-installed') === '1';
        var show = (currentStatus === 'all') ||
                   (currentStatus === 'installed' && installed) ||
                   (currentStatus === 'not' && !installed);
        card.style.display = show ? 'flex' : 'none';
    });
}

// 탭 버튼 이벤트
document.querySelectorAll('.mp-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        loadMarketPlugins(this.dataset.type, currentSearch, currentCat);
    });
});

// 설치 상태 버튼 이벤트 (서버 재조회 없이 클라이언트 필터만)
document.querySelectorAll('.mp-status-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        currentStatus = this.dataset.status;
        document.querySelectorAll('.mp-status-btn').forEach(function(b){ b.classList.remove('active'); });
        this.classList.add('active');
        applyMarketStatusFilter();
    });
});

// 카테고리 버튼 이벤트
document.querySelectorAll('.mp-cat-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.mp-cat-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        loadMarketPlugins(currentType, currentSearch, this.dataset.cat);
    });
});

// 검색 입력 이벤트 (debounce)
document.getElementById('marketSearch').addEventListener('input', function() {
    clearTimeout(marketLoadTimeout);
    marketLoadTimeout = setTimeout(function() {
        loadMarketPlugins(currentType, document.getElementById('marketSearch').value, currentCat);
    }, 300);
});

// 초기 로드
loadMarketPlugins('all', '');

<?php endif; ?>

// 마켓 설치 함수 (공통)
function installFromMarket(downloadUrl, name){
    if(!confirm('"'+name+'" 플러그인을 설치할까요?')) return;
    // 서버사이드 다운로드 (CORS 우회) - URL + 마켓 이름 전달
    var fd = new FormData();
    fd.append('action','plugin_install');
    fd.append('url', downloadUrl);
    fd.append('market_name', name || '');
    ajaxPost(fd)
    .then(function(res){
        alert(res.message || '설치 완료!');
        if(res.success) location.href='?page=plugins&tab=installed';
    })
    .catch(function(e){ alert('설치 실패:\n\n'+(e.message||e)); });
}

<?php if ($isMarketOwner): ?>
function uploadToMarket(e){
    e.preventDefault();
    var fd=new FormData();
    fd.append('action','market_upload');
    fd.append('name',document.getElementById('mu_name').value);
    fd.append('description',document.getElementById('mu_desc').value);
    fd.append('version',document.getElementById('mu_version').value);
    fd.append('price',document.getElementById('mu_price').value);
    fd.append('category',document.getElementById('mu_category').value);
    fd.append('plugin_zip',document.getElementById('mu_zip').files[0]);
    if(document.getElementById('mu_thumb').files[0]) fd.append('thumbnail',document.getElementById('mu_thumb').files[0]);
    var btn=document.querySelector('#marketUploadModal .btn-primary');
    btn.disabled=true;btn.textContent='등록 중...';
    ajaxPost(fd).then(function(r){
        btn.disabled=false;btn.textContent='등록';
        if(r.success){alert('등록 완료!');location.reload();}
        else alert(r.message||'등록 실패');
    });
    return false;
}
function openEditModal(id, name, desc, version, price, category){
    document.getElementById('me_id').value=id;
    document.getElementById('me_name').value=name;
    document.getElementById('me_desc').value=desc;
    document.getElementById('me_version').value=version;
    document.getElementById('me_price').value=price;
    document.getElementById('me_category').value=category||'';
    openModal('marketEditModal');
}

// ===== 마켓 필터링 로직 (클라이언트 측) =====
(function() {
    var allCards = Array.from(document.querySelectorAll('.mp-grid .mp-card'));
    var currentType = 'all';
    var currentSearch = '';
    var currentCat = '';
    var currentStatus = 'all';

    function filterMarketPlugins() {
        allCards.forEach(function(card) {
            var price = parseInt(card.getAttribute('data-price') || '0');
            var cat = card.getAttribute('data-category') || '';
            var installed = card.getAttribute('data-installed') === '1';
            var name = card.querySelector('.mp-name')?.textContent || '';
            var desc = card.querySelector('.mp-desc')?.textContent || '';

            var typeMatch = (currentType === 'all') ||
                            (currentType === 'free' && price === 0) ||
                            (currentType === 'paid' && price > 0);

            var catMatch = !currentCat || cat === currentCat;

            var statusMatch = (currentStatus === 'all') ||
                              (currentStatus === 'installed' && installed) ||
                              (currentStatus === 'not' && !installed);

            var searchMatch = !currentSearch ||
                             name.toLowerCase().includes(currentSearch.toLowerCase()) ||
                             desc.toLowerCase().includes(currentSearch.toLowerCase());

            card.style.display = (typeMatch && catMatch && statusMatch && searchMatch) ? 'flex' : 'none';
        });
    }

    // 탭 클릭 이벤트
    document.querySelectorAll('.mp-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentType = this.dataset.type;
            filterMarketPlugins();
            document.querySelectorAll('.mp-tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    // 설치 상태 버튼 이벤트
    document.querySelectorAll('.mp-status-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentStatus = this.dataset.status;
            filterMarketPlugins();
            document.querySelectorAll('.mp-status-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    // 카테고리 클릭 이벤트
    document.querySelectorAll('.mp-cat-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentCat = this.dataset.cat;
            filterMarketPlugins();
            document.querySelectorAll('.mp-cat-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    // 검색 입력 이벤트 (debounce)
    var searchTimeout;
    var searchInput = document.getElementById('marketSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            var searchVal = this.value;
            searchTimeout = setTimeout(function() {
                currentSearch = searchVal;
                filterMarketPlugins();
            }, 300);
        });
    }

    // 플러그인 카드에 가격 데이터 추가
    allCards.forEach(function(card) {
        var priceSpan = card.querySelector('.mp-price');
        if (priceSpan) {
            var isPrice = priceSpan.classList.contains('paid');
            var price = isPrice ? parseInt(priceSpan.textContent) : 0;
            card.setAttribute('data-price', price);
        }
    });
})();

// 수정 버튼 이벤트 위임 (data-edit 속성에서 JSON 읽기)
document.addEventListener('click', function(e){
    var btn = e.target.closest('.mp-edit-btn');
    if(!btn) return;
    try {
        var d = JSON.parse(btn.getAttribute('data-edit'));
        openEditModal(d.id, d.name, d.desc, d.version, d.price, d.category);
    } catch(ex){ console.error('수정 모달 오류:', ex); alert('수정 데이터 오류'); }
});
function editMarket(e){
    e.preventDefault();
    var fd=new FormData();
    fd.append('action','market_edit');
    fd.append('id',document.getElementById('me_id').value);
    fd.append('name',document.getElementById('me_name').value);
    fd.append('description',document.getElementById('me_desc').value);
    fd.append('version',document.getElementById('me_version').value);
    fd.append('price',document.getElementById('me_price').value);
    fd.append('category',document.getElementById('me_category').value);
    if(document.getElementById('me_zip').files[0]) fd.append('plugin_zip',document.getElementById('me_zip').files[0]);
    if(document.getElementById('me_thumb').files[0]) fd.append('thumbnail',document.getElementById('me_thumb').files[0]);
    var btn=document.querySelector('#marketEditModal .btn-primary');
    btn.disabled=true;btn.textContent='저장 중...';
    ajaxPost(fd).then(function(r){
        btn.disabled=false;btn.textContent='저장';
        if(r.success){alert('수정 완료!');location.reload();}
        else alert(r.message||'수정 실패');
    });
    return false;
}
<?php endif; ?>
</script>

<!-- 플러그인 상세 모달 코드 제거됨 (market-plugin.php 상세 페이지로 대체) -->

<!-- 마켓 수정 모달 -->
<?php if (isset($isMarketOwner) && $isMarketOwner): ?>
<div class="modal" id="marketEditModal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header">
            <h3>플러그인 수정</h3>
            <button class="modal-close" onclick="closeModal('marketEditModal')">&times;</button>
        </div>
        <form onsubmit="return editMarket(event)" style="padding:24px">
            <input type="hidden" id="me_id">
            <div class="form-group">
                <label>이름</label>
                <input type="text" id="me_name" required>
            </div>
            <div class="form-group">
                <label>설명</label>
                <textarea id="me_desc" rows="3" style="resize:vertical"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>버전</label>
                    <input type="text" id="me_version" style="width:100px">
                </div>
                <div class="form-group">
                    <label>가격</label>
                    <input type="number" id="me_price" min="0" style="width:100px">
                </div>
            </div>
            <div class="form-group">
                <label>카테고리</label>
                <select id="me_category" style="width:200px">
                    <option value="">선택안함</option>
                    <option value="seo">SEO</option>
                    <option value="security">보안</option>
                    <option value="community">커뮤니티</option>
                    <option value="content">콘텐츠</option>
                    <option value="design">디자인</option>
                    <option value="advertising">광고/수익</option>
                    <option value="notification">알림/연동</option>
                    <option value="management">관리</option>
                    <option value="media">미디어</option>
                    <option value="form">폼/설문</option>
                    <option value="shopping">쇼핑몰</option>
                    <option value="utility">유틸리티</option>
                </select>
            </div>
            <div class="form-group">
                <label>ZIP 교체 (선택)</label>
                <input type="file" id="me_zip" accept=".zip">
                <small>새 ZIP 업로드 시 기존 파일이 교체됩니다 (이미지도 자동 교체)</small>
            </div>
            <div class="form-group">
                <label>썸네일 교체 (선택)</label>
                <input type="file" id="me_thumb" accept="image/*">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('marketEditModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ============================================================ -->
<style>
.mp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.mp-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;transition:box-shadow .15s;display:flex;flex-direction:column}
.mp-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.mp-thumb{width:100%;aspect-ratio:16/9;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.mp-thumb img{width:100%;height:100%;object-fit:fill}
.mp-thumb.no-img{background:linear-gradient(135deg,var(--pbg1,#6366f1) 0%,var(--pbg2,#8b5cf6) 100%)}
.mp-thumb.no-img svg{width:44px;height:44px;stroke:#fff;opacity:.9}
.mp-thumb.no-img .mp-thumb-letter{position:absolute;bottom:8px;right:12px;color:#fff;font-weight:800;font-size:16px;letter-spacing:.5px;text-shadow:0 1px 4px rgba(0,0,0,.15);opacity:.9}
.mp-body{padding:12px;flex:1}
.mp-name{font-size:14px;font-weight:700;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis}
.mp-desc{font-size:12px;color:#64748b;line-height:1.4;height:34px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.mp-meta{display:flex;align-items:center;justify-content:space-between;margin-top:8px;font-size:11px;color:#94a3b8}
.mp-actions{display:flex;gap:4px;padding:8px 12px;border-top:1px solid #f1f5f9;flex-wrap:wrap}
@media(max-width:1200px){.mp-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.mp-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.mp-grid{grid-template-columns:1fr}}
</style>
<!-- 설치됨 탭 -->
<!-- ============================================================ -->
<div style="margin-bottom:12px;text-align:right">
    <button class="btn btn-primary" onclick="openModal('installModal')">+ 플러그인 설치 (ZIP)</button>
</div>

<?php if (empty($plugins)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;color:#94a3b8">
        <p style="font-size:16px;font-weight:600;margin-bottom:8px">설치된 플러그인이 없습니다</p>
        <p style="font-size:13px;margin-bottom:16px">마켓에서 플러그인을 설치해보세요.</p>
        <a href="?page=plugins&tab=market" class="btn btn-primary">마켓 바로가기</a>
    </div>
</div>
<?php else: ?>
<div class="mp-grid">
<?php foreach ($plugins as $p):
    $thumbUrl = '';
    $pDir = NB_ROOT . '/plugins/' . $p['dir_name'];
    if (is_dir($pDir)) {
        foreach (scandir($pDir) as $f) {
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)) {
                $thumbUrl = '../plugins/' . $p['dir_name'] . '/' . $f;
                break;
            }
        }
    }
    $pName = $p['name'] ?: $p['dir_name'];
    $pDesc = $p['description'] ?: '설명 없음';
?>
<div class="mp-card">
    <?php if ($thumbUrl): ?>
    <div class="mp-thumb"><img src="<?= $thumbUrl ?>" alt=""></div>
    <?php else: $_g = nb_plugin_gradient($pName); ?>
    <div class="mp-thumb no-img" style="--pbg1:<?= $_g[0] ?>;--pbg2:<?= $_g[1] ?>">
        <?= nb_plugin_placeholder_svg() ?>
        <span class="mp-thumb-letter"><?= nb_e(mb_strtoupper(mb_substr($pName, 0, 2))) ?></span>
    </div>
    <?php endif; ?>
    <div class="mp-body">
        <div class="mp-name"><?= nb_e($pName) ?></div>
        <div class="mp-desc"><?= nb_e(mb_strimwidth($pDesc, 0, 60, '...')) ?></div>
        <div class="mp-meta">
            <span>v<?= nb_e($p['version'] ?? '1.0') ?></span>
            <span style="color:<?= $p['enabled'] ? '#059669' : '#94a3b8' ?>;font-weight:600"><?= $p['enabled'] ? '활성' : '비활성' ?></span>
        </div>
    </div>
    <div class="mp-actions">
        <?php $is_protected = in_array($p['dir_name'], ['nurikorea-announcements'], true); ?>
        <?php if ($p['enabled']): ?>
        <a href="?page=plugins&settings=<?= urlencode($p['dir_name']) ?>" class="btn btn-sm">설정</a>
        <?php endif; ?>
        <?php if ($is_protected && $p['enabled']): ?>
            <span class="btn btn-sm" style="background:#f1f5f9;color:#64748b;cursor:not-allowed" title="시스템 필수 플러그인 - 비활성화/삭제 불가">필수</span>
        <?php elseif ($is_protected && !$p['enabled']): ?>
            <button class="btn btn-sm btn-primary" onclick="togglePlugin('<?= nb_e($p['dir_name']) ?>',1)">활성화</button>
        <?php else: ?>
            <button class="btn btn-sm <?= $p['enabled'] ? 'btn-danger' : 'btn-primary' ?>" onclick="togglePlugin('<?= nb_e($p['dir_name']) ?>',<?= $p['enabled'] ? '0' : '1' ?>)">
                <?= $p['enabled'] ? '비활성화' : '활성화' ?>
            </button>
            <?php if (!$p['enabled']): ?>
            <button class="btn btn-sm btn-danger" onclick="deletePlugin('<?= nb_e($p['dir_name']) ?>')">삭제</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<!-- 설치 모달 -->
<div class="modal" id="installModal">
    <div class="modal-content" style="max-width:450px">
        <div class="modal-header">
            <h3>플러그인 설치</h3>
            <button class="modal-close" onclick="closeModal('installModal')">&times;</button>
        </div>
        <form onsubmit="return installPlugin(event)" style="padding:24px">
            <div class="form-group">
                <label>플러그인 ZIP 파일</label>
                <input type="file" id="pluginZip" accept=".zip" required>
                <small>플러그인 폴더를 ZIP으로 압축한 파일을 선택하세요</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('installModal')">취소</button>
                <button type="submit" class="btn btn-primary">설치</button>
            </div>
        </form>
    </div>
</div>

<!-- 마켓 등록 모달 (nuribd.com 전용) -->
<?php if (isset($isMarketOwner) && $isMarketOwner): ?>
<div class="modal" id="marketUploadModal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header">
            <h3>마켓에 플러그인 등록</h3>
            <button class="modal-close" onclick="closeModal('marketUploadModal')">&times;</button>
        </div>
        <form onsubmit="return uploadToMarket(event)" style="padding:24px">
            <div class="form-group">
                <label>플러그인 이름 *</label>
                <input type="text" id="mu_name" required placeholder="예: AI 자동포스팅">
            </div>
            <div class="form-group">
                <label>설명 *</label>
                <textarea id="mu_desc" rows="3" required placeholder="플러그인 기능 설명" style="resize:vertical"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>버전</label>
                    <input type="text" id="mu_version" value="1.0" style="width:100px">
                </div>
                <div class="form-group">
                    <label>가격 (원, 0=무료)</label>
                    <input type="number" id="mu_price" value="0" min="0" style="width:120px" placeholder="예: 5000">
                </div>
            </div>
            <div class="form-group">
                <label>카테고리</label>
                <select id="mu_category" style="width:200px">
                    <option value="">선택안함</option>
                    <option value="seo">SEO</option>
                    <option value="security">보안</option>
                    <option value="community">커뮤니티</option>
                    <option value="content">콘텐츠</option>
                    <option value="design">디자인</option>
                    <option value="advertising">광고/수익</option>
                    <option value="notification">알림/연동</option>
                    <option value="management">관리</option>
                    <option value="media">미디어</option>
                    <option value="form">폼/설문</option>
                    <option value="shopping">쇼핑몰</option>
                    <option value="utility">유틸리티</option>
                </select>
            </div>
            <div class="form-group">
                <label>플러그인 ZIP 파일 * (이미지 포함 시 자동 썸네일)</label>
                <input type="file" id="mu_zip" accept=".zip" required>
            </div>
            <div class="form-group">
                <label>별도 썸네일 (선택, ZIP 이미지보다 우선)</label>
                <input type="file" id="mu_thumb" accept="image/*">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('marketUploadModal')">취소</button>
                <button type="submit" class="btn btn-primary">등록</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function togglePlugin(name, enable){
    var d=new FormData();
    d.append('action','plugin_toggle');
    d.append('plugin',name);
    d.append('enabled',enable);
    ajaxPost(d).then(function(r){if(r.success)location.reload()});
}
function installPlugin(e){
    e.preventDefault();
    var file=document.getElementById('pluginZip').files[0];
    if(!file)return false;
    var d=new FormData();
    d.append('action','plugin_install');
    d.append('plugin_zip',file);
    ajaxPost(d).then(function(r){
        alert(r.message||'완료');
        if(r.success) location.reload();
    });
    return false;
}
function deletePlugin(name){
    if(!confirm('"'+name+'" 플러그인을 삭제할까요?'))return;
    var d=new FormData();
    d.append('action','plugin_delete');
    d.append('plugin',name);
    ajaxPost(d).then(function(r){if(r.success)location.reload()});
}
</script>

<?php adminFooter(); ?>
