<?php
/**
 * NuriBoard 관리자 - 대시보드
 */

$stats = [
    'members' => Member::count(),
    'boards' => Board::count(),
    'posts' => Post::totalCount(),
    'comments' => Comment::totalCount(),
    'today_posts' => Post::todayCount(),
    'today_comments' => Comment::todayCount(),
    'today_points' => Point::todayTotal(),
];
$recentPosts = Post::recentPosts(10);

adminHeader('dashboard');
?>

<div class="page-header"><h1>대시보드</h1></div>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-number"><?= number_format($stats['members']) ?></div>
        <div class="stat-label">전체 회원</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($stats['boards']) ?></div>
        <div class="stat-label">게시판 수</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($stats['posts']) ?></div>
        <div class="stat-label">전체 게시글</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($stats['comments']) ?></div>
        <div class="stat-label">전체 댓글</div>
    </div>
    <div class="stat-card" style="background:#FFB6C1;color:#fff">
        <div class="stat-number"><?= number_format($stats['today_posts']) ?></div>
        <div class="stat-label" style="color:rgba(255,255,255,.8)">오늘 게시글</div>
    </div>
    <div class="stat-card" style="background:#FFB6C1;color:#fff">
        <div class="stat-number"><?= number_format($stats['today_comments']) ?></div>
        <div class="stat-label" style="color:rgba(255,255,255,.8)">오늘 댓글</div>
    </div>
    <div class="stat-card" style="background:#FFB6C1;color:#fff">
        <div class="stat-number"><?= number_format($stats['today_points']) ?></div>
        <div class="stat-label" style="color:rgba(255,255,255,.8)">오늘 지급 포인트</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>최근 게시글</h2></div>
    <table class="table">
        <thead><tr><th>ID</th><th>게시판</th><th>제목</th><th>작성자</th><th>작성일</th></tr></thead>
        <tbody>
        <?php foreach ($recentPosts as $post): ?>
            <tr>
                <td><?= $post['id'] ?></td>
                <td><?= nb_e($post['board_id']) ?></td>
                <td><?= nb_e($post['title']) ?></td>
                <td><?= nb_e($post['writer_name'] ?? '탈퇴회원') ?></td>
                <td><?= date('m-d H:i', strtotime($post['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentPosts)): ?>
            <tr><td colspan="5" class="text-center">게시글이 없습니다.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
