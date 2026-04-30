<?php
require_once __DIR__ . '/plugin.php';
require_once __DIR__ . '/../_openrouter_models.php';

$flash = '';

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['faq_act'])) {
    $act = $_POST['faq_act'];

    if ($act === 'save') {
        $config = _faq_load_config();
        $config['enabled']        = !empty($_POST['enabled']) ? true : false;
        $config['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
        $config['openai_model']   = trim($_POST['openai_model'] ?? 'openai/gpt-4o-mini');
        $boards_checked = $_POST['board_check'] ?? [];
        $config['allowed_boards'] = implode(',', array_filter(array_map('trim', (array)$boards_checked)));
        $config['faq_count']      = max(2, min(6, (int)($_POST['faq_count'] ?? 4)));
        $config['auto_generate']  = !empty($_POST['auto_generate']) ? true : false;
        _faq_save_config($config);
        $config = _faq_reload_config(); // static 캐시 무효화 후 최신 값으로
        $flash = '설정이 저장되었습니다.';
    }

    if ($act === 'bulk') {
        set_time_limit(120);
        $limit  = (int)($_POST['bulk_limit'] ?? 20);
        $prefix = DB::getPrefix();
        $config = _faq_load_config();
        $where  = '';
        $params = [];
        if (!empty($config['allowed_boards'])) {
            $boards = array_map('trim', explode(',', $config['allowed_boards']));
            $in = implode(',', array_fill(0, count($boards), '?'));
            $where  = "WHERE board_id IN ($in)";
            $params = $boards;
        }
        $params[] = $limit;
        $rows = DB::fetchAll("SELECT id FROM {$prefix}posts {$where} ORDER BY id DESC LIMIT ?", $params);
        $done = 0;
        foreach ($rows as $row) {
            if (_faq_generate((int)$row['id'])) $done++;
        }
        $flash = "최근 글 {$done}개에 FAQ를 생성했습니다.";
    }
}

$config  = _faq_reload_config();
$prefix  = DB::getPrefix();
$faqCount = 0;
try { $r = DB::fetch("SELECT COUNT(*) as c FROM {$prefix}faq_items"); $faqCount = (int)($r['c'] ?? 0); } catch(Exception $e) {}

// 전체 게시판 목록 (누리보드 컬럼: board_id, title, sort_order)
$boards = [];
try {
    $boards = DB::fetchAll(
        "SELECT board_id, title FROM {$prefix}boards WHERE is_active = 1 ORDER BY sort_order ASC, board_id ASC"
    );
} catch(Exception $e) {
    // 컬럼명 fallback
    try { $boards = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards ORDER BY board_id ASC"); } catch(Exception $e2) {}
}
?>
<style>
.faq-adm{max-width:820px}
.faq-adm-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.faq-adm-head h2{font-size:22px;font-weight:700;color:#111827;margin:0}
.faq-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600}
.faq-pill.on{background:#f0fdf4;border:1px solid #86efac;color:#16a34a}
.faq-pill.off{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
.faq-pill .dot{width:8px;height:8px;border-radius:50%}
.faq-pill.on .dot{background:#16a34a;animation:faq-pulse 2s infinite}
.faq-pill.off .dot{background:#f59e0b}
@keyframes faq-pulse{0%,100%{opacity:1}50%{opacity:.4}}
.faq-flash{padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:8px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
.faq-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.faq-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;text-align:center}
.faq-stat-num{font-size:26px;font-weight:800;color:#22c55e;line-height:1}
.faq-stat-lbl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-top:6px}
.faq-sec{background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;overflow:hidden}
.faq-sec-head{padding:14px 20px;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px}
.faq-sec-head svg{color:#22c55e;flex-shrink:0}
.faq-sec-head h3{font-size:15px;font-weight:700;color:#111827;margin:0}
.faq-sec-body{padding:20px}
.faq-toggle{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:10px;cursor:pointer}
.faq-toggle input[type=checkbox]{width:18px;height:18px;accent-color:#22c55e;cursor:pointer}
.faq-toggle-body b{display:block;font-size:14px;color:#111827}
.faq-toggle-body span{display:block;font-size:12px;color:#6b7280;margin-top:2px}
.faq-field{margin-bottom:16px}
.faq-field label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.faq-field input[type=text],.faq-field input[type=password],.faq-field select{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff}
.faq-hint{font-size:12px;color:#6b7280;margin-top:5px;line-height:1.5}
.faq-boards{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.faq-board-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;cursor:pointer}
.faq-board-chip input{accent-color:#22c55e;width:15px;height:15px}
.faq-submit{position:sticky;bottom:0;background:#fff;padding:16px 0;margin-top:20px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
.faq-btn{padding:10px 20px;background:#22c55e;color:#fff;border:1px solid #22c55e;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.faq-btn:hover{background:#16a34a}
.faq-btn-ghost{background:#fff;color:#374151;border-color:#d1d5db}
.faq-btn-ghost:hover{background:#f3f4f6}
.faq-range{width:100%;accent-color:#22c55e}
.faq-range-val{display:inline-block;width:24px;text-align:center;font-weight:700;color:#22c55e}
</style>

<div class="faq-adm">
    <div class="faq-adm-head">
        <h2>AI FAQ 자동 생성기</h2>
        <?php if ($config['enabled']): ?>
        <span class="faq-pill on"><span class="dot"></span> 활성</span>
        <?php else: ?>
        <span class="faq-pill off"><span class="dot"></span> 비활성</span>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
    <div class="faq-flash">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- 통계 -->
    <div class="faq-stats">
        <div class="faq-stat">
            <div class="faq-stat-num"><?= $faqCount ?></div>
            <div class="faq-stat-lbl">FAQ 생성된 글</div>
        </div>
        <div class="faq-stat">
            <div class="faq-stat-num"><?= $config['faq_count'] ?></div>
            <div class="faq-stat-lbl">글당 Q&amp;A 수</div>
        </div>
        <div class="faq-stat">
            <div class="faq-stat-num"><?= $config['auto_generate'] ? '자동' : '수동' ?></div>
            <div class="faq-stat-lbl">생성 방식</div>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="faq_act" value="save">

        <!-- 기본 설정 -->
        <div class="faq-sec">
            <div class="faq-sec-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                <h3>기본 설정</h3>
            </div>
            <div class="faq-sec-body">
                <label class="faq-toggle">
                    <input type="checkbox" name="enabled" value="1" <?= $config['enabled'] ? 'checked' : '' ?>>
                    <div class="faq-toggle-body">
                        <b>FAQ 자동 생성 사용</b>
                        <span>글 저장 시 AI가 FAQ를 자동 생성하고 글 하단에 추가, FAQPage 스키마 자동 출력</span>
                    </div>
                </label>
                <label class="faq-toggle">
                    <input type="checkbox" name="auto_generate" value="1" <?= $config['auto_generate'] ? 'checked' : '' ?>>
                    <div class="faq-toggle-body">
                        <b>글 저장 시 자동 생성</b>
                        <span>체크 해제 시 관리자가 글 하단 버튼으로 직접 생성</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- API 설정 -->
        <div class="faq-sec">
            <div class="faq-sec-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <h3>OpenAI 설정</h3>
            </div>
            <div class="faq-sec-body">
                <div class="faq-field">
                    <label>API 키</label>
                    <input type="password" name="openai_api_key" value="<?= htmlspecialchars($config['openai_api_key']) ?>" placeholder="sk-or-v1-...">
                    <p class="faq-hint">다른 AI 플러그인과 동일한 키 사용 가능</p>
                </div>
                <div class="faq-field">
                    <label>모델</label>
                    <select name="openai_model">
                        <?= nb_openrouter_options($config['openai_model'] ?? '') ?>
                    </select>
                    <p class="faq-hint">gpt-4o-mini 권장 (비용 저렴, 품질 우수)</p>
                </div>
            </div>
        </div>

        <!-- FAQ 설정 -->
        <div class="faq-sec">
            <div class="faq-sec-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <h3>FAQ 설정</h3>
            </div>
            <div class="faq-sec-body">
                <div class="faq-field">
                    <label>글당 Q&amp;A 개수: <span class="faq-range-val" id="faq-count-val"><?= $config['faq_count'] ?></span>개</label>
                    <input type="range" class="faq-range" name="faq_count" min="2" max="6" value="<?= $config['faq_count'] ?>"
                        oninput="document.getElementById('faq-count-val').textContent=this.value">
                    <p class="faq-hint">2~6개 권장. 많을수록 API 비용 증가</p>
                </div>

                <div class="faq-field">
                    <label>적용 게시판 (체크한 게시판만 적용, 모두 해제 시 전체 적용)</label>
                    <?php
                    $allowedArr = array_filter(array_map('trim', explode(',', $config['allowed_boards'])));
                    ?>
                    <?php if (!empty($boards)): ?>
                    <div class="faq-boards">
                        <?php foreach ($boards as $b):
                            $bid     = (string)($b['board_id'] ?? '');
                            $btitle  = $b['title'] ?? $bid;
                            $checked = in_array($bid, $allowedArr, true) ? 'checked' : '';
                        ?>
                        <label class="faq-board-chip">
                            <input type="checkbox" name="board_check[]" value="<?= htmlspecialchars($bid) ?>" <?= $checked ?>>
                            <?= htmlspecialchars($btitle) ?>
                            <small style="color:#9ca3af">(<?= htmlspecialchars($bid) ?>)</small>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="faq-hint">아무것도 선택 안 하면 전체 게시판에 적용됩니다.</p>
                    <?php else: ?>
                    <div style="padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:13px;color:#92400e;">
                        게시판 목록을 불러오지 못했습니다.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="faq-submit">
            <p class="faq-hint" style="margin:0">변경사항은 저장 후 즉시 적용됩니다.</p>
            <button type="submit" class="faq-btn">설정 저장</button>
        </div>
    </form>

    <!-- 일괄 생성 -->
    <div class="faq-sec" style="margin-top:16px" id="faq-bulk-sec">
        <div class="faq-sec-head">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <h3>기존 글 일괄 FAQ 생성</h3>
        </div>
        <div class="faq-sec-body">

            <!-- 시작 전 UI -->
            <div id="faq-bulk-ready" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div>
                    <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:#111827">플러그인 설치 전 작성된 글에 FAQ 추가</p>
                    <p style="margin:0;font-size:12px;color:#6b7280">글 1개씩 순차 처리 — 타임아웃 없음. API 비용이 발생하므로 적절한 수량을 설정하세요.</p>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <select id="faq-bulk-limit" style="padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                        <option value="10">최근 10개</option>
                        <option value="20" selected>최근 20개</option>
                        <option value="50">최근 50개</option>
                    </select>
                    <button class="faq-btn faq-btn-ghost" onclick="faqBulkStart()">지금 생성</button>
                </div>
            </div>

            <!-- 진행 중 UI -->
            <div id="faq-bulk-progress" style="display:none">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                    <div style="width:10px;height:10px;border-radius:50%;background:#22c55e;animation:faq-pulse 1s infinite"></div>
                    <span style="font-size:14px;font-weight:600;color:#111827">FAQ 생성 중...</span>
                    <span id="faq-prog-count" style="font-size:13px;color:#6b7280;margin-left:auto">0 / 0</span>
                </div>

                <!-- 프로그레스 바 -->
                <div style="background:#f1f5f9;border-radius:999px;height:10px;overflow:hidden;margin-bottom:12px">
                    <div id="faq-prog-bar" style="height:100%;width:0%;background:#22c55e;border-radius:999px;transition:width .4s ease"></div>
                </div>

                <!-- 현재 처리 중인 글 -->
                <div id="faq-prog-current" style="font-size:12px;color:#6b7280;margin-bottom:14px;min-height:18px">준비 중...</div>

                <!-- 성공/실패 카운터 -->
                <div style="display:flex;gap:12px">
                    <div style="flex:1;padding:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;text-align:center">
                        <div id="faq-prog-ok" style="font-size:22px;font-weight:800;color:#22c55e">0</div>
                        <div style="font-size:11px;color:#16a34a;margin-top:2px">성공</div>
                    </div>
                    <div style="flex:1;padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;text-align:center">
                        <div id="faq-prog-fail" style="font-size:22px;font-weight:800;color:#dc2626">0</div>
                        <div style="font-size:11px;color:#dc2626;margin-top:2px">실패</div>
                    </div>
                </div>
            </div>

            <!-- 완료 UI -->
            <div id="faq-bulk-done" style="display:none;text-align:center;padding:20px 0">
                <div style="font-size:40px;margin-bottom:10px">🎉</div>
                <div style="font-size:18px;font-weight:800;color:#111827;margin-bottom:6px">FAQ 생성 완료!</div>
                <div id="faq-done-msg" style="font-size:14px;color:#6b7280;margin-bottom:16px"></div>
                <button class="faq-btn faq-btn-ghost" onclick="faqBulkReset()">다시 생성하기</button>
            </div>

        </div>
    </div>
</div>

<script>
async function faqBulkStart() {
    var limit = document.getElementById('faq-bulk-limit').value;
    if (!confirm('최근 ' + limit + '개 글에 FAQ를 생성합니다.\nAPI 비용이 발생합니다. 계속할까요?')) return;

    // UI 전환
    document.getElementById('faq-bulk-ready').style.display = 'none';
    document.getElementById('faq-bulk-progress').style.display = 'block';
    document.getElementById('faq-bulk-done').style.display = 'none';

    // 글 목록 가져오기
    var res = await fetch('/admin/plugin/ai-faq-generator/posts?limit=' + limit);
    var data = await res.json();
    if (!data.success || !data.posts.length) {
        alert('처리할 글이 없습니다.');
        faqBulkReset();
        return;
    }

    var posts = data.posts;
    var total = posts.length;
    var ok = 0, fail = 0;

    document.getElementById('faq-prog-count').textContent = '0 / ' + total;

    for (var i = 0; i < posts.length; i++) {
        var post = posts[i];
        document.getElementById('faq-prog-current').textContent = '처리 중: ' + post.title;
        document.getElementById('faq-prog-count').textContent = (i + 1) + ' / ' + total;
        document.getElementById('faq-prog-bar').style.width = Math.round(((i + 1) / total) * 100) + '%';

        try {
            var r = await fetch('/admin/plugin/ai-faq-generator/regen', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({post_id: post.id})
            });
            var d = await r.json();
            if (d.success) { ok++; } else { fail++; }
        } catch(e) { fail++; }

        document.getElementById('faq-prog-ok').textContent = ok;
        document.getElementById('faq-prog-fail').textContent = fail;
    }

    // 완료
    document.getElementById('faq-bulk-progress').style.display = 'none';
    document.getElementById('faq-bulk-done').style.display = 'block';
    document.getElementById('faq-done-msg').textContent = '총 ' + total + '개 처리 — 성공 ' + ok + '개 / 실패 ' + fail + '개';
}

function faqBulkReset() {
    document.getElementById('faq-bulk-ready').style.display = 'flex';
    document.getElementById('faq-bulk-progress').style.display = 'none';
    document.getElementById('faq-bulk-done').style.display = 'none';
    document.getElementById('faq-prog-bar').style.width = '0%';
    document.getElementById('faq-prog-ok').textContent = '0';
    document.getElementById('faq-prog-fail').textContent = '0';
    document.getElementById('faq-prog-current').textContent = '준비 중...';
}
</script>
