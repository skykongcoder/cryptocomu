#!/bin/bash
# ============================================================
# 크립토니안 Ubuntu 24.04 자동 설치 스크립트 (Vultr / DigitalOcean / 일반 VPS)
#
# 실행:
#   curl -sSL https://raw.githubusercontent.com/skykongcoder/cryptocomu/main/scripts/setup_vultr.sh | bash
#
# 또는 root 로 SSH 접속 후:
#   wget https://raw.githubusercontent.com/skykongcoder/cryptocomu/main/scripts/setup_vultr.sh
#   chmod +x setup_vultr.sh
#   ./setup_vultr.sh
# ============================================================
set -e

REPO_URL="https://github.com/skykongcoder/cryptocomu.git"
SITE_DIR="/var/www/cryptocomu"
DB_NAME="cryptocomu"
DB_USER="cryptouser"
DB_PASS="$(openssl rand -hex 16)"
SECRET_KEY="$(openssl rand -hex 32)"
SERVER_IP="$(curl -sS -m 5 ifconfig.me 2>/dev/null || curl -sS -m 5 icanhazip.com)"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
log()  { echo -e "${GREEN}[+]${NC} $*"; }
warn() { echo -e "${YELLOW}[!]${NC} $*"; }
fail() { echo -e "${RED}[X]${NC} $*"; exit 1; }

[[ $EUID -ne 0 ]] && fail "root 권한 필요. sudo 또는 root 로 실행하세요."
[[ ! -f /etc/os-release ]] && fail "Linux 가 아닌 OS"
. /etc/os-release
[[ "$ID" != "ubuntu" && "$ID" != "debian" ]] && warn "Ubuntu/Debian 이외 OS — 일부 명령 실패 가능"

echo ""
echo "========================================"
echo "  크립토니안 자동 설치"
echo "  Server IP: $SERVER_IP"
echo "  Repo: $REPO_URL"
echo "========================================"
echo ""

# ---------- [1] Swap ----------
log "[1/9] Swap 2GB 생성"
if ! swapon --show 2>/dev/null | grep -q '/swapfile'; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile -L SWAP > /dev/null 2>&1
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    log "    Swap 2GB 활성화"
else
    warn "    Swap 이미 존재"
fi

# ---------- [2] 패키지 설치 ----------
log "[2/9] 시스템 패키지 설치 (nginx, PHP 8.3, MySQL, git)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    nginx git curl unzip ca-certificates ufw \
    php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-gd php8.3-intl \
    mysql-server > /dev/null

# ---------- [3] MySQL 1GB RAM 튜닝 ----------
log "[3/9] MySQL 1GB RAM 튜닝"
cat > /etc/mysql/mysql.conf.d/cryptocomu-tune.cnf <<'EOF'
[mysqld]
innodb_buffer_pool_size = 128M
innodb_log_file_size    = 32M
max_connections         = 50
performance_schema      = OFF
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
EOF
systemctl restart mysql

# ---------- [4] DB 생성 ----------
log "[4/9] DB + 사용자 생성"
mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;"
mysql -e "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# ---------- [5] git clone ----------
log "[5/9] 저장소 clone"
mkdir -p "$SITE_DIR"
if [[ -d "$SITE_DIR/.git" ]]; then
    cd "$SITE_DIR" && git pull
else
    rm -rf "$SITE_DIR"
    git clone "$REPO_URL" "$SITE_DIR"
fi

# ---------- [6] DB import ----------
log "[6/9] DB import"
if [[ -f "$SITE_DIR/deploy/cryptocomu_db.sql" ]]; then
    mysql "$DB_NAME" < "$SITE_DIR/deploy/cryptocomu_db.sql"
    log "    DB import 완료"
else
    warn "    deploy/cryptocomu_db.sql 없음 — install.php 로 신규 설치 필요"
fi

# ---------- [7] config.php ----------
log "[7/9] config.php 생성"
cat > "$SITE_DIR/config/config.php" <<EOF
<?php
return [
    'db_host'    => 'localhost',
    'db_user'    => '$DB_USER',
    'db_pass'    => '$DB_PASS',
    'db_name'    => '$DB_NAME',
    'db_prefix'  => 'nb_',
    'site_url'   => 'http://$SERVER_IP',
    'secret_key' => '$SECRET_KEY',
];
EOF

# CA bundle (OpenRouter HTTPS 호출용)
curl -sSL -o "$SITE_DIR/data/cacert.pem" https://curl.se/ca/cacert.pem 2>/dev/null || true

# DB 의 site_url 업데이트
mysql "$DB_NAME" -e "UPDATE nb_settings SET setting_value = 'http://$SERVER_IP' WHERE setting_key = 'site_url';" 2>/dev/null || true

# ---------- [8] nginx 설정 ----------
log "[8/9] nginx 설정"
cat > /etc/nginx/sites-available/cryptocomu <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root $SITE_DIR;
    index index.php;
    client_max_body_size 32M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param HTTP_PROXY "";
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff2?)\$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\.(ht|git|env) {
        deny all;
    }
    location ~ /(config|core|data|deploy)/ {
        deny all;
        return 404;
    }
}
EOF

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/cryptocomu /etc/nginx/sites-enabled/cryptocomu

nginx -t && systemctl restart nginx php8.3-fpm

# ---------- [9] 권한 + 방화벽 ----------
log "[9/9] 권한 + 방화벽"
mkdir -p "$SITE_DIR/data/cache" "$SITE_DIR/data/uploads" "$SITE_DIR/uploads"
for plugin in $SITE_DIR/plugins/*/; do
    mkdir -p "$plugin/cache" 2>/dev/null || true
done
chown -R www-data:www-data "$SITE_DIR"
find "$SITE_DIR" -type d -exec chmod 755 {} \;
find "$SITE_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$SITE_DIR/data" "$SITE_DIR/uploads" "$SITE_DIR/plugins"/*/cache 2>/dev/null || true

ufw allow 22/tcp > /dev/null 2>&1
ufw allow 80/tcp > /dev/null 2>&1
ufw allow 443/tcp > /dev/null 2>&1
ufw --force enable > /dev/null 2>&1

# ---------- 검증 ----------
echo ""
echo "========================================"
echo "  ✅ 설치 완료"
echo "========================================"
echo ""
echo "사이트: http://$SERVER_IP"
echo ""
echo "DB 정보 (config/config.php 에 저장됨):"
echo "  DB: $DB_NAME"
echo "  User: $DB_USER"
echo "  Pass: $DB_PASS"
echo ""
echo "nginx 응답 테스트..."
curl -sS -o /dev/null -w "HTTP %{http_code} ($(echo -n %{size_download}) bytes)\n" "http://localhost/" || warn "응답 실패"
echo ""
echo "다음 작업:"
echo "  1. 브라우저로 http://$SERVER_IP 접속하여 사이트 확인"
echo "  2. /admin 으로 관리자 로그인"
echo "  3. 플러그인 → AI 자동 댓글/글 작성기 → API 키 입력"
echo "  4. 도메인 연결 + Let's Encrypt SSL 무료 신청 (선택)"
echo ""
echo "업데이트: cd $SITE_DIR && git pull"
echo ""
