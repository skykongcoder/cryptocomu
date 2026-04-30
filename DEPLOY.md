# 배포 가이드 — 닷홈(DotHome) + GitHub

## 개요

```
GitHub repo
   ↓ git clone (최초) / git pull (업데이트)
닷홈 무료 호스팅 (PHP 8.4 + MySQL 8.0)
   ↓
https://your-id.dothome.co.kr
```

이 흐름의 장점:
- **버전 관리** — 변경 추적, 문제 시 롤백
- **배포 = `git pull`** — FTP 업로드 안 함
- **민감 정보 분리** — config.php / API 키는 git에 안 올리고 서버에 직접 입력
- **다중 환경** — 로컬 / 닷홈 각자 자기 config 사용

---

## 1단계 — GitHub 저장소 준비

### A) 첫 push (로컬 개발 머신에서)

```bash
cd D:\claudscode\cryptocomu

git init
git add .
git commit -m "Initial commit: 크립토니안 사이트"

# GitHub 에서 repo 만들기 (https://github.com/new) → cryptocomu 라는 이름
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/cryptocomu.git
git push -u origin main
```

### B) 민감 정보 push 안 됐는지 확인

`.gitignore` 가 다음을 제외:
- `config/config.php` (DB 비번 + secret_key)
- `plugins/*/config.json` (API 키들)
- `data/cache/`, `data/uploads/` (런타임)
- `plugins/*/cache/`

`config.example.php` 와 `plugins/*/config.example.json` 만 git 에 포함 (API 키는 빈 값).

검증:
```bash
git log --all --full-history -- "config/config.php"      # 결과 없어야 정상
git log --all --full-history -- "plugins/*/config.json"  # 결과 없어야 정상
```

---

## 2단계 — 닷홈 가입 + 호스트 신청

1. https://dothome.co.kr 가입
2. **무료호스팅 신청** → 호스트 ID(예: `sgkong`) 만들기
3. **트래픽 충전** — 무료 계정도 매월 트래픽 무료 충전 (관리 패널 우측 상단 "트래픽 충전" 버튼)
4. **PHP 버전 8.4 확인** (관리 패널 → 호스팅 관리 → PHP 버전)
5. **SSL 보안인증서 신청** — 호스팅 관리 → SSL 보안인증서 신청 (무료 Let's Encrypt) — **OpenRouter API 호출에 HTTPS 필수**

다음 정보 메모:
- 호스트 ID (예: `sgkong`)
- DB 비밀번호 (가입 시 받음)
- FTP 비밀번호 (호스트 ID = FTP ID)
- SSH 비밀번호 (port 2022)

---

## 3단계 — 닷홈에 git clone (SSH)

```bash
# 본인 PC에서 SSH 접속 (port 2022)
ssh -p 2022 sgkong@dothome.co.kr

# 접속 후 닷홈 서버 안에서:
cd ~/html       # 또는 /hosting/sgkong/html

# 기존 기본 파일들 백업 후 비우기
mv * ../html_backup_$(date +%s)/ 2>/dev/null
mkdir -p ~/html && cd ~/html

# GitHub repo clone (이 디렉토리로 직접)
git clone https://github.com/YOUR_USERNAME/cryptocomu.git .

# 또는 private repo 인 경우 (Personal Access Token 필요)
git clone https://TOKEN@github.com/YOUR_USERNAME/cryptocomu.git .
```

---

## 4단계 — 닷홈에 config 입력 (서버에서 직접)

서버 SSH 접속 상태에서:

```bash
cd ~/html

# 메인 config 만들기
cp config/config.example.php config/config.php
nano config/config.php
```

`config/config.php` 수정:
```php
return [
    'db_host'    => 'localhost',
    'db_user'    => 'sgkong',                              // 호스트 ID
    'db_pass'    => 'YOUR_DB_PASSWORD_FROM_DOTHOME',
    'db_name'    => 'sgkong',                              // 호스트 ID와 동일
    'db_prefix'  => 'nb_',
    'site_url'   => 'https://sgkong.dothome.co.kr',        // SSL 신청 후 https
    'secret_key' => 'GENERATE_RANDOM_64_HEX',              // 아래로 생성
];
```

secret_key 생성:
```bash
php -r 'echo bin2hex(random_bytes(32));'
```

플러그인 config 복사 + API 키 입력:
```bash
cp plugins/ai-auto-comment/config.example.json plugins/ai-auto-comment/config.json
nano plugins/ai-auto-comment/config.json   # openai_api_key 에 OpenRouter 키 입력

cp plugins/ai-auto-post-generator/config.example.json plugins/ai-auto-post-generator/config.json
nano plugins/ai-auto-post-generator/config.json

# ... 사용할 플러그인만 ...
```

---

## 5단계 — DB import

```bash
# 닷홈 서버에서:
mysql -u sgkong -p sgkong < deploy/cryptocomu_db.sql
# (DB 비밀번호 입력)
```

또는 phpMyAdmin 사용:
1. https://sgkong.dothome.co.kr/myadmin 접속
2. sgkong 데이터베이스 선택 → "가져오기" 탭
3. `deploy/cryptocomu_db.sql` 파일 업로드 → 실행

---

## 6단계 — 캐시·업로드 디렉토리 권한

```bash
cd ~/html
mkdir -p data/cache data/uploads
mkdir -p plugins/{crypto-market,crypto-extras,crypto-influencers,ai-auto-comment,ai-auto-post-generator,ai-content-generator,nuri-chat}/cache 2>/dev/null
chmod -R 755 data plugins/*/cache
```

CA 인증서 번들 다운로드 (OpenRouter HTTPS 호출용):
```bash
curl -o data/cacert.pem https://curl.se/ca/cacert.pem
chmod 644 data/cacert.pem
```

---

## 7단계 — 첫 접속 + 확인

`https://sgkong.dothome.co.kr` 접속:
- 메인 페이지 정상 렌더 ✓
- /coin 시세 페이지 — Upbit API 호출 정상 ✓
- /news — 한국어 뉴스 RSS 정상 ✓
- /admin → 로그인 ✓
- 플러그인 → AI 자동 댓글 → "지금 1회 실행" → OpenRouter 호출 정상 ✓

---

## 업데이트 (이후)

코드 수정 후 배포:

**로컬 머신**:
```bash
cd D:\claudscode\cryptocomu
git add .
git commit -m "수정 내용 설명"
git push
```

**닷홈 서버**:
```bash
ssh -p 2022 sgkong@dothome.co.kr
cd ~/html
git pull
# config.php / plugins/*/config.json 은 .gitignore라서 덮어써지지 않음 ✓
```

---

## 트러블슈팅

### "외부 API 호출 안 됨" 오류

닷홈은 무료 호스팅이라 일부 외부 호출에 제한이 있을 수 있습니다.
- **OpenRouter** (`api.openrouter.ai`) — 보통 OK
- **업비트** (`api.upbit.com`) — 보통 OK
- **TradingView 위젯** (브라우저에서 직접 로드) — OK
- 만약 막혀 있다면 닷홈 고객센터에 화이트리스트 요청 또는 다른 호스팅 고려

### "권한 오류" PHP error

```bash
chmod -R 755 data plugins/*/cache
chmod 644 config/config.php plugins/*/config.json
```

### DB 연결 실패

`config/config.php` 의 `db_host` 가 `localhost` 인지 확인. 닷홈은 `localhost` 사용.

### HTTPS 인증서 갱신 안 됨

닷홈 SSL 보안인증서는 90일 자동 갱신. 수동 갱신: 호스팅 관리 → SSL 보안인증서 → 갱신.

---

## 다중 환경 운용

```
GitHub (main branch)
   ├─ 로컬 개발 (D:\claudscode\cryptocomu)
   │     config/config.php → localhost / 개발 DB
   │     plugins/*/config.json → 개발용 API 키
   │
   └─ 닷홈 운영 (~/html)
         config/config.php → 닷홈 DB / 운영 secret_key
         plugins/*/config.json → 운영용 API 키
```

코드는 git으로 동기화, 환경 설정은 각자 다름. 표준 12-factor app 패턴.
