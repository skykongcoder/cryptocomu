<?php
/**
 * 카카오/텔레그램 상담 플로팅 버튼
 *
 * 사이트 구석에 문의 버튼 띄워서 바로 상담 연결
 */

$_cp_config_file = __DIR__ . '/config.json';
$_cp_config_raw = file_exists($_cp_config_file) ? json_decode(file_get_contents($_cp_config_file), true) : [];
if (!is_array($_cp_config_raw)) $_cp_config_raw = [];

$_cp_config = array_merge([
    'kakao_enabled' => '0',
    'kakao_url' => '',
    'kakao_image' => '',
    'telegram_enabled' => '0',
    'telegram_url' => '',
    'telegram_image' => '',
    'position' => 'bottom-right',
    'size' => 'medium',
    'show_on_admin' => '0',
], $_cp_config_raw);

// ===== 플러그인 폴더 URL 계산 =====
function _cp_get_plugin_url() {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $pluginDir = __DIR__;
    if ($docRoot && strpos($pluginDir, $docRoot) === 0) {
        return str_replace('\\', '/', substr($pluginDir, strlen($docRoot)));
    }
    // fallback: plugins 폴더 기준
    if (preg_match('~(/plugins/[^/]+)$~', str_replace('\\', '/', $pluginDir), $m)) {
        return $m[1];
    }
    return '/plugins/' . basename($pluginDir);
}

// ===== Hook: body_end에 플로팅 버튼 출력 =====
Plugin::addHook('body_end', function() use ($_cp_config) {
    $kakaoOn = $_cp_config['kakao_enabled'] === '1' && !empty($_cp_config['kakao_url']);
    $telegramOn = $_cp_config['telegram_enabled'] === '1' && !empty($_cp_config['telegram_url']);

    if (!$kakaoOn && !$telegramOn) return;

    // 관리자 페이지 제외
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($_cp_config['show_on_admin'] !== '1' && strpos($uri, '/admin') !== false) return;

    $pluginUrl = _cp_get_plugin_url();

    // 크기
    $sizes = ['small' => 60, 'medium' => 80, 'large' => 100, 'xlarge' => 120];
    $px = $sizes[$_cp_config['size']] ?? 80;

    // 위치 CSS (하단: 조금 더 위로, 중앙: 출석체크 겹침 방지로 200px 내림)
    $posCss = [
        'bottom-right' => 'bottom:60px;right:25px;',
        'bottom-left'  => 'bottom:60px;left:25px;',
        'middle-right' => 'top:calc(50% + 200px);right:25px;transform:translateY(-50%);',
        'middle-left'  => 'top:calc(50% + 200px);left:25px;transform:translateY(-50%);',
    ];
    $posStyle = $posCss[$_cp_config['position']] ?? $posCss['bottom-right'];

    // 기본 이미지 경로
    $kakaoImg = !empty($_cp_config['kakao_image']) ? $_cp_config['kakao_image'] : $pluginUrl . '/assets/kakao.png';
    $telegramImg = !empty($_cp_config['telegram_image']) ? $_cp_config['telegram_image'] : $pluginUrl . '/assets/telegram.png';

    ?>
    <style>
    .cp-float-wrap {
        position: fixed;
        <?= $posStyle ?>
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 14px;
        pointer-events: none;
    }
    .cp-float-item {
        position: relative;
        pointer-events: auto;
    }
    .cp-float-btn {
        display: block;
        width: <?= $px ?>px;
        height: <?= $px ?>px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.18);
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
        text-decoration: none;
    }
    .cp-float-btn:hover {
        transform: translateY(-3px) scale(1.03);
        box-shadow: 0 8px 28px rgba(0,0,0,0.25);
    }
    .cp-float-btn img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
    }
    .cp-close-btn {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 24px;
        height: 24px;
        background: #1e293b;
        color: white;
        border-radius: 50%;
        border: 2px solid white;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        padding: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        transition: background 0.2s, transform 0.2s;
    }
    .cp-close-btn:hover {
        background: #dc2626;
        transform: scale(1.1);
    }
    .cp-float-item.cp-hidden {
        display: none;
    }
    @media (max-width: 640px) {
        .cp-float-btn { width: <?= max(55, $px - 15) ?>px !important; height: <?= max(55, $px - 15) ?>px !important; }
    }
    </style>

    <div class="cp-float-wrap" id="cp-float-wrap">
        <?php if ($kakaoOn): ?>
            <div class="cp-float-item" data-cp-id="kakao">
                <a href="<?= htmlspecialchars($_cp_config['kakao_url']) ?>" target="_blank" rel="noopener" class="cp-float-btn" title="카카오톡 상담">
                    <img src="<?= htmlspecialchars($kakaoImg) ?>" alt="카카오톡 상담">
                </a>
                <button type="button" class="cp-close-btn" onclick="cpCloseBtn('kakao')" title="닫기">×</button>
            </div>
        <?php endif; ?>
        <?php if ($telegramOn): ?>
            <div class="cp-float-item" data-cp-id="telegram">
                <a href="<?= htmlspecialchars($_cp_config['telegram_url']) ?>" target="_blank" rel="noopener" class="cp-float-btn" title="텔레그램 상담">
                    <img src="<?= htmlspecialchars($telegramImg) ?>" alt="텔레그램 상담">
                </a>
                <button type="button" class="cp-close-btn" onclick="cpCloseBtn('telegram')" title="닫기">×</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        // 페이지 로드 시 이전에 닫힌 버튼 숨김 (sessionStorage 기준 — 탭 닫으면 초기화)
        document.querySelectorAll('.cp-float-item').forEach(function(item) {
            var id = item.getAttribute('data-cp-id');
            if (sessionStorage.getItem('cp_closed_' + id) === '1') {
                item.classList.add('cp-hidden');
            }
        });
    })();

    function cpCloseBtn(id) {
        var item = document.querySelector('.cp-float-item[data-cp-id="' + id + '"]');
        if (item) {
            item.classList.add('cp-hidden');
            sessionStorage.setItem('cp_closed_' + id, '1');
        }
    }
    </script>
    <?php
});
