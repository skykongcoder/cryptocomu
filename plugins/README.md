# NuriBoard Plugins

폴더 하나 = 플러그인 하나. 폴더를 넣으면 자동 활성화, 빼면 자동 비활성화.

## 폴더 구조

```
plugins/
  my-plugin/
    plugin.json   (선택 - 메타데이터)
    plugin.php    (필수 - 진입점)
```

## plugin.json

```json
{
  "name": "My Plugin",
  "description": "한 줄 설명",
  "version": "1.0",
  "author": "이름"
}
```

## plugin.php 예제

```php
<?php
// 이 파일은 index.php 로드시 자동으로 require 됨

// 액션 훅 등록 (값 반환 X)
Plugin::addHook('post_created', function($postId, $data) {
    // 글 작성시 실행
});

// 필터 등록 (값 반환 O)
Plugin::addFilter('post_title', function($title) {
    return '⭐ ' . $title;
});

// 에셋 큐 (header/footer에 태그 삽입)
Plugin::queueHeaderAsset('<style>.my-class{color:red}</style>');
Plugin::queueFooterAsset('<script>console.log("hi")</script>');

// 라이프사이클 훅 - 플러그인 첫 설치시 1회만
Plugin::addHook('plugin.activate.my-plugin', function($meta) {
    // DB 테이블 생성 등
});
```

## 주요 훅/필터 목록

### 이벤트 훅 (addHook)

| 이름 | 인자 | 설명 |
|------|------|------|
| `post_created` | `$postId, $data` | 글 작성 |
| `post_updated` | `$postId, $data` | 글 수정 |
| `post_deleted` | `$postId` | 글 삭제 |
| `comment_created` | `$commentId, $data` | 댓글 작성 |
| `upload.after_save` | `$result, $postId, $savePath` | 파일 업로드 완료 |
| `router.before_render` | `$view, $data, $viewFile` | 템플릿 렌더링 직전 |
| `router.after_render` | — | 템플릿 렌더링 직후 |
| `plugin.activate` | `$name, $meta` | 플러그인 최초 설치 |
| `plugin.activate.{name}` | `$meta` | 특정 플러그인 최초 설치 |
| `plugin.deactivate` | `$name` | 플러그인 폴더 제거 |

### 필터 (addFilter)

| 이름 | 값 | 설명 |
|------|------|------|
| `post_title` | `$title` | 글 제목 표시 변환 |
| `upload.before_save` | `$file, $postId` | 업로드 전 `$_FILES` 가공 (null 반환시 거부) |
| `post.search_query` | `$search, $boardId` | 검색어 변환 |
| `post.list_query` | `['where'=>..., 'params'=>...]` | 글 목록 쿼리 where 절 확장 |
| `theme_view_file` | `$viewFile, $view, $theme` | 템플릿 파일 경로 오버라이드 |
| `header.assets` | `$html` | 헤더 에셋 HTML 최종 가공 |
| `footer.assets` | `$html` | 푸터 에셋 HTML 최종 가공 |

## 캐시 드라이버 교체

```php
// plugin.php
class MyRedisCache {
    public function get($key) { /* ... */ }
    public function set($key, $value, $ttl) { /* ... */ }
    public function delete($key) { /* ... */ }
    public function deletePattern($pattern) { /* ... */ }
    public function flush() { /* ... */ }
}
Plugin::setCacheDriver(new MyRedisCache());
```
