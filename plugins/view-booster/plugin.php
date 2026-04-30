<?php
/**
 * 조회수 부스터 플러그인
 * 게시글 조회수 표시에 배수/최소값/랜덤 변동을 적용합니다.
 *
 * 적용 위치:
 *   게시판 목록 (.col-hit)          — 각 글의 조회수
 *   글 읽기 페이지 (.hit)            — "조회 N" 형식
 *   메인 위젯 (_list_fragment.php)   — .col-hit 공유
 *
 * DB 수정 없이 화면 표시에만 적용 (JavaScript).
 */

Plugin::addHook('body_end', function () {
    $configFile = __DIR__ . '/config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $config = array_merge([
        'multiplier' => 1,       // 실제값 × 배수
        'minimum'    => 0,       // 최소 조회수
        'variance'   => 0,       // ±% 랜덤 변동 (0~30)
        'enabled'    => 1,       // 전체 활성/비활성
    ], $config);

    // 배수 1, 최소값 0 이면 스킵
    if (empty($config['enabled']) || ((int)$config['multiplier'] <= 1 && (int)$config['minimum'] <= 0)) return;
    ?>
    <script>
    (function(){
        var C = <?= json_encode($config) ?>;
        var MUL = Math.max(1, parseInt(C.multiplier, 10) || 1);
        var MIN = Math.max(0, parseInt(C.minimum, 10) || 0);
        var VAR = Math.min(30, Math.max(0, parseInt(C.variance, 10) || 0));

        // 랜덤 변동 (글 ID 기반 결정론적 — 같은 글은 항상 같은 변동값)
        function pseudoRand(seed){
            var x = Math.sin(seed * 9301 + 49297) * 233280;
            return x - Math.floor(x); // 0~1
        }

        // 배수 + 최소값 + 랜덤 계산
        function boostValue(num, seed){
            var v = num * MUL;
            if (v < MIN) v = MIN;
            if (VAR > 0 && seed !== undefined){
                // ±VAR% 범위에서 결정론적 랜덤
                var factor = 1 + (pseudoRand(seed) - 0.5) * 2 * (VAR / 100);
                v = Math.round(v * factor);
                if (v < MIN) v = MIN;
            }
            return Math.max(0, v);
        }

        // 숫자 추출 (쉼표 포함)
        function extractNum(text){
            var m = (text || '').match(/([0-9][0-9,]*)/);
            if(!m) return null;
            var n = parseInt(m[1].replace(/,/g,''), 10);
            return isNaN(n) ? null : {num: n, str: m[1]};
        }

        // seed 추출 — 같은 행 내 글 ID/제목/URL 에서 해시
        function seedOf(el){
            var row = el.closest('.post-row, tr, li, div');
            if(!row) return 0;
            var link = row.querySelector('a[href*="board/"]');
            var s = link ? link.getAttribute('href') : (row.textContent || '');
            var h = 0;
            for(var i=0;i<s.length;i++) h = (h*31 + s.charCodeAt(i)) | 0;
            return Math.abs(h);
        }

        // 1. 게시판 목록 / 메인 위젯 (.col-hit)
        // 헤더의 "조회" 텍스트는 숫자가 아니므로 자동 제외됨
        document.querySelectorAll('.col-hit').forEach(function(el){
            var info = extractNum(el.textContent.trim());
            if (!info) return; // 숫자 없으면 (헤더 "조회" 등) 스킵
            var boosted = boostValue(info.num, seedOf(el));
            // 첫 텍스트노드만 교체해서 내부 태그 보존
            for(var i=0;i<el.childNodes.length;i++){
                var n = el.childNodes[i];
                if(n.nodeType === 3 && /[0-9]/.test(n.nodeValue)){
                    n.nodeValue = n.nodeValue.replace(info.str, boosted.toLocaleString());
                    return;
                }
            }
            // 텍스트노드 없으면 전체 교체
            el.textContent = el.textContent.replace(info.str, boosted.toLocaleString());
        });

        // 2. 글 읽기 페이지 (.hit) — "조회 N" 형식
        document.querySelectorAll('.hit').forEach(function(el){
            var text = (el.textContent || '').trim();
            var info = extractNum(text);
            if (!info) return;
            var boosted = boostValue(info.num, seedOf(el));
            // "조회" 라는 접두사는 유지하고 숫자만 교체
            for(var i=0;i<el.childNodes.length;i++){
                var n = el.childNodes[i];
                if(n.nodeType === 3 && /[0-9]/.test(n.nodeValue)){
                    n.nodeValue = n.nodeValue.replace(info.str, boosted.toLocaleString());
                    return;
                }
            }
            el.textContent = text.replace(info.str, boosted.toLocaleString());
        });
    })();
    </script>
    <?php
}, 99);
