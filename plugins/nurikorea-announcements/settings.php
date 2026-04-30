<?php
/**
 * 누리코리아 알림 - 설정 / 내역 페이지
 * 전체 공지 조회 + 개별 삭제 (완전 삭제, 복구 없음)
 */

require_once __DIR__ . '/plugin.php';

$_nka_cache_file   = __DIR__ . '/cache.json';
$_nka_deleted_file = __DIR__ . '/deleted.json';

// === POST 처리 (redirect 없이 바로 처리) ===
$_nka_flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nka_act'])) {
    $act = $_POST['nka_act'];
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'delete' && $id > 0) {
        nka_mark_deleted($id);
        $_nka_flash = '알림을 삭제했습니다.';
    } elseif ($act === 'refresh') {
        @unlink($_nka_cache_file);
        $_nka_flash = '서버에서 최신 알림을 다시 불러왔습니다.';
    }
}

// === 데이터 ===
$all     = nka_fetch_announcements();
$deleted = nka_deleted_ids();
$items   = array_values(array_filter($all, fn($a) => !in_array((int)$a['id'], $deleted, true)));
?>

<style>
.nka-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.nka-head h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em; }
.nka-stat { display: flex; gap: 10px; font-size: 13px; color: #6b7280; }
.nka-stat b { color: #111827; font-weight: 700; }

.nka-flash {
    padding: 10px 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    border-radius: 4px;
    font-size: 13px;
    margin-bottom: 16px;
}

.nka-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.nka-tool-btn {
    padding: 8px 14px;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
}
.nka-tool-btn:hover { background: #f9fafb; }

.nka-list { display: flex; flex-direction: column; gap: 10px; }

.nka-archive {
    padding: 18px 20px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #9ca3af;
    border-radius: 4px;
}
.nka-archive.sev-update   { border-left-color: #16a34a; }
.nka-archive.sev-security { border-left-color: #d97706; }
.nka-archive.sev-urgent   { border-left-color: #dc2626; }

.nka-arc-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.nka-arc-sev {
    padding: 3px 9px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    background: #f3f4f6;
    color: #6b7280;
}
.nka-archive.sev-update .nka-arc-sev   { background: #dcfce7; color: #16a34a; }
.nka-archive.sev-security .nka-arc-sev { background: #fed7aa; color: #d97706; }
.nka-archive.sev-urgent .nka-arc-sev   { background: #fee2e2; color: #dc2626; }
.nka-arc-date {
    margin-left: auto;
    color: #9ca3af;
    font-size: 12px;
    font-family: 'JetBrains Mono', Consolas, monospace;
}

.nka-arc-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 6px; letter-spacing: -0.01em; }
.nka-arc-body  { font-size: 13px; color: #4b5563; line-height: 1.6; margin-bottom: 12px; white-space: pre-wrap; }

.nka-arc-foot {
    display: flex;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid #f3f4f6;
    flex-wrap: wrap;
}
.nka-arc-link {
    padding: 7px 14px;
    background: #111827;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}
.nka-arc-link:hover { background: #1f2937; color: #fff; text-decoration: none; }
.nka-arc-btn {
    padding: 7px 14px;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    color: #374151;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
}
.nka-arc-btn:hover { background: #f9fafb; }
.nka-arc-btn.danger { color: #dc2626; border-color: #fecaca; }
.nka-arc-btn.danger:hover { background: #fef2f2; }

.nka-empty {
    padding: 50px 20px;
    text-align: center;
    color: #9ca3af;
    background: #fafafa;
    border-radius: 4px;
    font-size: 14px;
}
</style>

<div class="nka-head">
    <h2>누리코리아 알림 내역</h2>
    <div class="nka-stat">
        <span>받은 알림 <b><?= count($items) ?></b></span>
    </div>
</div>

<?php if ($_nka_flash): ?>
    <div class="nka-flash"><?= htmlspecialchars($_nka_flash) ?></div>
<?php endif; ?>

<form method="post" class="nka-toolbar">
    <button type="submit" name="nka_act" value="refresh" class="nka-tool-btn">새로고침</button>
</form>

<?php if (empty($items)): ?>
    <div class="nka-empty">
        받은 알림이 없습니다. 누리코리아에서 새 공지를 발송하면 여기 표시됩니다.
    </div>
<?php else: ?>
    <div class="nka-list">
        <?php foreach ($items as $a): ?>
            <div class="nka-archive sev-<?= htmlspecialchars($a['severity']) ?>">
                <div class="nka-arc-head">
                    <span class="nka-arc-sev"><?= nka_sev_label($a['severity']) ?></span>
                    <span class="nka-arc-date"><?= htmlspecialchars($a['published_at']) ?></span>
                </div>
                <div class="nka-arc-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="nka-arc-body"><?= htmlspecialchars($a['body']) ?></div>
                <div class="nka-arc-foot">
                    <?php if (!empty($a['link_url'])): ?>
                        <a href="<?= htmlspecialchars($a['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="nka-arc-link">
                            <?= htmlspecialchars($a['link_text'] ?: '자세히 보기') ?>
                        </a>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('이 알림을 영구 삭제할까요?\n복구할 수 없습니다.')">
                        <input type="hidden" name="nka_act" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="nka-arc-btn danger">삭제</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
