# NuriBoard (누리보드)

한국형 커뮤니티 CMS - 누구나 쉽게 커뮤니티를 만들 수 있습니다.

## 주요 특징

- **3단계 설치** - DB 정보 입력 → 관리자 설정 → 완료
- **플러그인 시스템** - ZIP 업로드로 기능 확장, 워드프레스처럼 설치/활성화
- **REST API** - API 키 발급으로 외부 프로그램 연동 (자동 포스팅 등)
- **Webhook** - 글/댓글/가입 이벤트를 외부 서비스로 자동 전송
- **이미지 게시판** - 갤러리 뷰, 메인 썸네일 그리드
- **포인트 시스템** - 글쓰기/댓글/로그인/출석 포인트, 유료 첨부파일
- **회원 레벨** - 10단계 자동 등급, 권한별 접근 제어
- **소셜 로그인** - 카카오/네이버/구글
- **SEO 최적화** - OG태그, 사이트맵, 구조화 데이터 자동 생성
- **이미지 자동 WebP 변환** - 업로드 시 자동 최적화
- **파일 캐싱** - 메인 페이지 쿼리 캐싱으로 빠른 로딩
- **보안** - CSRF, XSS, SQL 인젝션 방지, 브루트포스 차단

## 요구사항

- PHP 7.4 이상
- MySQL 5.7 이상 / MariaDB 10.2 이상
- GD 라이브러리 (이미지 WebP 변환용)

## 설치 방법

1. 파일을 웹서버에 업로드
2. `https://도메인/install.php` 접속
3. DB 정보 입력 → 관리자 계정 생성 → 완료

## 디렉토리 구조

```
nuriboard/
├── admin/          # 관리자 페이지
├── config/         # DB 설정 (설치 시 자동 생성)
├── core/           # 핵심 클래스
│   ├── Auth.php        # 인증/세션/자동로그인
│   ├── DB.php          # PDO 데이터베이스
│   ├── Router.php      # URL 라우팅
│   ├── Board.php       # 게시판
│   ├── Post.php        # 게시글
│   ├── Comment.php     # 댓글
│   ├── Member.php      # 회원
│   ├── Upload.php      # 파일 업로드 (WebP 변환)
│   ├── Point.php       # 포인트
│   ├── Plugin.php      # 플러그인 시스템
│   ├── Cache.php       # 파일 캐시
│   ├── SEO.php         # SEO 메타태그
│   └── ...
├── routes/         # 라우트 정의
│   ├── web.php         # 메인/인증
│   ├── member.php      # 회원/프로필
│   ├── board.php       # 게시판/글/댓글
│   ├── api.php         # REST API
│   └── oauth.php       # 소셜 로그인
├── theme/          # 테마
│   └── default/        # 기본 테마
├── plugins/        # 플러그인 폴더
├── uploads/        # 업로드 파일
├── data/           # 캐시/방문자 데이터
├── index.php       # 메인 엔트리
└── install.php     # 설치 마법사
```

## 플러그인 개발

```
plugins/내플러그인/
├── plugin.json     # 메타 정보
├── plugin.php      # 실행 코드
├── icon.png        # 아이콘 (선택)
└── config.json     # 설정 (자동 생성, 선택)
```

### plugin.json
```json
{
    "name": "플러그인 이름",
    "description": "설명",
    "version": "1.0",
    "author": "작성자"
}
```

### plugin.php
```php
<?php
// 훅: 특정 시점에 코드 실행
Plugin::addHook('post.created', function($postId, $title) {
    // 글 작성 시 실행할 코드
});

// 필터: 데이터 변환
Plugin::addFilter('post.content', function($content) {
    return $content; // 변환된 내용 반환
});

// 관리자 설정 페이지
Plugin::addHook('plugin.settings.폴더명', function() {
    echo '<form>설정 UI</form>';
});
```

### 사용 가능한 훅
| 훅 | 위치 |
|---|---|
| `after_header` | 헤더 아래 |
| `before_content` | 콘텐츠 시작 |
| `after_content` | 콘텐츠 끝 |
| `before_post_content` | 게시글 본문 위 |
| `after_post_content` | 게시글 본문 아래 |
| `after_footer` | 푸터 아래 |
| `body_end` | body 닫기 직전 |

## REST API

API 키: 마이페이지 > API 키 탭에서 발급

```
인증: Authorization: Bearer YOUR_API_KEY

GET  /api/v1/me              # 내 정보
GET  /api/v1/boards          # 게시판 목록
GET  /api/v1/posts           # 글 목록
GET  /api/v1/posts/{id}      # 글 상세
POST /api/v1/posts           # 글 작성
POST /api/v1/comments        # 댓글 작성
```

## 라이선스

GPL-3.0 License - 자유롭게 사용, 수정, 배포 가능
