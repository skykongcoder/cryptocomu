# 누리코리아 색인 가속기

새 글·수정글을 검색엔진에 **즉시 자동 제출**. 평균 30분 내 검색 노출.

## 주요 기능
- **IndexNow 자동 제출** (네이버 · Bing · Yandex · Seznam) — 설정 0, 활성화만으로 작동
- **Google Indexing API** (선택) — Service Account JSON 업로드 시 동작
- **24시간 통계** — 성공/실패 실시간 집계
- **수동 재제출** — 처음 설치 시 기존 글 일괄 제출

## 효과 비교

| 구분 | 노출까지 걸리는 시간 |
|------|---------------------|
| 기본 (이 플러그인 없음) | 1~7일 |
| IndexNow 사용 | 평균 30분 |

## 사용법

### 1. 플러그인 설치 및 활성화
- 마켓에서 설치 → 활성화 버튼 클릭
- **끝.** IndexNow 는 자동으로 작동합니다.

### 2. (선택) Google Indexing API
공식적으로 Google Indexing API 는 **채용공고·라이브 스트림** 사이트에만 허용됩니다.
일반 블로그에서 쓸 수 있지만 언제 막힐지 모름. **필수 아님** — sitemap.xml 로도 Google 색인 됩니다.

설정 방법:
1. [Google Cloud Console](https://console.cloud.google.com/) → 프로젝트 생성
2. Indexing API 활성화
3. Service Account 생성 → 키(JSON) 다운로드
4. Google Search Console 에서 Service Account 이메일을 "소유자" 로 추가
5. 플러그인 설정에 JSON 업로드

## 작동 원리
- 누리보드의 `post_created`, `post_updated` 훅을 가로채서 발동
- IndexNow 인증 키는 플러그인 활성화 시 자동 생성 (32자 hex)
- 사이트 루트에 `{key}.txt` 파일 자동 배치 (IndexNow 검증용)
- 제출 기록은 플러그인 폴더의 `submissions.log` 에 저장 (최근 100건)

## 주의사항
- 게시글 URL 패턴은 `/board/{board_id}/{slug_or_id}` 로 생성됩니다.
- 사이트 URL 설정(`site_url`)이 비어있으면 HTTP_HOST 로 대체합니다.
- IndexNow 는 무료이며 API 키나 계정 가입이 필요 없습니다.

## 버전
- 1.0 (2026-04-21)

## 라이선스
© 2026 누리코리아
