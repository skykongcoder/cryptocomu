<?php
SEO::setTitle('페이지를 찾을 수 없습니다');
require dirname(__DIR__) . '/header.php';
?>
<div class="error-page">
    <h1>404</h1>
    <p>요청하신 페이지를 찾을 수 없습니다.</p>
    <a href="<?= nb_url('/') ?>" class="btn btn-primary">홈으로 돌아가기</a>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
