<?php
/**
 * NuriBoard 관리자 - 로그인 페이지
 */
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인 - NuriBoard</title>
    <style>
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Noto Sans KR',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#1e293b,#334155)}.login-box{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 8px 32px rgba(0,0,0,.12)}.login-box h1{text-align:center;font-size:24px;margin-bottom:24px;color:#2563eb}.form-group{margin-bottom:16px}.form-group label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}.form-group input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none}.form-group input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}.btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;width:100%;background:#2563eb;color:#fff}.btn:hover{background:#1d4ed8}.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
    </style>
</head>
<body>
    <div class="login-box">
        <h1>NuriBoard Admin</h1>
        <?php if (!empty($loginError)): ?>
            <div class="alert"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="?page=login">
            <div class="form-group">
                <label>아이디</label>
                <input type="text" name="user_id" required autofocus>
            </div>
            <div class="form-group">
                <label>비밀번호</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">로그인</button>
        </form>
    </div>
</body>
</html>
