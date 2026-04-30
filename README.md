# 크립토니안 (CryptoNian)

> ₿ 한국 암호화폐 커뮤니티 — 시세 / 차트 / 뉴스 / AI 자동 콘텐츠 / 인플루언서 트윗

NuriBoard 3.1.5 기반 PHP 커뮤니티에 크립토 특화 플러그인 + 다크 사이버펑크 테마를 입혀 만든 사이트.

## 주요 기능

- **실시간 시세** (`/coin`) — 업비트 KRW/BTC 마켓 200+ 코인 / 헤더 자동 스크롤 티커
- **TradingView 히트맵** — 메인 페이지 시총 비례 트리맵
- **상승/하락 TOP 5** — 30초 자동 갱신
- **포트폴리오 트래커** (`/portfolio`) — localStorage 기반 자동 손익 계산
- **코인 속보 (RSS)** (`/news`) — 토큰포스트·코인리더스 자동 수집
- **🐋 고래 신호 탐지기** (`/whales`) — 거래량+가격 급변동 자동 감지 + 토스트 알림
- **🐦 인플루언서 X 피드** (`/influencers`) — Vitalik·CZ·Saylor·주기영 등 14명 트윗 + AI 한국어 자동 번역
- **AI 자동 글/댓글** — 게시판별 페르소나로 자연스러운 콘텐츠 자동 생성
- **이벤트 캘린더 / 코인 사전 / 코인 운세 (재미)** — 부가 위젯들

## 기술 스택

- **백엔드**: PHP 8.2+ / MySQL 8.0+
- **프론트엔드**: Vanilla JS + 다크 테마 CSS (사이버펑크 + 글래스모피즘)
- **AI**: OpenRouter (gpt-oss-20b/120b free, llama 3.3, qwen3, gemma 3 폴백)
- **데이터 소스**: Upbit Public API · TokenPost RSS · CoinGecko · X(Twitter) syndication
- **위젯**: TradingView 임베드

## 설치 (로컬 개발)

```bash
# 1. 저장소 clone
git clone https://github.com/<your-username>/cryptocomu.git
cd cryptocomu

# 2. config 복사 + 수정
cp config/config.example.php config/config.php
# DB 정보·secret_key 수정 (secret_key 생성: php -r "echo bin2hex(random_bytes(32));")

# 3. 플러그인 config 복사 (사용할 것만)
cp plugins/ai-auto-comment/config.example.json plugins/ai-auto-comment/config.json
# OpenRouter API 키 등 입력

# 4. DB 생성 + 덤프 import
mysql -u root -p -e "CREATE DATABASE nuriboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p nuriboard < deploy/cryptocomu_db.sql

# 5. 캐시 디렉토리 권한
chmod -R 755 data/cache plugins/*/cache 2>/dev/null

# 6. 로컬 서버
php -S localhost:8090 -t .
```

브라우저에서 `http://localhost:8090` 접속.

## 호스팅 배포

[DEPLOY.md](DEPLOY.md) 참고. 닷홈(DotHome) 무료 호스팅 + GitHub git pull 흐름 가이드 포함.

## 디렉토리 구조

```
cryptocomu/
├── admin/                 # 관리자 패널
├── config/                # 설정 (config.php는 .gitignore)
├── core/                  # 핵심 클래스 (DB, Auth, Router, ...)
├── data/                  # 캐시·업로드 (대부분 .gitignore)
├── plugins/               # 50+ 플러그인
│   ├── crypto-market/     # 시세 / 티커 (자체 제작)
│   ├── crypto-theme/      # 다크 사이버펑크 테마 (자체 제작)
│   ├── crypto-extras/     # 포트폴리오·뉴스·고래·이벤트·사전·운세 (자체 제작)
│   ├── crypto-influencers/# X 인플루언서 트윗 + AI 번역 (자체 제작)
│   ├── ai-auto-comment/   # 게시판 자동 댓글 (NuriBoard 마켓)
│   ├── ai-auto-post-generator/ # 게시판별 자동 글 (확장됨)
│   └── ...
├── theme/default/         # 기본 테마 + 메인 페이지
├── routes/                # 라우터 정의
├── deploy/                # 배포 산출물 (DB 덤프 등, gitignore)
├── scripts/               # 일회성 유틸 (gitignore)
└── index.php              # 진입점
```

## 라이선스

- NuriBoard 본체: **GPL-3.0** (원저작자 누리코리아) — 자세한 내용은 [README_NURIBOARD.md](README_NURIBOARD.md)
- 자체 작성 플러그인(`crypto-*`): 동일 GPL-3.0

## 크레딧

- [NuriBoard](https://nurikorea.com) — 한국형 PHP 커뮤니티 CMS
- [업비트 공개 API](https://docs.upbit.com)
- [OpenRouter](https://openrouter.ai) — 무료 AI 모델
- [TradingView](https://tradingview.com) — 임베드 위젯
- 토큰포스트 / 코인리더스 — RSS 피드
