    <?php Plugin::doHook('after_content'); ?>
    </main>

    <?php if (!empty($rightSideBanners)): ?>
        <div class="side-right <?= nb_setting('wing_right_sticky', '1') === '1' ? 'wing-sticky' : '' ?>">
            <?php foreach ($rightSideBanners as $bn): ?>
                <a href="<?= nb_e($bn['link']) ?>" target="<?= nb_e($bn['target']) ?>" class="wing-banner"><img src="<?= nb_url($bn['image']) ?>" alt="<?= nb_e($bn['title']) ?>"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($leftBanners) || !empty($rightSideBanners)): ?>
    </div><!-- /layout-3col -->
    <?php endif; ?>

    <!-- 모바일 하단 고정바 -->
    <?php if (!empty($_bottomBar)): ?>
    <nav class="mobile-bottombar" id="mobileBottomBar">
        <?php foreach ($_bottomBar as $_bi): ?>
        <a href="<?= nb_e($_bi['link']) ?>" class="bottombar-item">
            <span class="bottombar-icon"><?= MobileMenu::renderIcon($_bi['icon'], 20) ?></span>
            <span class="bottombar-label"><?= nb_e($_bi['title']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- 출석체크 유도 팝업 -->
    <?php
    $_showAttendPopup = false;
    if (nb_setting('attend_popup_enabled', '1') !== '1') $_showAttendPopup = false;
    elseif (!Auth::check()) {
        $_showAttendPopup = true;
        $_attendMsg = '오늘 출석 안하셨어요!';
        $_attendLink = nb_url('login');
        $_attendBtn = '출석체크하러가기';
    } else {
        $prefix = DB::getPrefix();
        $_todayAttend = DB::fetch("SELECT id FROM {$prefix}attendance WHERE member_id = ? AND attend_date = CURDATE()", [Auth::id()]);
        if (!$_todayAttend) {
            $_showAttendPopup = true;
            $_attendMsg = '오늘 출석 안하셨어요!';
            $_attendLink = nb_url('attendance');
            $_attendBtn = '출석체크하러가기';
        }
    }
    if ($_showAttendPopup && empty($_COOKIE['nb_attend_dismiss'])):
    ?>
    <div class="attend-popup" id="attendPopup">
        <button class="attend-popup-close" onclick="closeAttendPopup()">&times;</button>
        <div class="attend-popup-icon">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
        </div>
        <p class="attend-popup-msg"><?= $_attendMsg ?></p>
        <a href="<?= $_attendLink ?>" class="attend-popup-btn"><?= $_attendBtn ?></a>
    </div>
    <script>
    function closeAttendPopup(){
        document.getElementById('attendPopup').style.display='none';
        document.cookie='nb_attend_dismiss=1;path=/;max-age=86400';
    }
    </script>
    <?php endif; ?>

    <?php
    // 푸터 유형: biz(사업자) / custom(직접HTML) / minimal(저작권만)
    $_ftType = nb_setting('footer_type', 'biz');
    $_ftCopyright = nb_setting('biz_copyright', '');
    ?>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-links">
                    <a href="<?= nb_url('terms') ?>"><strong>이용약관</strong></a>
                    <span class="ft-sep">|</span>
                    <a href="<?= nb_url('privacy') ?>"><strong>개인정보처리방침</strong></a>
                </div>
            </div>

            <?php if ($_ftType === 'biz'):
                $_ftCompany  = nb_setting('biz_company', '');
                $_ftCeo      = nb_setting('biz_ceo', '');
                $_ftAddress  = nb_setting('biz_address', '');
                $_ftBizNo    = nb_setting('biz_reg_number', '');
                $_ftOnlineNo = nb_setting('biz_online_number', '');
                $_ftPhone    = nb_setting('biz_phone', '');
                $_ftEmail    = nb_setting('biz_email', '');
                $_ftPrivacyOfficer = nb_setting('biz_privacy_officer', '');
            ?>
            <div class="footer-biz">
                <dl>
                    <?php if ($_ftCompany): ?><dt>상호</dt><dd><?= nb_e($_ftCompany) ?></dd><?php endif; ?>
                    <?php if ($_ftCeo): ?><dt>대표자</dt><dd><?= nb_e($_ftCeo) ?></dd><?php endif; ?>
                    <?php if ($_ftBizNo): ?>
                    <dt>사업자등록번호</dt>
                    <dd>
                        <?= nb_e($_ftBizNo) ?>
                        <a href="javascript:void(0)" onclick="window.open('https://www.ftc.go.kr/bizCommPop.do?wrkr_no=<?= nb_e(preg_replace('/[^0-9]/', '', $_ftBizNo)) ?>','bizCommPop','width=750,height=700');return false;" class="ft-biz-check">[사업자정보확인]</a>
                    </dd>
                    <?php endif; ?>
                    <?php if ($_ftOnlineNo): ?><dt>통신판매업신고</dt><dd><?= nb_e($_ftOnlineNo) ?></dd><?php endif; ?>
                </dl>
                <dl>
                    <?php if ($_ftAddress): ?><dt>주소</dt><dd><?= nb_e($_ftAddress) ?></dd><?php endif; ?>
                    <?php if ($_ftPhone): ?><dt>대표전화</dt><dd><a href="tel:<?= nb_e(preg_replace('/[^0-9]/', '', $_ftPhone)) ?>"><?= nb_e($_ftPhone) ?></a></dd><?php endif; ?>
                    <?php if ($_ftEmail): ?><dt>이메일</dt><dd><a href="mailto:<?= nb_e($_ftEmail) ?>"><?= nb_e($_ftEmail) ?></a></dd><?php endif; ?>
                    <?php if ($_ftPrivacyOfficer): ?><dt>개인정보보호책임자</dt><dd><?= nb_e($_ftPrivacyOfficer) ?></dd><?php endif; ?>
                </dl>
            </div>
            <?php elseif ($_ftType === 'custom'):
                $_ftCustom = trim(nb_setting('footer_custom_html', ''));
                if ($_ftCustom !== ''): ?>
                <div class="footer-custom"><?= nb_purify($_ftCustom) ?></div>
            <?php endif; endif; ?>

            <p class="footer-copy">
                <?php if ($_ftCopyright): ?>
                    <?= nb_e($_ftCopyright) ?>
                <?php else: ?>
                    &copy; <?= date('Y') ?> <?= nb_e(nb_setting('site_title', 'NuriBoard')) ?>. All rights reserved.
                <?php endif; ?>
                <span class="ft-powered">Powered by NuriBoard</span>
            </p>
        </div>
    </footer>
    <!-- 작성자 호버 카드 (전역) -->
    <div class="author-card-overlay" id="authorCardOverlay"></div>
    <div class="author-card" id="authorCard" role="dialog">
        <div class="ac-loading">불러오는중...</div>
        <div class="ac-content">
            <div class="ac-header">
                <span class="ac-level"></span>
                <span class="ac-nick"></span>
            </div>
            <div class="ac-meta"><span class="ac-join"></span></div>
            <div class="ac-stats">
                <div class="ac-stat"><span class="ac-label">글</span><span class="ac-posts">0</span></div>
                <div class="ac-stat"><span class="ac-label">댓글</span><span class="ac-comments">0</span></div>
                <div class="ac-stat"><span class="ac-label">팔로워</span><span class="ac-followers">0</span></div>
            </div>
            <div class="ac-recent">
                <div class="ac-recent-title">최근글</div>
                <div class="ac-recent-list"></div>
            </div>
            <div class="ac-actions">
                <button type="button" class="ac-follow-btn" id="acFollowBtn">팔로우</button>
                <a class="ac-msg-btn" id="acMsgBtn">쪽지</a>
                <a class="ac-profile-btn" id="acProfileBtn">프로필</a>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var card = document.getElementById('authorCard');
        var overlay = document.getElementById('authorCardOverlay');
        if (!card) return;
        var hoverTimer = null, closeTimer = null, currentMid = null;
        var cache = {};
        var isMobile = window.matchMedia('(max-width:768px)').matches;

        function positionCard(target){
            card.style.display = 'block';
            if (isMobile) {
                // 모바일은 CSS로 중앙 고정, 위치 계산 불필요
                if (overlay) overlay.classList.add('show');
                card.style.visibility = 'visible';
                return;
            }
            var rect = target.getBoundingClientRect();
            card.style.visibility = 'hidden';
            requestAnimationFrame(function(){
                var cw = card.offsetWidth, ch = card.offsetHeight;
                var top = rect.bottom + window.scrollY + 6;
                var left = rect.left + window.scrollX;
                if (left + cw > window.innerWidth - 10) left = window.innerWidth - cw - 10;
                if (left < 10) left = 10;
                if (rect.bottom + ch + 20 > window.innerHeight && rect.top - ch - 6 > 0) {
                    top = rect.top + window.scrollY - ch - 6;
                }
                card.style.top = top + 'px';
                card.style.left = left + 'px';
                card.style.visibility = 'visible';
            });
        }

        function showCard(target, mid){
            clearTimeout(closeTimer);
            if (currentMid === mid && card.style.display === 'block') return;
            currentMid = mid;
            positionCard(target);
            if (cache[mid]) {
                renderCard(cache[mid]);
            } else {
                card.querySelector('.ac-loading').style.display = 'block';
                card.querySelector('.ac-content').style.display = 'none';
                fetch('<?= nb_url("api/member-card") ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: mid})
                }).then(function(r){
                    return r.text().then(function(txt){
                        try { return JSON.parse(txt); }
                        catch(e) { console.error('[author-card] not JSON:', txt); card.querySelector('.ac-loading').textContent = '응답 오류: ' + txt.substring(0,100); throw e; }
                    });
                }).then(function(res){
                    if (res.success && currentMid === mid) {
                        cache[mid] = res;
                        renderCard(res);
                    } else if (!res.success) {
                        card.querySelector('.ac-loading').textContent = '정보를 불러올 수 없습니다';
                        console.error('[author-card] success=false', res);
                    }
                }).catch(function(err){
                    console.error('[author-card] fetch error:', err);
                });
            }
        }

        function renderCard(d){
            card.querySelector('.ac-loading').style.display = 'none';
            card.querySelector('.ac-content').style.display = 'block';
            card.querySelector('.ac-level').innerHTML = d.level_icon || '';
            card.querySelector('.ac-nick').textContent = d.nickname;
            card.querySelector('.ac-join').textContent = 'Lv.' + d.level + ' · ' + d.joined + ' 가입';
            card.querySelector('.ac-posts').textContent = d.post_count;
            card.querySelector('.ac-comments').textContent = d.comment_count;
            card.querySelector('.ac-followers').textContent = d.follower_count;
            var list = card.querySelector('.ac-recent-list');
            list.innerHTML = '';
            if (!d.recent_posts || d.recent_posts.length === 0) {
                list.innerHTML = '<div class="ac-no-posts">작성한 글이 없습니다</div>';
            } else {
                d.recent_posts.forEach(function(p){
                    var a = document.createElement('a');
                    a.href = p.url;
                    a.textContent = '• ' + p.title;
                    a.className = 'ac-recent-item';
                    list.appendChild(a);
                });
            }
            var followBtn = card.querySelector('#acFollowBtn');
            var msgBtn = card.querySelector('#acMsgBtn');
            var profBtn = card.querySelector('#acProfileBtn');
            profBtn.href = d.profile_url;
            if (d.is_me || !d.logged_in) {
                followBtn.style.display = 'none';
                msgBtn.style.display = 'none';
            } else {
                followBtn.style.display = 'inline-flex';
                msgBtn.style.display = 'inline-flex';
                msgBtn.href = d.message_url;
                updateFollowBtn(d.is_following);
                followBtn.onclick = function(){
                    followBtn.disabled = true;
                    fetch('<?= nb_url("api/follow") ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({target_id: d.id})
                    }).then(function(r){return r.json()}).then(function(res){
                        followBtn.disabled = false;
                        if (res.success) {
                            d.is_following = res.is_following;
                            d.follower_count = res.follower_count;
                            card.querySelector('.ac-followers').textContent = res.follower_count;
                            updateFollowBtn(res.is_following);
                            if (cache[d.id]) {
                                cache[d.id].is_following = res.is_following;
                                cache[d.id].follower_count = res.follower_count;
                            }
                        } else {
                            alert(res.message || '오류가 발생했습니다.');
                        }
                    }).catch(function(){followBtn.disabled = false;});
                };
            }
        }

        function updateFollowBtn(isFollowing){
            var btn = card.querySelector('#acFollowBtn');
            if (isFollowing) {
                btn.textContent = '팔로잉';
                btn.classList.add('following');
            } else {
                btn.textContent = '+ 팔로우';
                btn.classList.remove('following');
            }
        }

        function hideCardDelayed(){
            closeTimer = setTimeout(function(){
                card.style.display = 'none';
                if (overlay) overlay.classList.remove('show');
                currentMid = null;
            }, 200);
        }

        function hideCardNow(){
            card.style.display = 'none';
            if (overlay) overlay.classList.remove('show');
            currentMid = null;
        }

        if (overlay) overlay.addEventListener('click', hideCardNow);

        document.addEventListener('mouseover', function(e){
            if (isMobile) return;
            var t = e.target.closest('.nick-popup-trigger');
            if (!t) return;
            var mid = parseInt(t.getAttribute('data-mid'), 10);
            if (!mid) return;
            clearTimeout(hoverTimer);
            clearTimeout(closeTimer);
            hoverTimer = setTimeout(function(){ showCard(t, mid); }, 300);
        });
        document.addEventListener('mouseout', function(e){
            if (isMobile) return;
            var t = e.target.closest('.nick-popup-trigger');
            if (!t) return;
            clearTimeout(hoverTimer);
            var rel = e.relatedTarget;
            if (rel && (rel.closest && (rel.closest('.nick-popup-trigger') === t || rel.closest('.author-card')))) return;
            hideCardDelayed();
        });
        card.addEventListener('mouseenter', function(){ clearTimeout(closeTimer); });
        card.addEventListener('mouseleave', function(){ hideCardDelayed(); });

        document.addEventListener('click', function(e){
            var t = e.target.closest('.nick-popup-trigger');
            if (t && isMobile) {
                var mid = parseInt(t.getAttribute('data-mid'), 10);
                if (mid) {
                    e.preventDefault();
                    e.stopPropagation();
                    showCard(t, mid);
                }
                return;
            }
            if (!card.contains(e.target) && !e.target.closest('.nick-popup-trigger')) {
                if (card.style.display === 'block') hideCardNow();
            }
        });

        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hideCardNow(); });
        window.addEventListener('resize', function(){
            isMobile = window.matchMedia('(max-width:768px)').matches;
            hideCardNow();
        });
    })();
    </script>
    <?php Plugin::doHook('after_footer'); ?>
    <?php Plugin::doHook('body_end'); ?>
    <script src="<?= nb_asset('main.js') ?>"></script>
    <script>
    function switchRanking(btn,panelId){
        btn.parentElement.querySelectorAll('.ranking-tab').forEach(function(t){t.classList.remove('active')});
        btn.classList.add('active');
        btn.closest('.side-box').querySelectorAll('.ranking-panel').forEach(function(p){p.classList.remove('active');p.style.display='none'});
        var panel=document.getElementById(panelId);
        panel.classList.add('active');panel.style.display='block';
    }
    </script>
    <script>

    function toggleMobileSearch() {
        var popup = document.getElementById('mobileSearchPopup');
        var open = popup.classList.toggle('open');
        if (open) document.getElementById('mobileSearchInput').focus();
    }

    function toggleNav() {
        var btn = document.getElementById('navToggle');
        var panel = document.getElementById('mobileMyinfo');
        var open = panel.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.setAttribute('aria-label', open ? '메뉴 닫기' : '메뉴 열기');
        document.body.style.overflow = open ? 'hidden' : '';
    }
    // 모바일 드롭다운 터치 토글
    document.querySelectorAll('.nav-dropdown > .nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                e.stopPropagation();
                var parent = this.parentElement;
                var wasOpen = parent.classList.contains('open');
                // 다른 열린 드롭다운 닫기
                document.querySelectorAll('.nav-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
                if (!wasOpen) {
                    parent.classList.add('open');
                    // 부모 메뉴 바로 아래에 드롭다운 위치
                    var rect = this.getBoundingClientRect();
                    var dd = parent.querySelector('.dropdown-menu');
                    if (dd) {
                        dd.style.top = rect.bottom + 'px';
                        dd.style.left = rect.left + 'px';
                        // 화면 오른쪽 넘침 방지
                        var ddWidth = dd.offsetWidth || 160;
                        if (rect.left + ddWidth > window.innerWidth) {
                            dd.style.left = (window.innerWidth - ddWidth - 8) + 'px';
                        }
                    }
                }
            }
        });
    });

    // 외부 클릭 시 닫기 (내정보 패널 + 드롭다운)
    document.addEventListener('click', function(e) {
        var btn = document.getElementById('navToggle');
        var panel = document.getElementById('mobileMyinfo');
        if (panel && panel.classList.contains('open') && !panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('open');
            btn.classList.remove('open');
        }
        // 드롭다운 외부 클릭 시 닫기
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
        }
    });
    // 관리자 패널 - 메뉴 관리
    <?php if (Auth::check() && Auth::isAdmin()): ?>
    function apLoadMenus(){
        fetch('<?= nb_url("api/menu/list") ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({_token:'<?= Auth::csrfToken() ?>'})})
        .then(function(r){return r.json()}).then(function(res){
            if(!res.success) return;
            // 부모 드롭다운 갱신
            var sel=document.getElementById('apMenuParent');
            sel.innerHTML='<option value="0">최상위 메뉴</option>';
            res.all.forEach(function(m){
                if(m.parent_id==0) sel.innerHTML+='<option value="'+m.id+'">└ '+m.title+'의 하위</option>';
            });
            // 메뉴 목록
            var list=document.getElementById('apMenuList');
            if(!res.all.length){list.innerHTML='<div style="padding:12px 0;color:#94a3b8;font-size:13px;text-align:center">메뉴가 없습니다</div>';return;}
            var h='';
            res.all.forEach(function(m){
                var indent=m.parent_id>0?'&nbsp;&nbsp;└ ':'';
                var type=m.board_id?'<span class="ap-mi-sub">[게시판]</span>':(m.link?'<span class="ap-mi-sub">[링크]</span>':'<span class="ap-mi-sub">[그룹]</span>');
                h+='<div class="ap-menu-item">'+indent+'<span class="ap-mi-name">'+m.title+'</span>'+type+'<button onclick="apDeleteMenu('+m.id+',\''+m.title+'\')">&times;</button></div>';
            });
            list.innerHTML=h;
        });
    }
    function apAddMenu(){
        var title=document.getElementById('apMenuTitle').value.trim();
        if(!title){alert('메뉴 이름을 입력하세요');return;}
        var data={
            _token:'<?= Auth::csrfToken() ?>',
            title:title,
            parent_id:document.getElementById('apMenuParent').value,
            board_id:document.getElementById('apMenuBoard').value,
            link:document.getElementById('apMenuLink').value
        };
        fetch('<?= nb_url("api/menu/create") ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
        .then(function(r){return r.json()}).then(function(res){
            if(res.success){
                document.getElementById('apMenuTitle').value='';
                document.getElementById('apMenuLink').value='';
                apLoadMenus();
                alert('메뉴가 추가되었습니다. 새로고침하면 반영됩니다.');
            } else {alert(res.message||'오류');}
        });
    }
    function apDeleteMenu(id,title){
        if(!confirm('"'+title+'" 메뉴를 삭제할까요?')) return;
        fetch('<?= nb_url("api/menu/delete") ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({_token:'<?= Auth::csrfToken() ?>',id:id})})
        .then(function(r){return r.json()}).then(function(res){
            if(res.success) apLoadMenus();
        });
    }
    // 패널 열 때 메뉴 로드
    document.getElementById('adminFloatBtn').addEventListener('click',function(){apLoadMenus()});
    <?php endif; ?>
    </script>
    <?= Plugin::renderFooterAssets() ?>
</body>
</html>
