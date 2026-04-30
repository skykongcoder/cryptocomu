# 누리코리아 SEO 플러그인

SEO 인증 + 광고 추적 스크립트를 한 곳에서 관리.

## 기능
- 구글 서치콘솔 사이트 인증 (HTML 태그 방식)
- 네이버 서치어드바이저 사이트 인증
- Google Analytics 4
- Google Tag Manager
- 페이스북(Meta) 픽셀
- 카카오 픽셀
- 사용자 정의 head HTML

## 사용법

### 구글 서치콘솔 연결 (가장 자주 묻는 질문 해결)
1. [Google Search Console](https://search.google.com/search-console) 접속
2. "속성 추가" → URL 접두어 → 본인 사이트 주소 입력
3. 소유권 확인 방법 중 **"HTML 태그"** 선택
4. 나온 태그 예시: `<meta name="google-site-verification" content="abc123..." />`
5. `content="..."` 안의 **값만** 복사 (또는 태그 통째로 붙여도 자동 추출됨)
6. 누리보드 관리자 → 플러그인 → 이 플러그인 [설정] → "구글 서치콘솔 확인 코드" 란에 붙여넣기
7. 저장
8. 구글 서치콘솔로 돌아가 **"확인"** 버튼 클릭 → 완료

### GA4 추적 설치
1. [Google Analytics](https://analytics.google.com/) 계정 생성
2. 속성 만들기 → 데이터 스트림 → 웹
3. 측정 ID 복사 (`G-XXXXXXXXXX`)
4. 플러그인 설정에 붙여넣기 → 저장
5. 30분 내 실시간 보고서에 접속자 표시됨

## 작동 원리
- 설정 값은 누리보드의 `{prefix}settings` 테이블에 저장
- 구글/네이버 인증은 누리보드 코어(SEO.php)가 자동으로 메타태그 출력
- GA4/GTM/픽셀 등은 플러그인이 `Plugin::queueHeaderAsset()` 으로 head에 주입
- 모든 페이지 `<head>` 내부에 자동 삽입, 페이지별 분리 불필요

## 보안
- 사용자 정의 HTML의 `onclick`, `onload` 등 이벤트 핸들러 자동 제거
- `javascript:` 프로토콜 자동 제거
- 관리자 외에는 설정 변경 불가

## 버전
- 1.0 (2026-04-21)
