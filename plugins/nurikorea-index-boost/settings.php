<?php
/**
 * 누리코리아 색인 가속기 - 관리자 설정 페이지
 * - 상태 및 통계 표시
 * - IndexNow / Google 활성화 토글
 * - Google Service Account JSON 업로드
 * - 수동 전체 재제출 버튼
 */

require_once __DIR__ . '/plugin.php';

$nib_flash = '';

// --- POST 처리 ---
$nib_google_test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nib_act'])) {
    $act = $_POST['nib_act'];

    if ($act === 'save') {
        nib_set_setting('nib_enabled_indexnow', !empty($_POST['nib_enabled_indexnow']) ? '1' : '0');
        nib_set_setting('nib_enabled_google',   !empty($_POST['nib_enabled_google'])   ? '1' : '0');

        // Google JSON 업로드 처리
        if (!empty($_FILES['nib_google_key_json']['tmp_name'])) {
            $content = file_get_contents($_FILES['nib_google_key_json']['tmp_name']);
            $parsed = json_decode($content, true);
            if (is_array($parsed) && !empty($parsed['private_key']) && !empty($parsed['client_email'])) {
                nib_set_setting('nib_google_key_json', $content);
                $nib_flash = '저장 완료. Google Service Account 키도 업로드됨.';
            } else {
                $nib_flash = '⚠️ 저장됨. 단, 업로드한 JSON 이 올바르지 않아 Google 기능은 작동 안 함.';
            }
        } else {
            $nib_flash = '저장 완료.';
        }
    }
    elseif ($act === 'delete_google_key') {
        nib_set_setting('nib_google_key_json', '');
        $nib_flash = 'Google 키를 삭제했습니다.';
    }
    elseif ($act === 'google_test') {
        $test_url = trim($_POST['google_test_url'] ?? '');
        if ($test_url && preg_match('#^https?://#', $test_url)) {
            $nib_google_test_result = nib_submit_google($test_url, 'URL_UPDATED');
        } else {
            $nib_google_test_result = ['ok' => false, 'error' => 'URL을 올바르게 입력해주세요.'];
        }
    }
}

// --- 현재 값 ---
$enabled_indexnow = nib_get_setting('nib_enabled_indexnow', '1') === '1';
$enabled_google   = nib_get_setting('nib_enabled_google', '0') === '1';
$has_google_key   = nib_get_setting('nib_google_key_json', '') !== '';
$indexnow_key     = nib_get_setting('nib_indexnow_key', '');

// --- 제출 통계 (최근 24h) ---
$log_file = __DIR__ . '/submissions.log';
$stats = ['total' => 0, 'in_success' => 0, 'in_fail' => 0, 'gl_success' => 0, 'gl_fail' => 0, 'avg_ms' => 0, 'recent' => []];
if (is_file($log_file)) {
    $cutoff = strtotime('-24 hours');
    $lines = @file($log_file, FILE_IGNORE_NEW_LINES);
    $recent = array_slice(array_reverse($lines ?: []), 0, 100);
    foreach ($recent as $ln) {
        $e = json_decode($ln, true);
        if (!$e) continue;
        if (!empty($e['at']) && strtotime($e['at']) >= $cutoff) {
            $stats['total']++;
            if (!empty($e['indexnow']['ok'])) $stats['in_success']++;
            elseif (isset($e['indexnow'])) $stats['in_fail']++;
            if (!empty($e['google']['ok'])) $stats['gl_success']++;
            elseif (isset($e['google']) && empty($e['google']['skipped'])) $stats['gl_fail']++;
        }
        $stats['recent'][] = $e;
    }
}
?>

<style>
.nib-wrap { max-width: 820px; }
.nib-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.nib-head h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em; }
.nib-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 20px; font-size: 13px; color: #16a34a; font-weight: 600; }
.nib-status-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; animation: nib-pulse 2s infinite; }
@keyframes nib-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.nib-flash { padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }

.nib-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.nib-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; text-align: center; }
.nib-stat-num { font-size: 24px; font-weight: 800; color: #111827; line-height: 1; }
.nib-stat-num.ok { color: #16a34a; }
.nib-stat-num.fail { color: #dc2626; }
.nib-stat-lbl { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-top: 6px; }

.nib-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
.nib-section-head { padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 10px; }
.nib-section-head svg { color: #16a34a; flex-shrink: 0; }
.nib-section-head h3 { font-size: 15px; font-weight: 700; color: #111827; margin: 0; }
.nib-section-body { padding: 20px; }

.nib-toggle { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px; cursor: pointer; }
.nib-toggle:hover { background: #f3f4f6; }
.nib-toggle input[type="checkbox"] { width: 18px; height: 18px; accent-color: #16a34a; cursor: pointer; }
.nib-toggle-body { flex: 1; }
.nib-toggle-body b { display: block; font-size: 14px; color: #111827; }
.nib-toggle-body span { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }

.nib-engines { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
.nib-engine { padding: 4px 10px; background: #dcfce7; color: #16a34a; border-radius: 4px; font-size: 11px; font-weight: 700; }

.nib-field { margin-bottom: 16px; }
.nib-field label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.nib-field input[type="file"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 14px; }
.nib-hint { margin-top: 6px; font-size: 12px; color: #6b7280; line-height: 1.5; }
.nib-hint code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 11px; color: #374151; }

.nib-key-info { padding: 10px 14px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; font-size: 12px; color: #15803d; font-family: monospace; word-break: break-all; }

.nib-log { margin-top: 16px; }
.nib-log table { width: 100%; border-collapse: collapse; font-size: 12px; }
.nib-log th, .nib-log td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f1f5f9; }
.nib-log th { background: #f9fafb; font-weight: 700; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
.nib-log .ok { color: #16a34a; font-weight: 700; }
.nib-log .fail { color: #dc2626; font-weight: 700; }
.nib-log .url { color: #1e40af; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; }

.nib-submit { position: sticky; bottom: 0; background: #fff; padding: 16px 0; margin-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.nib-btn { padding: 10px 20px; background: #16a34a; color: #fff; border: 1px solid #16a34a; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
.nib-btn:hover { background: #15803d; }
.nib-btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
.nib-btn-ghost:hover { background: #f3f4f6; }
.nib-btn-danger { background: #fff; color: #dc2626; border-color: #fecaca; }
.nib-btn-danger:hover { background: #fef2f2; }

/* ===== 친절 가이드 박스 ===== */
.nib-guide {
    margin-top: 14px;
    padding: 16px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}
.nib-guide-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #16a34a;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}
.nib-guide-head b { font-weight: 700; }

.nib-guide-step {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}
.nib-guide-step:last-of-type { margin-bottom: 0; }

.nib-guide-num {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    background: #16a34a;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
}

.nib-guide-body {
    flex: 1;
    font-size: 13px;
    line-height: 1.65;
    color: #374151;
}
.nib-guide-body b {
    display: block;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
}
.nib-guide-body p {
    margin: 0 0 6px;
    color: #4b5563;
}
.nib-guide-body p:last-child { margin-bottom: 0; }
.nib-guide-body a {
    color: #16a34a;
    text-decoration: underline;
    font-weight: 600;
}
.nib-guide-body code {
    background: #e5e7eb;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: 'Consolas', monospace;
    font-size: 11px;
    color: #374151;
}

.nib-code-sample {
    background: #111827;
    color: #e5e7eb;
    padding: 10px 14px;
    border-radius: 6px;
    font-family: 'Consolas', monospace;
    font-size: 12px;
    margin: 8px 0;
    overflow-x: auto;
    white-space: nowrap;
}
.nib-code-sample .nib-hl {
    background: #fbbf24;
    color: #111827;
    padding: 1px 4px;
    border-radius: 3px;
    font-weight: 700;
}

.nib-warn-box {
    margin-top: 14px;
    padding: 12px 14px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    border-radius: 8px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: 12px;
    color: #9a3412;
    line-height: 1.6;
}
.nib-warn-box b {
    display: block;
    margin-bottom: 4px;
    color: #7c2d12;
}
.nib-warn-box ul {
    margin: 0;
    padding-left: 16px;
}
.nib-warn-box li { margin-bottom: 3px; }
.nib-warn-box code {
    background: #fde68a;
    padding: 1px 5px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
    color: #713f12;
}
</style>

<div class="nib-wrap">
    <div class="nib-head">
        <h2>누리코리아 색인 가속기</h2>
        <?php if ($enabled_indexnow || $enabled_google): ?>
        <span class="nib-status-pill"><span class="dot"></span> 자동 제출 중</span>
        <?php else: ?>
        <span class="nib-status-pill" style="background:#fef3c7;border-color:#fcd34d;color:#92400e"><span class="dot" style="background:#f59e0b"></span> 비활성</span>
        <?php endif; ?>
    </div>

    <?php if ($nib_flash): ?>
    <div class="nib-flash">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($nib_flash) ?>
    </div>
    <?php endif; ?>

    <!-- ===== 최근 24h 통계 ===== -->
    <div class="nib-stats">
        <div class="nib-stat">
            <div class="nib-stat-num"><?= $stats['total'] ?></div>
            <div class="nib-stat-lbl">24시간 제출</div>
        </div>
        <div class="nib-stat">
            <div class="nib-stat-num ok"><?= $stats['in_success'] ?></div>
            <div class="nib-stat-lbl">IndexNow 성공</div>
        </div>
        <div class="nib-stat">
            <div class="nib-stat-num <?= $stats['in_fail']>0?'fail':'' ?>"><?= $stats['in_fail'] ?></div>
            <div class="nib-stat-lbl">IndexNow 실패</div>
        </div>
        <div class="nib-stat">
            <div class="nib-stat-num ok"><?= $stats['gl_success'] ?></div>
            <div class="nib-stat-lbl">Google 성공</div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="nib_act" value="save">

        <!-- ===== 섹션 1: IndexNow ===== -->
        <div class="nib-section">
            <div class="nib-section-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                <h3>IndexNow · 즉시 색인 요청 (권장)</h3>
            </div>
            <div class="nib-section-body">
                <label class="nib-toggle">
                    <input type="checkbox" name="nib_enabled_indexnow" value="1" <?= $enabled_indexnow?'checked':'' ?>>
                    <div class="nib-toggle-body">
                        <b>IndexNow 자동 제출 사용</b>
                        <span>새 글 작성 및 기존 글 수정 시 자동으로 검색엔진에 색인 요청</span>
                        <div class="nib-engines">
                            <span class="nib-engine">네이버</span>
                            <span class="nib-engine">Bing</span>
                            <span class="nib-engine">Yandex</span>
                            <span class="nib-engine">Seznam</span>
                        </div>
                    </div>
                </label>

                <?php if ($indexnow_key): ?>
                <div style="margin-top:14px">
                    <label style="font-size:12px;color:#6b7280;font-weight:600">발급된 인증 키 (자동 생성됨)</label>
                    <div class="nib-key-info"><?= htmlspecialchars($indexnow_key) ?></div>
                    <p class="nib-hint">이 키는 사이트 루트의 <code><?= htmlspecialchars($indexnow_key) ?>.txt</code> 파일로도 배치되어 있습니다. (IndexNow 검증용)</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== 섹션 2: Google Indexing API (옵션) ===== -->
        <div class="nib-section">
            <div class="nib-section-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
                <h3>Google Indexing API (고급, 선택)</h3>
            </div>
            <div class="nib-section-body">
                <label class="nib-toggle">
                    <input type="checkbox" name="nib_enabled_google" value="1" <?= $enabled_google?'checked':'' ?> <?= !$has_google_key?'disabled':'' ?>>
                    <div class="nib-toggle-body">
                        <b>Google Indexing API 사용 <?= !$has_google_key?'<span style="color:#dc2626;font-size:11px;margin-left:6px">키 업로드 필요</span>':'' ?></b>
                        <span>Service Account JSON 키가 필요합니다. 설정 안 해도 sitemap.xml 로 Google 자동 색인됨 (이 옵션은 부가)</span>
                    </div>
                </label>

                <div class="nib-field" style="margin-top:14px">
                    <label>Service Account JSON 키 파일 <?= $has_google_key?'<span style="color:#16a34a;font-size:11px">● 업로드됨</span>':'' ?></label>
                    <input type="file" name="nib_google_key_json" accept=".json">

                    <div class="nib-guide">
                        <div class="nib-guide-head">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <b>처음이신가요? 3단계로 끝납니다</b>
                        </div>

                        <div class="nib-guide-step">
                            <div class="nib-guide-num">1</div>
                            <div class="nib-guide-body">
                                <b>Service Account 만들기</b>
                                <p><a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">Google Cloud Console</a> 접속
                                    → 프로젝트 생성 → 좌측 메뉴 [IAM 및 관리] → [서비스 계정]
                                    → [서비스 계정 만들기] → 이름 입력 후 저장
                                    → 만든 계정 클릭 → [키] 탭 → [키 추가] → [JSON]
                                    → <b>파일 자동 다운로드됨</b></p>
                            </div>
                        </div>

                        <div class="nib-guide-step">
                            <div class="nib-guide-num">2</div>
                            <div class="nib-guide-body">
                                <b>JSON 파일 안에서 이메일 찾기</b>
                                <p>다운로드된 JSON 파일을 메모장으로 열면 아래 같은 줄이 있어요:</p>
                                <div class="nib-code-sample">
                                    "client_email": "<span class="nib-hl">abcd-123@내프로젝트이름.iam.gserviceaccount.com</span>",
                                </div>
                                <p>👉 <b>따옴표 안의 이메일 전체</b>를 복사하세요. (<code>@...gserviceaccount.com</code> 으로 끝남)</p>
                            </div>
                        </div>

                        <div class="nib-guide-step">
                            <div class="nib-guide-num">3</div>
                            <div class="nib-guide-body">
                                <b>Web Search Indexing API 활성화</b>
                                <p><a href="https://console.cloud.google.com/apis/library/indexing.googleapis.com" target="_blank">Google Cloud Console → API 라이브러리</a> 접속
                                    → <b>Web Search Indexing API</b> 검색
                                    → <b>[사용 설정]</b> 버튼 클릭 → <b>API Enabled</b> 표시 확인</p>
                            </div>
                        </div>

                        <div class="nib-guide-step">
                            <div class="nib-guide-num">4</div>
                            <div class="nib-guide-body">
                                <b>Search Console에 소유자로 추가</b>
                                <p><a href="https://search.google.com/search-console" target="_blank">Google Search Console</a> 접속
                                    → 내 사이트 속성 선택 → 좌측 하단 <b>[설정]</b> (톱니바퀴)
                                    → <b>[사용자 및 권한]</b> → 우측 상단 <b>[사용자 추가]</b>
                                    → 방금 복사한 이메일 붙여넣기
                                    → <b>권한: "소유자"</b> 선택 (⚠️ "전체" 아니라 "소유자")
                                    → [추가]</p>
                                <p style="color:#16a34a;font-weight:600">✅ 완료! 이제 위 [파일 선택]으로 JSON 업로드하고 저장하세요.</p>
                            </div>
                        </div>

                        <div class="nib-warn-box">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <div>
                                <b>중요 안내</b>
                                <ul>
                                    <li>Google Indexing API 는 공식적으로 <b>채용공고/라이브 스트림</b> 사이트용입니다. 일반 블로그에서도 작동하지만 구글이 언제 막을지 모릅니다.</li>
                                    <li><b>필수 아님</b> — 이 옵션 없어도 sitemap.xml 로 구글 색인이 자동 됩니다 (3~14일).</li>
                                    <li>JSON 파일의 <code>private_key</code> 는 비밀번호와 같습니다. <b>타인과 공유 금지</b>.</li>
                                    <li>하루 할당량: 프로젝트당 200건 (블로그엔 충분).</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($has_google_key): ?>
                <button type="button" class="nib-btn nib-btn-danger" style="padding:6px 12px;font-size:12px" onclick="if(confirm('Google 키를 삭제할까요?')){document.getElementById('nib-del-key-form').submit();}">저장된 Google 키 삭제</button>
                <?php endif; ?>
            </div>
        </div>


        <div class="nib-submit">
            <p class="nib-hint" style="margin:0">변경사항은 저장 후 즉시 적용됩니다.</p>
            <button type="submit" class="nib-btn">설정 저장</button>
        </div>
    </form>

    <!-- Google 연결 테스트 -->
    <div class="nib-section" style="margin-top:16px">
        <div class="nib-section-head">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h3>Google 연결 테스트</h3>
        </div>
        <div class="nib-section-body">
            <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="nib_act" value="google_test">
                <div style="flex:1;min-width:240px">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">테스트할 URL (예: https://nuribd.com/board/free/128)</label>
                    <input type="url" name="google_test_url" required placeholder="https://nuribd.com/board/..." style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px">
                </div>
                <button type="submit" class="nib-btn nib-btn-ghost">Google 테스트 전송</button>
            </form>
            <?php if ($nib_google_test_result !== null): ?>
            <div style="margin-top:14px;padding:14px;border-radius:8px;background:<?= !empty($nib_google_test_result['ok']) ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= !empty($nib_google_test_result['ok']) ? '#bbf7d0' : '#fecaca' ?>">
                <b style="font-size:13px;color:<?= !empty($nib_google_test_result['ok']) ? '#15803d' : '#dc2626' ?>"><?= !empty($nib_google_test_result['ok']) ? '✓ 성공' : '✗ 실패' ?></b>
                <pre style="margin:8px 0 0;font-size:11px;color:#374151;white-space:pre-wrap;word-break:break-all;background:#f9fafb;padding:10px;border-radius:6px;border:1px solid #e5e7eb"><?= htmlspecialchars(json_encode($nib_google_test_result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 삭제 / 재제출용 별도 폼 -->
    <form id="nib-del-key-form" method="post" style="display:none">
        <input type="hidden" name="nib_act" value="delete_google_key">
    </form>

    <!-- ===== 최근 제출 로그 ===== -->
    <?php if (!empty($stats['recent'])): ?>
    <div class="nib-section">
        <div class="nib-section-head">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <h3>최근 제출 내역 (최근 100건)</h3>
        </div>
        <div class="nib-log">
            <table>
                <thead>
                    <tr>
                        <th style="width:130px">시각</th>
                        <th>URL</th>
                        <th style="width:120px">IndexNow</th>
                        <th style="width:90px">Google</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['recent'] as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('m-d H:i', strtotime($e['at'] ?? ''))) ?></td>
                        <td><a href="<?= htmlspecialchars($e['url'] ?? '#') ?>" target="_blank" class="url"><?= htmlspecialchars(parse_url($e['url'] ?? '', PHP_URL_PATH) ?: '—') ?></a></td>
                        <td>
                            <?php if (!empty($e['indexnow'])): ?>
                                <?php if (!empty($e['indexnow']['ok'])): ?>
                                    <span class="ok">✓ 성공 (<?= (int)$e['indexnow']['http'] ?>)</span>
                                <?php else: ?>
                                    <span class="fail">✗ 실패 (<?= (int)($e['indexnow']['http'] ?? 0) ?>)</span>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($e['google'])): ?>
                                <?php if (!empty($e['google']['ok'])): ?>
                                    <?php if (isset($e['google']['success'])): ?>
                                        <span class="ok">✓ <?= (int)$e['google']['success'] ?>개 성공</span>
                                    <?php else: ?>
                                        <span class="ok">✓ 성공</span>
                                    <?php endif; ?>
                                <?php elseif (!empty($e['google']['skipped'])): ?>
                                    —
                                <?php else: ?>
                                    <?php $gtitle = htmlspecialchars($e['google']['error'] ?? $e['google']['resp'] ?? ''); ?>
                                    <?php if (isset($e['google']['success'])): ?>
                                        <span class="fail" title="<?= $gtitle ?>">✗ 성공<?= (int)$e['google']['success'] ?>/실패<?= (int)$e['google']['fail'] ?></span>
                                    <?php else: ?>
                                        <span class="fail" title="<?= $gtitle ?>">✗ 실패 (<?= (int)($e['google']['http'] ?? 0) ?>)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
