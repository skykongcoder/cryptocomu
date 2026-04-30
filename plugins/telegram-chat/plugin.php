<?php
/**
 * 텔레그램 채팅 버튼 플러그인
 * 우측 하단 플로팅 버튼으로 텔레그램 1:1 채팅 연결
 */

$_tgConfigFile = __DIR__ . '/config.json';
$_tgConfigRaw = file_exists($_tgConfigFile) ? json_decode(file_get_contents($_tgConfigFile), true) : [];
if (!is_array($_tgConfigRaw)) $_tgConfigRaw = [];
$_tgConfig = array_merge([
    'username' => '',
    'message' => '문의하기',
    'bubble_text' => '무엇이든 물어보세요!',
    'bubble_show' => '1',
    'color' => '#0088cc',
    'size' => '56',
    'bottom' => '24',
    'right' => '24',
    'hide_admin' => '1',
], $_tgConfigRaw);

// 텔레그램 아이디 미설정 시 미표시
if (empty($_tgConfig['username'])) return;

// 관리자 페이지에서 숨기기
if ($_tgConfig['hide_admin'] === '1' && strpos($_SERVER['REQUEST_URI'], '/admin') !== false) return;

Plugin::addHook('body_end', function() use ($_tgConfig) {
    $username = htmlspecialchars($_tgConfig['username']);
    $message = htmlspecialchars($_tgConfig['message']);
    $bubbleText = htmlspecialchars($_tgConfig['bubble_text']);
    $showBubble = $_tgConfig['bubble_show'] === '1';
    $color = htmlspecialchars($_tgConfig['color']);
    $size = (int)$_tgConfig['size'];
    $bottom = (int)$_tgConfig['bottom'];
    $right = (int)$_tgConfig['right'];
    $iconSize = round($size * 0.5);
?>
<!-- 텔레그램 채팅 버튼 -->
<div id="tg-chat-wrap" style="position:fixed;bottom:<?=$bottom?>px;right:<?=$right?>px;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:8px">
    <?php if ($showBubble && $bubbleText): ?>
    <div id="tg-bubble" style="background:#fff;color:#333;padding:10px 16px;border-radius:20px;box-shadow:0 2px 12px rgba(0,0,0,.15);font-size:13px;max-width:220px;position:relative;opacity:1;transition:opacity .3s">
        <?=$bubbleText?>
        <button onclick="this.parentElement.style.display='none'" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#e2e8f0;border:none;cursor:pointer;font-size:11px;line-height:20px;text-align:center;color:#64748b">&times;</button>
    </div>
    <?php endif; ?>
    <a href="https://t.me/<?=$username?>" target="_blank" rel="noopener" title="<?=$message?>" id="tg-chat-btn" style="display:flex;align-items:center;justify-content:center;width:<?=$size?>px;height:<?=$size?>px;border-radius:50%;background:<?=$color?>;box-shadow:0 4px 16px rgba(0,0,0,.2);cursor:pointer;transition:transform .2s;text-decoration:none">
        <svg width="<?=$iconSize?>" height="<?=$iconSize?>" viewBox="0 0 24 24" fill="#fff"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
    </a>
</div>
<style>
#tg-chat-btn:hover{transform:scale(1.1)}
@media(max-width:768px){
    #tg-chat-wrap{bottom:<?=max($bottom-8,12)?>px;right:<?=max($right-8,12)?>px}
    #tg-bubble{font-size:12px;max-width:180px}
}
</style>
<?php if ($showBubble && $bubbleText): ?>
<script>
setTimeout(function(){var b=document.getElementById('tg-bubble');if(b){b.style.opacity='0';setTimeout(function(){b.style.display='none'},300)}},5000);
</script>
<?php endif; ?>
<?php
});
