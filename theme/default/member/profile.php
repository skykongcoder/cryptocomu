<?php
/**
 * NuriBoard - 마이페이지
 */
SEO::setTitle('마이페이지');
$prefix = DB::getPrefix();

$pointHistory = Point::history($member['id'], 1, 15);
$levelProgress = Level::progressToNext($member['id']);

// 내 글 (최근 30개)
$myPosts = DB::fetchAll(
    "SELECT p.id, p.board_id, p.title, p.hit, p.vote_up, p.comment_count, p.created_at,
            b.title as board_title
     FROM {$prefix}posts p
     LEFT JOIN {$prefix}boards b ON p.board_id = b.board_id
     WHERE p.member_id = ?
     ORDER BY p.id DESC LIMIT 30",
    [$member['id']]
);

// 내 댓글 (최근 30개)
$myComments = DB::fetchAll(
    "SELECT c.id, c.content, c.created_at,
            p.id as post_id, p.board_id, p.title as post_title
     FROM {$prefix}comments c
     LEFT JOIN {$prefix}posts p ON c.post_id = p.id
     WHERE c.member_id = ?
     ORDER BY c.id DESC LIMIT 30",
    [$member['id']]
);

$activeTab = $_GET['tab'] ?? 'info';
require dirname(__DIR__) . '/header.php';
?>

<div class="container">
<div class="mypage-wrap">

    <!-- 프로필 헤더 -->
    <div class="mypage-header">
        <div class="mypage-avatar"><?php if (!empty($member['profile_image'])): ?><img src="<?= nb_url($member['profile_image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: ?><?= strtoupper(mb_substr($member['nickname'], 0, 1)) ?><?php endif; ?></div>
        <div class="mypage-info">
            <div class="mypage-nick"><?= nb_level_icon($member['level']) ?> <?= nb_e($member['nickname']) ?></div>
            <div class="mypage-meta">가입일 <?= date('Y.m.d', strtotime($member['created_at'])) ?></div>
        </div>
        <div class="mypage-stats">
            <div class="mps-item"><strong><?= number_format($member['point']) ?></strong><span>포인트</span></div>
            <div class="mps-item"><strong>Lv.<?= $member['level'] ?></strong><span><?= nb_e($levelProgress['current']['name'] ?? '레벨') ?></span></div>
            <div class="mps-item"><strong><?= count($myPosts) ?></strong><span>작성글</span></div>
            <div class="mps-item"><strong><?= count($myComments) ?></strong><span>댓글</span></div>
        </div>
    </div>

    <!-- 등급 진행도 바 -->
    <?php if ($levelProgress['next']): ?>
    <div class="level-progress-wrap">
        <div class="lp-labels">
            <span><?= Level::getIcon($member['level']) ?> Lv.<?= $member['level'] ?> <?= nb_e($levelProgress['current']['name'] ?? '') ?></span>
            <span style="color:#64748b;font-size:12px">다음 등급까지 <?= number_format($levelProgress['need']) ?>포인트</span>
            <span><?= Level::getIcon($member['level'] + 1) ?> Lv.<?= $member['level'] + 1 ?> <?= nb_e($levelProgress['next']['name']) ?></span>
        </div>
        <div class="lp-bar-wrap">
            <div class="lp-bar" style="width:<?= $levelProgress['percent'] ?>%"></div>
        </div>
        <div style="text-align:center;font-size:12px;color:#94a3b8;margin-top:4px"><?= $levelProgress['percent'] ?>% 달성</div>
    </div>
    <?php else: ?>
    <div class="level-progress-wrap" style="text-align:center;padding:12px 20px">
        <span style="font-size:14px;color:#7c3aed;font-weight:700">🏆 최고 등급 달성!</span>
    </div>
    <?php endif; ?>

    <!-- 탭 -->
    <div class="mypage-tabs">
        <a href="?tab=info" class="mp-tab <?= $activeTab === 'info' ? 'active' : '' ?>">내 정보</a>
        <a href="?tab=posts" class="mp-tab <?= $activeTab === 'posts' ? 'active' : '' ?>">내 글 <span class="mp-cnt"><?= count($myPosts) ?></span></a>
        <a href="?tab=comments" class="mp-tab <?= $activeTab === 'comments' ? 'active' : '' ?>">내 댓글 <span class="mp-cnt"><?= count($myComments) ?></span></a>
        <a href="?tab=points" class="mp-tab <?= $activeTab === 'points' ? 'active' : '' ?>">포인트 내역</a>
        <a href="?tab=bookmarks" class="mp-tab <?= $activeTab === 'bookmarks' ? 'active' : '' ?>">북마크</a>
        <a href="?tab=api" class="mp-tab <?= $activeTab === 'api' ? 'active' : '' ?>">API 키</a>
    </div>

    <!-- 내 정보 탭 -->
    <?php if ($activeTab === 'info'): ?>
    <div class="mypage-panel">
        <?php if (!empty($_GET['saved'])): ?>
            <div class="alert success">저장되었습니다.</div>
        <?php endif; ?>
        <form method="post" action="<?= nb_url('profile') ?>" class="mp-form" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <table class="mp-form-table">
                <tr>
                    <th>프로필 이미지</th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                            <?= nb_avatar($member['nickname'], $member['profile_image'] ?? '', '64') ?>
                            <?php if (!empty($member['profile_image'])): ?>
                                <label style="font-size:13px;color:#dc2626;cursor:pointer"><input type="checkbox" name="delete_profile_image" value="1" style="margin-right:4px">삭제</label>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="profile_image" accept="image/*" id="profileImageInput">
                        <small>jpg, png, gif (업로드 시 자동으로 512px로 리사이즈됩니다)</small>
                    </td>
                </tr>
                <tr>
                    <th>아이디</th>
                    <td><input type="text" value="<?= nb_e($member['user_id']) ?>" disabled></td>
                </tr>
                <tr>
                    <th>닉네임</th>
                    <td><input type="text" name="nickname" value="<?= nb_e($member['nickname']) ?>" required maxlength="20"></td>
                </tr>
                <tr>
                    <th>이메일</th>
                    <td>
                        <input type="email" name="email" value="<?= nb_e($member['email']) ?>">
                        <small>비밀번호 찾기에 사용됩니다.</small>
                    </td>
                </tr>
                <tr>
                    <th>새 비밀번호</th>
                    <td>
                        <input type="password" name="password" minlength="6" placeholder="변경하지 않으면 비워두세요">
                        <small>변경 시만 입력 (6자 이상)</small>
                    </td>
                </tr>
            </table>
            <div class="mp-form-actions">
                <button type="submit" class="btn btn-primary">저장하기</button>
            </div>
        </form>
    </div>

    <!-- 내 글 탭 -->
    <?php elseif ($activeTab === 'posts'): ?>
    <div class="mypage-panel">
        <?php if (empty($myPosts)): ?>
            <div class="mp-empty">작성한 글이 없습니다.</div>
        <?php else: ?>
        <div class="mp-list">
            <?php foreach ($myPosts as $p): ?>
            <div class="mp-row">
                <span class="mp-board"><?= nb_e($p['board_title'] ?? $p['board_id']) ?></span>
                <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" class="mp-title">
                    <?= nb_e($p['title']) ?>
                    <?php if ($p['comment_count'] > 0): ?><span class="lt-cmt">[<?= $p['comment_count'] ?>]</span><?php endif; ?>
                </a>
                <span class="mp-meta">
                    <span>조회 <?= number_format($p['hit']) ?></span>
                    <?php if ($p['vote_up'] > 0): ?><span class="mp-vote">▲<?= $p['vote_up'] ?></span><?php endif; ?>
                </span>
                <span class="mp-date"><?= date('Y.m.d', strtotime($p['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 내 댓글 탭 -->
    <?php elseif ($activeTab === 'comments'): ?>
    <div class="mypage-panel">
        <?php if (empty($myComments)): ?>
            <div class="mp-empty">작성한 댓글이 없습니다.</div>
        <?php else: ?>
        <div class="mp-list">
            <?php foreach ($myComments as $c): ?>
            <div class="mp-row">
                <a href="<?= nb_url("board/{$c['board_id']}/{$c['post_id']}") ?>#comments" class="mp-title">
                    <span class="mp-comment-post"><?= nb_e(mb_strimwidth($c['post_title'] ?? '', 0, 25, '..')) ?></span>
                    <span class="mp-comment-body"><?= nb_e(mb_strimwidth($c['content'], 0, 60, '...')) ?></span>
                </a>
                <span class="mp-date"><?= date('Y.m.d', strtotime($c['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 포인트 내역 탭 -->
    <?php elseif ($activeTab === 'points'): ?>
    <div class="mypage-panel">
        <?php if (empty($pointHistory['items'])): ?>
            <div class="mp-empty">포인트 내역이 없습니다.</div>
        <?php else: ?>
        <div class="mp-list">
            <?php foreach ($pointHistory['items'] as $ph): ?>
            <div class="mp-row">
                <span class="mp-title" style="flex:1"><?= nb_e($ph['reason']) ?></span>
                <span class="point-val <?= $ph['point'] > 0 ? 'plus' : 'minus' ?>"><?= $ph['point'] > 0 ? '+' : '' ?><?= $ph['point'] ?>P</span>
                <span class="mp-date"><?= date('Y.m.d H:i', strtotime($ph['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 북마크 탭 -->
    <?php if ($activeTab === 'bookmarks'): ?>
    <div class="mypage-panel">
        <?php
        $bookmarks = DB::fetchAll("SELECT b.*, p.title, p.board_id, p.comment_count, p.created_at as post_date FROM {$prefix}bookmarks b LEFT JOIN {$prefix}posts p ON b.post_id = p.id WHERE b.member_id = ? ORDER BY b.id DESC", [$member['id']]);
        ?>
        <?php if (empty($bookmarks)): ?>
            <div class="mp-empty">북마크한 게시글이 없습니다.</div>
        <?php else: ?>
        <div class="mp-list">
            <?php foreach ($bookmarks as $bm): ?>
            <div class="mp-row">
                <a href="<?= nb_url("board/{$bm['board_id']}/{$bm['post_id']}") ?>" class="mp-title">
                    <?= nb_e($bm['title'] ?? '삭제된 글') ?>
                    <?php if (($bm['comment_count'] ?? 0) > 0): ?><span style="color:var(--primary)">[<?= $bm['comment_count'] ?>]</span><?php endif; ?>
                </a>
                <span class="mp-date"><?= date('m.d', strtotime($bm['post_date'] ?? $bm['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- API 키 탭 -->
    <?php if ($activeTab === 'api'): ?>
    <div class="mypage-panel">
        <p style="font-size:13px;color:#64748b;margin-bottom:16px">API 키를 발급받아 외부 프로그램에서 자동으로 글쓰기, 댓글 작성 등을 할 수 있습니다.</p>

        <?php $apiKeys = DB::fetchAll("SELECT * FROM {$prefix}api_keys WHERE member_id = ? ORDER BY id DESC", [$member['id']]); ?>

        <?php if (!empty($apiKeys)): ?>
        <table style="width:100%;font-size:13px;margin-bottom:16px">
            <thead><tr style="border-bottom:2px solid #e2e8f0"><th style="padding:8px;text-align:left">이름</th><th style="padding:8px">API 키</th><th style="padding:8px">요청 수</th><th style="padding:8px">최근 사용</th><th style="padding:8px">관리</th></tr></thead>
            <tbody>
            <?php foreach ($apiKeys as $ak): ?>
            <tr style="border-bottom:1px solid #f8fafc">
                <td style="padding:8px;font-weight:500"><?= nb_e($ak['name']) ?></td>
                <td style="padding:8px"><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px"><?= substr($ak['api_key'], 0, 8) ?>...<?= substr($ak['api_key'], -4) ?></code></td>
                <td style="padding:8px;text-align:center"><?= number_format($ak['request_count']) ?></td>
                <td style="padding:8px;text-align:center;color:#94a3b8;font-size:12px"><?= $ak['last_used_at'] ? date('m.d H:i', strtotime($ak['last_used_at'])) : '-' ?></td>
                <td style="padding:8px;text-align:center"><button class="btn btn-sm btn-danger" onclick="deleteApiKey(<?= $ak['id'] ?>)">삭제</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <form onsubmit="return generateApiKey(event)" style="display:flex;gap:8px;align-items:end">
            <div style="flex:1">
                <label style="font-size:12px;font-weight:600;color:#475569;display:block;margin-bottom:4px">키 이름</label>
                <input type="text" id="apiKeyName" value="My API Key" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
            </div>
            <button type="submit" class="btn btn-primary">API 키 발급</button>
        </form>

        <div id="newKeyResult" style="display:none;margin-top:12px;padding:14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px">
            <div style="font-size:13px;font-weight:600;color:#059669;margin-bottom:6px">✅ API 키가 발급되었습니다!</div>
            <code id="newKeyValue" style="display:block;background:#fff;padding:10px;border-radius:6px;font-size:13px;word-break:break-all;border:1px solid #d1d5db"></code>
            <small style="color:#dc2626;display:block;margin-top:6px">⚠️ 이 키는 다시 볼 수 없습니다. 지금 복사해서 안전하게 보관하세요.</small>
        </div>

        <!-- API 사용법 -->
        <details style="margin-top:20px">
            <summary style="font-size:14px;font-weight:600;cursor:pointer;padding:8px 0">📖 API 사용법</summary>
            <div style="margin-top:10px;padding:14px;background:#f8fafc;border-radius:8px;font-size:13px;line-height:1.8">
                <strong>인증:</strong> 모든 요청에 헤더 추가<br>
                <code>Authorization: Bearer YOUR_API_KEY</code><br><br>

                <strong>글 작성 (POST):</strong><br>
                <code>POST <?= nb_setting('site_url') ?>/api/v1/posts</code><br>
                <code>{"board_id": "free", "title": "제목", "content": "내용"}</code><br><br>

                <strong>댓글 작성 (POST):</strong><br>
                <code>POST <?= nb_setting('site_url') ?>/api/v1/comments</code><br>
                <code>{"post_id": 1, "content": "댓글 내용"}</code><br><br>

                <strong>게시판 목록 (GET):</strong><br>
                <code>GET <?= nb_setting('site_url') ?>/api/v1/boards</code><br><br>

                <strong>글 목록 (GET):</strong><br>
                <code>GET <?= nb_setting('site_url') ?>/api/v1/posts?board_id=free&page=1</code><br><br>

                <strong>내 정보 (GET):</strong><br>
                <code>GET <?= nb_setting('site_url') ?>/api/v1/me</code>
            </div>
        </details>
    </div>
    <?php endif; ?>

</div>
</div>

<style>
.mypage-wrap{max-width:860px;margin:0 auto;padding-bottom:40px}

/* 프로필 헤더 */
.mypage-header{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;display:flex;align-items:center;gap:20px;margin-bottom:4px}
.mypage-avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;font-size:26px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mypage-info{flex:1}
.mypage-nick{font-size:18px;font-weight:700;margin-bottom:4px}
.mypage-meta{font-size:12px;color:var(--text-light)}
.mypage-stats{display:flex;gap:20px;text-align:center}
.mps-item strong{display:block;font-size:18px;font-weight:700;color:var(--primary)}
.mps-item span{font-size:11px;color:var(--text-light)}

/* 탭 */
.mypage-tabs{display:flex;border-bottom:2px solid var(--border);margin-bottom:0;background:#fff;border-radius:10px 10px 0 0;border:1px solid var(--border);border-bottom:none;margin-top:12px}
.mp-tab{padding:13px 20px;font-size:14px;font-weight:600;color:var(--text-light);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px}
.mp-tab:hover{color:var(--text);text-decoration:none}
.mp-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
.mp-cnt{background:#e2e8f0;color:var(--text-light);border-radius:10px;padding:1px 7px;font-size:11px;font-weight:600}
.mp-tab.active .mp-cnt{background:#eff6ff;color:var(--primary)}

/* 패널 */
.mypage-panel{background:#fff;border:1px solid var(--border);border-top:none;border-radius:0 0 12px 12px;padding:24px}

/* 내정보 폼 테이블 */
.mp-form-table{width:100%;border-collapse:collapse}
.mp-form-table th{text-align:left;padding:14px 16px 14px 0;font-size:14px;font-weight:600;color:#475569;white-space:nowrap;vertical-align:middle;width:100px;border-bottom:1px solid #f1f5f9}
.mp-form-table td{padding:10px 0;border-bottom:1px solid #f1f5f9}
.mp-form-table tr:last-child th,.mp-form-table tr:last-child td{border-bottom:none}
.mp-form-table input{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none;transition:border-color .2s}
.mp-form-table input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.08)}
.mp-form-table input:disabled{background:#f8fafc;color:var(--text-light);cursor:not-allowed}
.mp-form-table small{display:block;margin-top:4px;color:#94a3b8;font-size:12px}
.mp-form-actions{margin-top:20px;text-align:right}

/* 목록 공통 */
.mp-list{}
.mp-row{display:flex;align-items:center;padding:10px 0;border-bottom:1px solid #f8fafc;gap:10px;font-size:13px}
.mp-row:last-child{border-bottom:none}
.mp-board{font-size:11px;color:var(--text-light);background:#f1f5f9;padding:2px 7px;border-radius:3px;flex-shrink:0}
.mp-title{flex:1;color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mp-title:hover{color:var(--primary);text-decoration:none}
.mp-meta{display:flex;gap:8px;color:var(--text-light);font-size:12px;flex-shrink:0}
.mp-vote{color:var(--primary);font-weight:600}
.mp-date{color:var(--text-light);font-size:12px;flex-shrink:0;min-width:65px;text-align:right}
.mp-comment-post{font-weight:600;color:var(--primary);margin-right:6px;flex-shrink:0}
.mp-comment-body{color:var(--text-light)}
.mp-empty{padding:40px;text-align:center;color:var(--text-light);font-size:14px}

/* 포인트 */
.point-val{font-weight:700;flex-shrink:0}
.point-val.plus{color:#059669}
.point-val.minus{color:#dc2626}

/* 등급 진행도 */
.level-progress-wrap{background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 20px;margin-bottom:4px}
.lp-labels{display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:8px}
.lp-bar-wrap{height:8px;background:#f1f5f9;border-radius:10px;overflow:hidden}
.lp-bar{height:100%;background:linear-gradient(90deg,#6366f1,#a78bfa);border-radius:10px;transition:width .4s ease}

@media(max-width:768px){
    .mypage-header{flex-direction:column;text-align:center}
    .mypage-stats{justify-content:center}
    .mp-tab{padding:10px 12px;font-size:13px}
    .mp-meta{display:none}
    .lp-labels span:nth-child(2){display:none}
    .mp-form-table,.mp-form-table tbody,.mp-form-table tr,.mp-form-table th,.mp-form-table td{display:block;width:100%}
    .mp-form-table th{padding:10px 0 4px;border-bottom:none}
    .mp-form-table td{padding:0 0 12px}
}
</style>

<script>
function generateApiKey(e){
    e.preventDefault();
    var name=document.getElementById('apiKeyName').value;
    var fd=new FormData();fd.append('name',name);
    fetch('<?= nb_url("api/v1/key/generate") ?>',{method:'POST',body:fd})
    .then(function(r){return r.json()}).then(function(res){
        if(res.success){
            document.getElementById('newKeyValue').textContent=res.api_key;
            document.getElementById('newKeyResult').style.display='block';
        } else {alert(res.error||'오류');}
    });
    return false;
}
function deleteApiKey(id){
    if(!confirm('API 키를 삭제할까요?'))return;
    var fd=new FormData();fd.append('_token','<?= Auth::csrfToken() ?>');fd.append('id',id);
    fetch('<?= nb_url("api/v1/key/delete") ?>',{method:'POST',body:fd})
    .then(function(r){return r.json()}).then(function(res){if(res.success)location.reload();});
}

/* ===== 프로필 이미지 클라이언트 리사이즈 (업로드 제한 우회) =====
 * 선택한 이미지를 canvas로 최대 512px JPEG(품질 0.85)로 줄여 DataTransfer로 교체.
 * 원본이 10MB여도 보통 50~150KB로 축소되어 서버 upload_max_filesize 제약을 받지 않음. */
(function(){
    var input = document.getElementById('profileImageInput');
    if (!input) return;
    input.addEventListener('change', function(){
        var file = this.files && this.files[0];
        if (!file || !/^image\//.test(file.type)) return;
        var reader = new FileReader();
        reader.onload = function(e){
            var img = new Image();
            img.onload = function(){
                var max = 512;
                var w = img.width, h = img.height;
                if (w > max || h > max) {
                    var r = w >= h ? max/w : max/h;
                    w = Math.round(w*r); h = Math.round(h*r);
                }
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob){
                    if (!blob) return;
                    try {
                        var newName = (file.name || 'profile').replace(/\.[^.]+$/, '') + '.jpg';
                        var newFile = new File([blob], newName, {type:'image/jpeg', lastModified: Date.now()});
                        var dt = new DataTransfer();
                        dt.items.add(newFile);
                        input.files = dt.files;
                    } catch(err) { console.warn('profile resize swap failed', err); }
                }, 'image/jpeg', 0.85);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
})();
</script>
<?php require dirname(__DIR__) . '/footer.php'; ?>
