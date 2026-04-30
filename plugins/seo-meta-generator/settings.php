<?php
/**
 * SEO 메타태그 자동 생성 - 설정 페이지
 */

$_seo_config_file = __DIR__ . '/config.json';
$_seo_config_raw = file_exists($_seo_config_file)
    ? json_decode(file_get_contents($_seo_config_file), true)
    : [];
if (!is_array($_seo_config_raw)) $_seo_config_raw = [];

$_seo_config = array_merge([
    'enabled' => '1',
    'title_suffix' => '1',
    'desc_length' => '150',
    'include_keywords' => '1',
    'og_enabled' => '1',
    'json_ld_enabled' => '1',
    'twitter_card' => '1',
    'canonical' => '1',
], $_seo_config_raw);

// 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_save'])) {
    $_seo_config['enabled'] = isset($_POST['enabled']) ? '1' : '0';
    $_seo_config['title_suffix'] = isset($_POST['title_suffix']) ? '1' : '0';
    $_seo_config['desc_length'] = max(50, min(300, (int)($_POST['desc_length'] ?? 150)));
    $_seo_config['include_keywords'] = isset($_POST['include_keywords']) ? '1' : '0';
    $_seo_config['og_enabled'] = isset($_POST['og_enabled']) ? '1' : '0';
    $_seo_config['json_ld_enabled'] = isset($_POST['json_ld_enabled']) ? '1' : '0';
    $_seo_config['twitter_card'] = isset($_POST['twitter_card']) ? '1' : '0';
    $_seo_config['canonical'] = isset($_POST['canonical']) ? '1' : '0';

    file_put_contents($_seo_config_file, json_encode($_seo_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}
?>

<form method="post">
    <input type="hidden" name="seo_save" value="1">

    <h3 style="margin:20px 0 16px;font-size:16px;font-weight:600">🔍 SEO 메타태그 설정</h3>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="enabled" <?= $_seo_config['enabled'] === '1' ? 'checked' : '' ?>>
            <strong>플러그인 활성화</strong>
        </label>
    </div>

    <hr style="margin:16px 0">

    <h4 style="margin:12px 0;font-size:14px">메타 태그 설정</h4>

    <div class="form-row">
        <div class="form-group">
            <label>
                <input type="checkbox" name="title_suffix" <?= $_seo_config['title_suffix'] === '1' ? 'checked' : '' ?>>
                제목에 사이트명 자동 추가
            </label>
            <small>예: "게시글 제목 | 사이트명"</small>
        </div>
    </div>

    <div class="form-group">
        <label>설명(Description) 길이</label>
        <input type="number" name="desc_length" value="<?= (int)$_seo_config['desc_length'] ?>" min="50" max="300" style="width:100px">
        <small>자동 생성되는 설명 글자 수 (50-300자)</small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="include_keywords" <?= $_seo_config['include_keywords'] === '1' ? 'checked' : '' ?>>
            키워드에 카테고리/태그 포함
        </label>
    </div>

    <hr style="margin:16px 0">

    <h4 style="margin:12px 0;font-size:14px">소셜 미디어</h4>

    <div class="form-group">
        <label>
            <input type="checkbox" name="og_enabled" <?= $_seo_config['og_enabled'] === '1' ? 'checked' : '' ?>>
            Open Graph 활성화 (카카오톡, 페이스북 공유)
        </label>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="twitter_card" <?= $_seo_config['twitter_card'] === '1' ? 'checked' : '' ?>>
            Twitter Card 활성화
        </label>
    </div>

    <hr style="margin:16px 0">

    <h4 style="margin:12px 0;font-size:14px">고급 설정</h4>

    <div class="form-group">
        <label>
            <input type="checkbox" name="json_ld_enabled" <?= $_seo_config['json_ld_enabled'] === '1' ? 'checked' : '' ?>>
            JSON-LD 스키마 마크업 활성화
        </label>
        <small>검색 엔진이 콘텐츠를 이해하기 쉽도록 함</small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="canonical" <?= $_seo_config['canonical'] === '1' ? 'checked' : '' ?>>
            Canonical URL 자동 추가
        </label>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<div style="margin-top:30px;padding:16px;background:#f0f9ff;border-left:4px solid #3b82f6;border-radius:6px">
    <h4 style="margin:0 0 8px;color:#1e40af">💡 팁</h4>
    <ul style="margin:0;padding-left:20px;font-size:13px;color:#334155">
        <li>검색 엔진은 메타 태그를 통해 페이지 내용을 이해합니다</li>
        <li>설명은 150-160자가 검색 결과에 완전히 표시됩니다</li>
        <li>JSON-LD를 활성화하면 Rich Snippet으로 표시될 수 있습니다</li>
        <li>모든 설정은 자동으로 적용되므로 추가 작업이 필요없습니다</li>
    </ul>
</div>
