<?php
/**
 * 조회수 부스터 - 설정 페이지
 */
$_vbConfigFile = __DIR__ . '/config.json';
$_vbConfigRaw = file_exists($_vbConfigFile) ? json_decode(file_get_contents($_vbConfigFile), true) : [];
if (!is_array($_vbConfigRaw)) $_vbConfigRaw = [];
$_vbConfig = array_merge([
    'multiplier' => 1,
    'minimum'    => 0,
    'variance'   => 0,
    'enabled'    => 1,
], $_vbConfigRaw);

$_vbFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vb_save'])) {
    $_vbConfig['multiplier'] = max(1, min(1000, (int)($_POST['multiplier'] ?? 1)));
    $_vbConfig['minimum']    = max(0, min(100000, (int)($_POST['minimum'] ?? 0)));
    $_vbConfig['variance']   = max(0, min(30, (int)($_POST['variance'] ?? 0)));
    $_vbConfig['enabled']    = !empty($_POST['enabled']) ? 1 : 0;
    file_put_contents($_vbConfigFile, json_encode($_vbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $_vbFlash = '저장되었습니다. 게시판 새로고침하면 즉시 적용됩니다.';
}

// 예시 계산 함수
function vb_calc($real, $mul, $min) {
    $v = $real * $mul;
    if ($v < $min) $v = $min;
    return $v;
}
?>

<style>
.vb-wrap { max-width: 720px; }
.vb-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.vb-head h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; }
.vb-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
.vb-pill.on { background: #f0fdf4; border: 1px solid #86efac; color: #16a34a; }
.vb-pill.off { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
.vb-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

.vb-flash { padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }

.vb-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
.vb-section-head { padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 15px; font-weight: 700; color: #111827; }
.vb-section-body { padding: 20px; }

.vb-field { margin-bottom: 18px; }
.vb-field:last-child { margin-bottom: 0; }
.vb-field label { display: block; font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 6px; }
.vb-field input[type="number"] { width: 120px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
.vb-field input[type="number"]:focus { outline: none; border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.1); }
.vb-hint { margin-top: 6px; font-size: 13px; color: #6b7280; line-height: 1.6; }

.vb-toggle { display: flex; align-items: center; gap: 12px; cursor: pointer; }
.vb-toggle input { width: 18px; height: 18px; accent-color: #16a34a; }
.vb-toggle b { font-size: 14px; color: #111827; }
.vb-toggle span { font-size: 12px; color: #6b7280; }

.vb-preview { margin-top: 14px; padding: 14px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
.vb-preview-title { font-size: 12px; font-weight: 700; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .04em; }
.vb-preview-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; border-bottom: 1px dashed #e5e7eb; }
.vb-preview-row:last-child { border-bottom: 0; }
.vb-preview-row .real { color: #6b7280; min-width: 70px; }
.vb-preview-row .arrow { color: #cbd5e1; }
.vb-preview-row .boosted { color: #16a34a; font-weight: 700; }

.vb-btn { padding: 10px 20px; background: #16a34a; color: #fff; border: 1px solid #16a34a; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
.vb-btn:hover { background: #15803d; }
</style>

<div class="vb-wrap">
    <div class="vb-head">
        <h2>조회수 부스터</h2>
        <?php if ($_vbConfig['enabled']): ?>
        <span class="vb-pill on"><span class="dot"></span> 적용 중</span>
        <?php else: ?>
        <span class="vb-pill off"><span class="dot"></span> 비활성</span>
        <?php endif; ?>
    </div>

    <?php if ($_vbFlash): ?>
    <div class="vb-flash"><?= htmlspecialchars($_vbFlash) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="vb_save" value="1">

        <div class="vb-section">
            <div class="vb-section-head">기본 설정</div>
            <div class="vb-section-body">
                <div class="vb-field">
                    <label class="vb-toggle">
                        <input type="checkbox" name="enabled" value="1" <?= $_vbConfig['enabled'] ? 'checked' : '' ?>>
                        <div>
                            <b>조회수 부스터 사용</b><br>
                            <span>체크 해제하면 원본 조회수 그대로 표시됩니다.</span>
                        </div>
                    </label>
                </div>

                <div class="vb-field">
                    <label>배수 (1~1000)</label>
                    <input type="number" name="multiplier" value="<?= (int)$_vbConfig['multiplier'] ?>" min="1" max="1000">
                    <p class="vb-hint">실제 조회수에 이 숫자를 곱합니다. 예: 실제 5회 × 배수 20 = 화면 표시 100회</p>
                </div>

                <div class="vb-field">
                    <label>최소 조회수 (0~100000)</label>
                    <input type="number" name="minimum" value="<?= (int)$_vbConfig['minimum'] ?>" min="0" max="100000">
                    <p class="vb-hint">
                        조회수가 이 값보다 적으면 이 값으로 올립니다. 예: 최소 50 설정 시, 실제 0회인 글도 화면에는 50회 표시.<br>
                        <b>추천</b>: 초기 커뮤니티는 50~200 사이, 안정기에는 0 또는 낮은 값.
                    </p>
                </div>

                <div class="vb-field">
                    <label>랜덤 변동폭 % (0~30)</label>
                    <input type="number" name="variance" value="<?= (int)$_vbConfig['variance'] ?>" min="0" max="30">
                    <p class="vb-hint">
                        글마다 ±N% 범위에서 숫자를 살짝 변경해 어색함을 줄입니다. 같은 글은 항상 같은 값이 나오므로 새로고침해도 숫자가 튀지 않습니다.<br>
                        <b>추천</b>: 10~20 정도. 0으로 두면 모든 글이 정확히 같은 배율이라 티가 날 수 있습니다.
                    </p>
                </div>

                <!-- 미리보기 -->
                <div class="vb-preview">
                    <div class="vb-preview-title">미리보기 (현재 설정 기준)</div>
                    <div class="vb-preview-row"><span class="real">실제 0회</span><span class="arrow">→</span><span class="boosted"><?= number_format(vb_calc(0, $_vbConfig['multiplier'], $_vbConfig['minimum'])) ?>회</span></div>
                    <div class="vb-preview-row"><span class="real">실제 1회</span><span class="arrow">→</span><span class="boosted"><?= number_format(vb_calc(1, $_vbConfig['multiplier'], $_vbConfig['minimum'])) ?>회</span></div>
                    <div class="vb-preview-row"><span class="real">실제 5회</span><span class="arrow">→</span><span class="boosted"><?= number_format(vb_calc(5, $_vbConfig['multiplier'], $_vbConfig['minimum'])) ?>회</span></div>
                    <div class="vb-preview-row"><span class="real">실제 20회</span><span class="arrow">→</span><span class="boosted"><?= number_format(vb_calc(20, $_vbConfig['multiplier'], $_vbConfig['minimum'])) ?>회</span></div>
                    <div class="vb-preview-row"><span class="real">실제 100회</span><span class="arrow">→</span><span class="boosted"><?= number_format(vb_calc(100, $_vbConfig['multiplier'], $_vbConfig['minimum'])) ?>회</span></div>
                </div>
            </div>
        </div>

        <div class="vb-section">
            <div class="vb-section-head">추천 조합</div>
            <div class="vb-section-body">
                <p class="vb-hint" style="margin:0 0 10px">상황별 추천 설정값:</p>
                <table style="width:100%;font-size:13px;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f9fafb">
                            <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb">상황</th>
                            <th style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb">배수</th>
                            <th style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb">최소값</th>
                            <th style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb">변동폭</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:8px">오픈 직후 (회원 0~50)</td><td style="padding:8px;text-align:center">20</td><td style="padding:8px;text-align:center">150</td><td style="padding:8px;text-align:center">20</td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:8px">성장기 (회원 50~500)</td><td style="padding:8px;text-align:center">10</td><td style="padding:8px;text-align:center">80</td><td style="padding:8px;text-align:center">15</td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:8px">안정기 (회원 500~)</td><td style="padding:8px;text-align:center">3</td><td style="padding:8px;text-align:center">20</td><td style="padding:8px;text-align:center">10</td></tr>
                        <tr><td style="padding:8px">부스터 해제 (완전 실제)</td><td style="padding:8px;text-align:center">1</td><td style="padding:8px;text-align:center">0</td><td style="padding:8px;text-align:center">0</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vb-section">
            <div class="vb-section-head">주의사항</div>
            <div class="vb-section-body">
                <ul style="font-size:13px;color:#4b5563;line-height:2;margin:0;padding-left:20px">
                    <li>소스 보기(Ctrl+U)에는 원본 조회수가 보입니다. 화면에만 배수가 적용됩니다.</li>
                    <li>DB의 실제 조회수는 전혀 변경되지 않습니다. 해제 시 즉시 원본으로 복귀합니다.</li>
                    <li>회원이 자신의 글 관리에서 보는 수치와 메인 화면 수치가 다를 수 있습니다.</li>
                    <li>과한 배수(50배 이상)는 부자연스러워 보일 수 있으니 점진적으로 낮춰 가세요.</li>
                    <li>검색엔진 크롤링은 원본 HTML을 수집하므로 SEO에는 영향이 없습니다.</li>
                </ul>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:16px">
            <button type="submit" class="vb-btn">설정 저장</button>
        </div>
    </form>
</div>
