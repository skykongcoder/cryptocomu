<?php
/**
 * 카카오톡 채팅 버튼 플러그인
 *
 * 관리자 페이지에서 설정하세요: 관리자 > 플러그인 > 카카오톡 채팅 버튼 > 설정
 */

// 설정 파일에서 설정 읽기
$_configFile = __DIR__ . '/config.json';
$_configRaw = file_exists($_configFile) ? json_decode(file_get_contents($_configFile), true) : [];
if (!is_array($_configRaw)) $_configRaw = [];

$_config = array_merge([
    'kakao_chat_url' => 'https://pf.kakao.com/_example/chat',
    'message' => '카카오톡 상담',
    'bubble_text' => '',
    'bubble_show' => '0',
    'size' => '56',
    'bottom' => '24',
    'right' => '24',
    'hide_admin' => '1',
], $_configRaw);

// 카카오톡 URL이 없으면 버튼 표시 안 함
if (empty($_config['kakao_chat_url'])) return;

// 관리자 페이지에서 숨기기
if ($_config['hide_admin'] === '1' && function_exists('nb_isAdmin') && nb_isAdmin()) return;

// 사이트 하단에 플로팅 채팅 버튼 추가
Plugin::addHook('body_end', function() use ($_config) {
    $size = (int)$_config['size'];
    $bottom = (int)$_config['bottom'];
    $right = (int)$_config['right'];
    $url = htmlspecialchars($_config['kakao_chat_url']);
    $title = htmlspecialchars($_config['message']);
    $bubble = htmlspecialchars($_config['bubble_text']);
    $showBubble = $_config['bubble_show'] === '1';

    echo '
    <div style="position:fixed;bottom:' . $bottom . 'px;right:' . $right . 'px;z-index:9000;display:flex;flex-direction:column;align-items:flex-end;gap:6px;font-family:system-ui,-apple-system,sans-serif" class="kakao-chat-bubble-wrap">
        ' . ($showBubble && $bubble ? '
        <div style="background:#fff;color:#333;padding:8px 12px;border-radius:16px;font-size:12px;box-shadow:0 2px 8px rgba(0,0,0,.2);white-space:nowrap;animation:fadeInUp .3s ease;opacity:1" class="kakao-chat-bubble">
            ' . $bubble . '
        </div>
        ' : '') . '
        <a href="' . $url . '" target="_blank"
           style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;
                  background:#FEE500;display:flex;align-items:center;justify-content:center;
                  box-shadow:0 4px 12px rgba(0,0,0,.15);text-decoration:none;transition:transform .15s"
           onmouseover="this.style.transform=\'scale(1.1)\'" onmouseout="this.style.transform=\'scale(1)\'"
           title="' . $title . '"
           class="kakao-chat-btn">
            <svg width="' . round($size * 0.5) . '" height="' . round($size * 0.5) . '" viewBox="0 0 24 24" fill="#3C1E1E">
                <path d="M12 3C6.48 3 2 6.58 2 10.94c0 2.8 1.86 5.27 4.68 6.67l-1.19 4.44c-.04.16.12.3.27.22l5.14-3.39c.36.03.73.06 1.1.06 5.52 0 10-3.58 10-7.94S17.52 3 12 3z"/>
            </svg>
        </a>
    </div>
    <style>
    @keyframes fadeInUp { from { opacity:0;transform:translateY(10px) } to { opacity:1;transform:translateY(0) } }
    .kakao-chat-bubble { animation-delay:0s }
    </style>
    ';

    // 5초 후 말풍선 자동 숨김
    if ($showBubble && $bubble) {
        echo '
        <script>
        setTimeout(function() {
            var bubble = document.querySelector(".kakao-chat-bubble");
            if(bubble) {
                bubble.style.transition = "opacity .3s ease";
                bubble.style.opacity = "0";
                setTimeout(function() { bubble.style.display = "none"; }, 300);
            }
        }, 5000);
        </script>
        ';
    }
});
