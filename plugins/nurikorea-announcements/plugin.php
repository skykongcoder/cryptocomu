<?php
/**
 * 누리코리아 알림 플러그인
 *
 * 누리코리아(nurikorea.com)에서 제공하는 공식 공지를
 * 관리자 화면 상단 배너로 표시합니다.
 *
 * 단순 구조:
 *   - 배너에 공지 표시
 *   - [삭제] 버튼 = 각 관리자가 본인 화면에서만 영구 제거 (복구 없음)
 *   - 6시간 캐시 (테스트 중: 10초)
 */

// ========== 설정 ==========
const NKA_API_URL       = 'https://nurikorea.com/api/announcements.php';
const NKA_CACHE_SECONDS = 1800;   // 30분 캐시 (서버 부하 최소, 공지 30분 내 도달)

// ========== API 조회 (로컬 캐시) ==========
function nka_fetch_announcements(): array {
    $cacheFile = __DIR__ . '/cache.json';
    if (is_file($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < NKA_CACHE_SECONDS) {
            $json = @file_get_contents($cacheFile);
            $data = json_decode($json ?: '{}', true);
            return is_array($data['announcements'] ?? null) ? $data['announcements'] : [];
        }
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'user_agent' => 'NuriBoard-Announcements/1.0'],
        'ssl'  => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents(NKA_API_URL, false, $ctx);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['ok'])) return [];
    @file_put_contents($cacheFile, $raw);
    return $data['announcements'] ?? [];
}

// ========== 삭제된 알림 ID 관리 (완전 삭제, 복구 없음) ==========
// 첫 실행 시: 이미 발행된 모든 공지를 "이미 본 것"으로 자동 처리
// (새 설치본이 과거 공지 더미에 파묻히지 않게 함)
function nka_deleted_ids(): array {
    $f = __DIR__ . '/deleted.json';
    if (!is_file($f)) {
        // 첫 실행 — 현재 존재하는 모든 공지 ID 를 "이미 본 것"으로 기록
        $all = nka_fetch_announcements();
        $ids = array_map(fn($a) => (int)($a['id'] ?? 0), $all);
        $ids = array_values(array_filter($ids, fn($i) => $i > 0));
        @file_put_contents($f, json_encode($ids));
        return $ids;
    }
    $data = json_decode(@file_get_contents($f) ?: '[]', true);
    return is_array($data) ? $data : [];
}

function nka_mark_deleted(int $id): void {
    $ids = nka_deleted_ids();
    if (!in_array($id, $ids, true)) {
        $ids[] = $id;
        if (count($ids) > 2000) $ids = array_slice($ids, -2000);
        @file_put_contents(__DIR__ . '/deleted.json', json_encode(array_values($ids)));
    }
}

// ========== AJAX: 배너에서 삭제 ==========
if (isset($_POST['nka_delete_id'])) {
    $id = (int)$_POST['nka_delete_id'];
    if ($id > 0) nka_mark_deleted($id);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ========== 중요도 라벨 ==========
function nka_sev_label(string $sev): string {
    return [
        'info'     => '공지',
        'update'   => '업데이트',
        'security' => '보안',
        'urgent'   => '긴급',
    ][$sev] ?? '공지';
}

// ========== 관리자 헤더에 배너 렌더링 ==========
Plugin::addHook('admin_header', function () {
    $all     = nka_fetch_announcements();
    $deleted = nka_deleted_ids();
    $visible = array_values(array_filter($all, fn($a) => !in_array((int)$a['id'], $deleted, true)));

    if (empty($visible)) return;
    ?>
    <style>
    .nka-stack { margin: 0 0 16px; display: flex; flex-direction: column; gap: 8px; }
    .nka-banner {
        padding: 14px 18px;
        background: #ffffff;
        color: #111827;
        border: 1px solid #e5e7eb;
        border-left: 4px solid #9ca3af;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 14px;
        font-family: inherit;
    }
    .nka-banner.sev-update   { border-left-color: #16a34a; }
    .nka-banner.sev-security { border-left-color: #d97706; }
    .nka-banner.sev-urgent   { border-left-color: #dc2626; }
    .nka-sev {
        padding: 3px 9px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: #f3f4f6;
        color: #4b5563;
        flex-shrink: 0;
    }
    .nka-banner.sev-update .nka-sev   { background: #dcfce7; color: #16a34a; }
    .nka-banner.sev-security .nka-sev { background: #fed7aa; color: #d97706; }
    .nka-banner.sev-urgent .nka-sev   { background: #fee2e2; color: #dc2626; }
    .nka-banner-text { flex: 1; min-width: 0; }
    .nka-banner-title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 2px; }
    .nka-banner-body  { font-size: 13px; color: #4b5563; line-height: 1.5; }
    .nka-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .nka-btn {
        padding: 7px 14px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        color: #374151;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        font-family: inherit;
    }
    .nka-btn:hover { background: #f9fafb; }
    .nka-btn-primary { background: #111827; border-color: #111827; color: #fff; }
    .nka-btn-primary:hover { background: #1f2937; border-color: #1f2937; color: #fff; }
    .nka-btn-danger { color: #dc2626; border-color: #fecaca; }
    .nka-btn-danger:hover { background: #fef2f2; }
    @media (max-width: 720px) {
        .nka-banner { flex-direction: column; align-items: flex-start; }
        .nka-actions { width: 100%; }
        .nka-actions .nka-btn { flex: 1; text-align: center; }
    }
    </style>

    <div class="nka-stack">
    <?php foreach ($visible as $a): ?>
        <div class="nka-banner sev-<?= htmlspecialchars($a['severity']) ?>">
            <span class="nka-sev"><?= nka_sev_label($a['severity']) ?></span>
            <div class="nka-banner-text">
                <div class="nka-banner-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="nka-banner-body"><?= htmlspecialchars(mb_substr($a['body'], 0, 140)) ?></div>
            </div>
            <div class="nka-actions">
                <?php if (!empty($a['link_url'])): ?>
                <a href="<?= htmlspecialchars($a['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="nka-btn nka-btn-primary" onclick="event.stopPropagation()"><?= htmlspecialchars($a['link_text']) ?></a>
                <?php endif; ?>
                <button class="nka-btn nka-btn-danger" onclick="nkaDelete(<?= (int)$a['id'] ?>)">삭제</button>
            </div>
        </div>
    <?php endforeach; ?>
        <div style="text-align:right;font-size:12px;margin-top:4px">
            <a href="?page=plugins&settings=nurikorea-announcements" style="color:#6b7280;text-decoration:underline">알림 내역 전체 보기</a>
        </div>
    </div>

    <script>
    function nkaDelete(id) {
        if (!confirm('이 알림을 영구 삭제할까요? (복구 불가)')) return;
        const fd = new FormData();
        fd.append('nka_delete_id', id);
        fetch('/plugins/nurikorea-announcements/plugin.php', { method: 'POST', body: fd })
            .then(() => location.reload());
    }
    </script>
    <?php
});
