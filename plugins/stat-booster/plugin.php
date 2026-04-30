<?php
/**
 * 통계 부스터 플러그인
 * 커뮤니티 현황(stats-v2) 수치에 배수를 적용합니다.
 *
 * 실제 HTML 구조 (누리보드 main.php):
 *   .stats-v2-hero .stats-v2-live    → 현재 접속자
 *   .stats-v2-hero .stats-v2-joined  → 전체 회원
 *   .stats-v2-mid-card (1)            → 오늘 새 글
 *   .stats-v2-mid-card (2)            → 오늘 새 댓글
 *   .stats-v2-list-row (1)            → 전체 게시물
 *   .stats-v2-list-row (2)            → 전체 댓글
 *   .stats-v2-list-row (3)            → 전체 회원
 */

Plugin::addHook('body_end', function () {
    $configFile = __DIR__ . '/config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $config = array_merge([
        'online' => 1,
        'today_posts' => 1,
        'today_comments' => 1,
        'today_members' => 1,    // 현재 테마에는 해당 항목 없음 (향후 대비)
        'total_posts' => 1,
        'total_comments' => 1,
        'total_members' => 1,
    ], $config);

    // 배수 1인 항목만 있으면 아예 스크립트 출력 안 함 (최적화)
    $hasAnyMultiplier = false;
    foreach ($config as $v) { if ((int)$v > 1) { $hasAnyMultiplier = true; break; } }
    if (!$hasAnyMultiplier) return;
    ?>
    <script>
    (function(){
        var M = <?= json_encode($config) ?>;

        // 배수 계산: 실제 값이 0이면 배수를 그대로 사용, 아니면 실제값 × 배수
        function calc(num, mul){
            return (num === 0 ? mul : num * mul);
        }

        // 숫자 추출 → 배수 적용 → 포맷 유지 (단위/쉼표)
        function boost(el, mul){
            if(!el || !mul || mul <= 1) return;
            var text = (el.textContent || '').trim();
            // 숫자 부분 추출 (쉼표 포함, 0도 매칭되게 [0-9]+ 로)
            var m = text.match(/([0-9][0-9,]*)/);
            if(!m) return;
            var num = parseInt(m[1].replace(/,/g,''), 10);
            if(isNaN(num)) return;
            var boosted = calc(num, mul).toLocaleString();
            // 원본 텍스트에서 숫자 부분만 교체 (뒤의 " 명", " 개" 등 그대로 유지)
            el.textContent = text.replace(m[1], boosted);
        }

        // .stats-v2-hero-num / .stats-v2-mid-num 안에는 <small>단위</small> 가 있어서
        // textContent 를 전부 바꾸면 <small> 태그가 날아감 → 첫 텍스트노드만 수정하는 헬퍼.
        function boostKeepingTags(el, mul){
            if(!el || !mul || mul <= 1) return;
            for(var i=0; i<el.childNodes.length; i++){
                var n = el.childNodes[i];
                // 첫 번째 텍스트 노드 (숫자가 있는 곳)
                if(n.nodeType === 3 && /[0-9]/.test(n.nodeValue)){
                    var t = n.nodeValue;
                    var m = t.match(/([0-9][0-9,]*)/);
                    if(!m) return;
                    var num = parseInt(m[1].replace(/,/g,''), 10);
                    if(isNaN(num)) return;
                    n.nodeValue = t.replace(m[1], calc(num, mul).toLocaleString());
                    return;
                }
            }
        }

        function apply(){
            // 1. 현재 접속자 (hero live)
            var online = document.querySelector('.stats-v2-live .stats-v2-hero-num');
            boostKeepingTags(online, M.online);

            // 2. 전체 회원 (hero joined)
            var totalMemHero = document.querySelector('.stats-v2-joined .stats-v2-hero-num');
            boostKeepingTags(totalMemHero, M.total_members);

            // 3. 오늘 새 글 / 오늘 새 댓글 (mid 2개)
            var midCards = document.querySelectorAll('.stats-v2-mid-card .stats-v2-mid-num');
            if(midCards[0]) boostKeepingTags(midCards[0], M.today_posts);
            if(midCards[1]) boostKeepingTags(midCards[1], M.today_comments);

            // 4. 전체 게시물 / 전체 댓글 / 전체 회원 (list 3개)
            var listRows = document.querySelectorAll('.stats-v2-list-row .stats-v2-list-val');
            if(listRows[0]) boost(listRows[0], M.total_posts);
            if(listRows[1]) boost(listRows[1], M.total_comments);
            if(listRows[2]) boost(listRows[2], M.total_members);

            // 5. 구 버전 호환 (예전 stat-list 구조)
            var oldRows = document.querySelectorAll('.stat-list .stat-row .stat-val-right');
            if(oldRows.length){
                var oldKeys = ['online','today_posts','today_comments','today_members','total_posts','total_comments','total_members'];
                oldRows.forEach(function(r, i){
                    var k = oldKeys[i];
                    if(k) boost(r, M[k]);
                });
            }
        }

        // DOM 준비되면 실행
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', apply);
        } else {
            apply();
        }
    })();
    </script>
    <?php
}, 99);
