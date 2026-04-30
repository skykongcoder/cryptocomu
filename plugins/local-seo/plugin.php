<?php
/**
 * 지역 비즈니스 SEO 부스터
 *
 * 구글 지역 검색(Local Pack)에 사업자를 노출시키는 Local SEO 플러그인.
 *
 * 핵심 기능:
 * - LocalBusiness JSON-LD 구조화 데이터 자동 생성 (모든 페이지 head 삽입)
 * - 구글 지도 자동 임베드 (위경도 또는 주소 기반)
 * - NAP(Name/Address/Phone) 푸터 자동 표시 → 일관성 보장
 * - 영업중/마감 실시간 표시
 * - 영업시간 OpeningHoursSpecification 자동 변환
 * - 13가지 사업 카테고리 지원 (식당, 미용실, 병원, 학원 등)
 */

// ── 설정 ────────────────────────────────────────────────────

function _lseo_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/local-seo';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _lseo_load_cfg(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $default = [
        'enabled'         => true,
        'business_type'   => 'LocalBusiness',
        'business_name'   => '',
        'description'     => '',
        'phone'           => '',
        'email'           => '',
        'address'         => '',          // 도로명 주소
        'address_region'  => '',          // 시/도 (예: 서울특별시)
        'address_locality'=> '',          // 시/군/구 (예: 강남구)
        'postal_code'     => '',
        'country'         => 'KR',
        'latitude'        => '',
        'longitude'       => '',
        'logo_url'        => '',
        'image_url'       => '',
        'price_range'     => '$$',         // $, $$, $$$, $$$$
        'hours'           => [             // 요일별 영업시간 (Mo, Tu, We, Th, Fr, Sa, Su)
            'Mo' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'Tu' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'We' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'Th' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'Fr' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
            'Sa' => ['open' => '10:00', 'close' => '17:00', 'closed' => false],
            'Su' => ['open' => '10:00', 'close' => '17:00', 'closed' => true],
        ],
        'social_facebook' => '',
        'social_instagram'=> '',
        'social_youtube'  => '',
        'show_footer_nap' => true,         // 푸터 NAP 자동 표시
        'show_open_status'=> true,         // 영업중/마감 실시간 표시
        'inject_schema'   => true,         // JSON-LD 자동 삽입
        'inject_map'      => false,        // 지도 자동 삽입 (푸터)
        'map_zoom'        => 16,
    ];
    $file = _lseo_data_dir() . '/config.json';
    if (!file_exists($file)) { $cache = $default; return $cache; }
    $data = json_decode(file_get_contents($file), true);
    $cache = is_array($data) ? array_merge($default, $data) : $default;
    return $cache;
}

function _lseo_save_cfg(array $cfg): void {
    file_put_contents(
        _lseo_data_dir() . '/config.json',
        json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// ── 사업 카테고리 (Schema.org LocalBusiness 하위 타입) ───────

function _lseo_business_types(): array {
    return [
        'LocalBusiness'         => '일반 비즈니스',
        'Restaurant'            => '식당 / 음식점',
        'CafeOrCoffeeShop'      => '카페',
        'BarOrPub'              => '바 / 술집',
        'Bakery'                => '베이커리',
        'Store'                 => '소매점 / 상점',
        'BeautySalon'           => '미용실 / 뷰티샵',
        'HairSalon'             => '헤어샵',
        'NailSalon'             => '네일샵',
        'DaySpa'                => '스파 / 마사지',
        'Dentist'               => '치과',
        'MedicalClinic'         => '병원 / 의원',
        'Physician'             => '개인 병원',
        'VeterinaryCare'        => '동물병원',
        'AutoRepair'            => '자동차 정비소',
        'GasStation'            => '주유소',
        'EducationalOrganization'=> '학원 / 교육기관',
        'ChildCare'             => '어린이집 / 유치원',
        'Gym'                   => '헬스장 / 피트니스',
        'SportsActivityLocation'=> '스포츠 시설',
        'TravelAgency'          => '여행사',
        'Lodging'               => '숙박업소',
        'RealEstateAgent'       => '부동산',
        'LegalService'          => '법률사무소',
        'AccountingService'     => '회계 / 세무',
        'FinancialService'      => '금융 서비스',
        'ProfessionalService'   => '전문 서비스',
    ];
}

// ── JSON-LD 생성 ────────────────────────────────────────────

function _lseo_build_jsonld(array $cfg): string {
    if (empty($cfg['business_name'])) return '';

    $site_url = function_exists('nb_setting') ? nb_setting('site_url', '') : '';

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => $cfg['business_type'] ?: 'LocalBusiness',
        'name'     => $cfg['business_name'],
    ];

    if (!empty($cfg['description'])) {
        $schema['description'] = $cfg['description'];
    }
    if ($site_url) {
        $schema['url'] = $site_url;
    }
    if (!empty($cfg['phone'])) {
        $schema['telephone'] = $cfg['phone'];
    }
    if (!empty($cfg['email'])) {
        $schema['email'] = $cfg['email'];
    }

    // 주소
    if (!empty($cfg['address']) || !empty($cfg['address_region'])) {
        $schema['address'] = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $cfg['address']         ?? '',
            'addressLocality' => $cfg['address_locality']?? '',
            'addressRegion'   => $cfg['address_region']  ?? '',
            'postalCode'      => $cfg['postal_code']     ?? '',
            'addressCountry'  => $cfg['country']         ?: 'KR',
        ];
    }

    // 좌표
    if (!empty($cfg['latitude']) && !empty($cfg['longitude'])) {
        $schema['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float)$cfg['latitude'],
            'longitude' => (float)$cfg['longitude'],
        ];
    }

    // 로고/이미지
    if (!empty($cfg['logo_url'])) {
        $schema['logo'] = $cfg['logo_url'];
    }
    if (!empty($cfg['image_url'])) {
        $schema['image'] = $cfg['image_url'];
    }

    // 가격대
    if (!empty($cfg['price_range'])) {
        $schema['priceRange'] = $cfg['price_range'];
    }

    // 영업시간
    $opening = [];
    $day_map = [
        'Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday',
        'Th' => 'Thursday','Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday',
    ];
    foreach (($cfg['hours'] ?? []) as $day_key => $hour) {
        if (!empty($hour['closed'])) continue;
        if (empty($hour['open']) || empty($hour['close'])) continue;
        $opening[] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $day_map[$day_key] ?? $day_key,
            'opens'     => $hour['open'],
            'closes'    => $hour['close'],
        ];
    }
    if ($opening) {
        $schema['openingHoursSpecification'] = $opening;
    }

    // 소셜 링크
    $sameAs = [];
    foreach (['social_facebook', 'social_instagram', 'social_youtube'] as $key) {
        if (!empty($cfg[$key])) $sameAs[] = $cfg[$key];
    }
    if ($sameAs) $schema['sameAs'] = $sameAs;

    return '<script type="application/ld+json">'
         . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
         . '</script>';
}

// ── 영업중/마감 실시간 계산 ──────────────────────────────────

function _lseo_open_status(array $cfg): array {
    $tz = new DateTimeZone('Asia/Seoul');
    $now = new DateTime('now', $tz);

    $day_keys = ['Mo','Tu','We','Th','Fr','Sa','Su']; // Mon=0
    $today_idx = (int)$now->format('w'); // 0=Sun, 1=Mon
    $today_idx = $today_idx === 0 ? 6 : $today_idx - 1;
    $today_key = $day_keys[$today_idx];

    $hours = $cfg['hours'][$today_key] ?? null;
    if (!$hours || !empty($hours['closed'])) {
        // 다음 영업일 찾기
        for ($i = 1; $i <= 7; $i++) {
            $next_idx = ($today_idx + $i) % 7;
            $next = $cfg['hours'][$day_keys[$next_idx]] ?? null;
            if ($next && empty($next['closed']) && !empty($next['open'])) {
                return [
                    'open'    => false,
                    'message' => '오늘 휴무 / 다음 영업: ' . _lseo_day_kr($day_keys[$next_idx]) . ' ' . $next['open'],
                ];
            }
        }
        return ['open' => false, 'message' => '영업일 정보 없음'];
    }

    $now_min   = (int)$now->format('H') * 60 + (int)$now->format('i');
    $open_min  = _lseo_time_to_min($hours['open']);
    $close_min = _lseo_time_to_min($hours['close']);

    if ($now_min >= $open_min && $now_min < $close_min) {
        return ['open' => true, 'message' => '영업중 · ' . $hours['close'] . ' 마감'];
    }
    if ($now_min < $open_min) {
        return ['open' => false, 'message' => '영업 전 · ' . $hours['open'] . ' 영업 시작'];
    }
    // 영업 종료 후 → 다음 영업일
    for ($i = 1; $i <= 7; $i++) {
        $next_idx = ($today_idx + $i) % 7;
        $next = $cfg['hours'][$day_keys[$next_idx]] ?? null;
        if ($next && empty($next['closed']) && !empty($next['open'])) {
            return [
                'open'    => false,
                'message' => '영업 종료 · ' . _lseo_day_kr($day_keys[$next_idx]) . ' ' . $next['open'] . ' 영업 시작',
            ];
        }
    }
    return ['open' => false, 'message' => '영업 종료'];
}

function _lseo_time_to_min(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm . ':0'));
    return $h * 60 + $m;
}

function _lseo_day_kr(string $key): string {
    return ['Mo'=>'월','Tu'=>'화','We'=>'수','Th'=>'목','Fr'=>'금','Sa'=>'토','Su'=>'일'][$key] ?? $key;
}

// ── 구글 지도 임베드 HTML ────────────────────────────────────

function _lseo_map_embed(array $cfg, int $height = 300): string {
    if (empty($cfg['business_name'])) return '';

    // 좌표 우선 사용 / 없으면 주소
    if (!empty($cfg['latitude']) && !empty($cfg['longitude'])) {
        $q = $cfg['latitude'] . ',' . $cfg['longitude'];
    } else {
        $addr = trim(($cfg['address_region'] ?? '') . ' ' . ($cfg['address_locality'] ?? '') . ' ' . ($cfg['address'] ?? ''));
        $q = urlencode($addr ?: $cfg['business_name']);
    }
    $zoom = (int)($cfg['map_zoom'] ?? 16);
    $url  = "https://maps.google.com/maps?q={$q}&z={$zoom}&output=embed";

    return '<div class="lseo-map" style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;margin:16px 0">'
         . '<iframe src="' . htmlspecialchars($url) . '" width="100%" height="' . $height . '" '
         . 'style="border:0;display:block" loading="lazy" allowfullscreen></iframe>'
         . '</div>';
}

// ── NAP 푸터 HTML ────────────────────────────────────────────

function _lseo_footer_nap(array $cfg): string {
    if (empty($cfg['business_name'])) return '';

    $status     = !empty($cfg['show_open_status']) ? _lseo_open_status($cfg) : null;
    $addr_full  = trim(($cfg['address_region'] ?? '') . ' ' . ($cfg['address_locality'] ?? '') . ' ' . ($cfg['address'] ?? ''));

    ob_start();
    ?>
    <div class="lseo-nap" style="padding:24px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:13px;color:#475569;text-align:center;line-height:1.8">
        <div style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:8px">
            <?= htmlspecialchars($cfg['business_name']) ?>
            <?php if ($status): ?>
                <span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?= $status['open'] ? '#f0fdf4' : '#fef2f2' ?>;color:<?= $status['open'] ? '#15803d' : '#dc2626' ?>;border:1px solid <?= $status['open'] ? '#bbf7d0' : '#fecaca' ?>">
                    <?= htmlspecialchars($status['message']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($addr_full): ?>
            <div><?= htmlspecialchars($addr_full) ?><?= !empty($cfg['postal_code']) ? ' (' . htmlspecialchars($cfg['postal_code']) . ')' : '' ?></div>
        <?php endif; ?>
        <?php if (!empty($cfg['phone'])): ?>
            <div>
                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $cfg['phone'])) ?>" style="color:#475569;text-decoration:none">
                    전화 <?= htmlspecialchars($cfg['phone']) ?>
                </a>
            </div>
        <?php endif; ?>
        <?php if (!empty($cfg['email'])): ?>
            <div><a href="mailto:<?= htmlspecialchars($cfg['email']) ?>" style="color:#475569;text-decoration:none"><?= htmlspecialchars($cfg['email']) ?></a></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── HTML 출력 가공 (head/footer 주입) ────────────────────────

function _lseo_process_html(string $html): string {
    if (strlen($html) < 100) return $html;
    if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) return $html;

    $cfg = _lseo_load_cfg();
    if (empty($cfg['enabled']) || empty($cfg['business_name'])) return $html;

    // 1. <head>에 JSON-LD 삽입
    if (!empty($cfg['inject_schema'])) {
        $jsonld = _lseo_build_jsonld($cfg);
        if ($jsonld && stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $jsonld . "\n</head>", $html, 1);
        }
    }

    // 2. 푸터에 NAP + 지도 삽입
    $footer_html = '';
    if (!empty($cfg['show_footer_nap'])) {
        $footer_html .= _lseo_footer_nap($cfg);
    }
    if (!empty($cfg['inject_map'])) {
        $footer_html .= _lseo_map_embed($cfg, 280);
    }
    if ($footer_html) {
        // </body> 직전에 삽입
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $footer_html . "\n</body>", $html, 1);
        }
    }

    return $html;
}

// ── 출력 버퍼 등록 ───────────────────────────────────────────

$_lseo_cfg_boot = _lseo_load_cfg();
if (!empty($_lseo_cfg_boot['enabled']) && !empty($_lseo_cfg_boot['business_name'])) {
    $is_cli  = php_sapi_name() === 'cli';
    $is_ajax = defined('NB_AJAX') && NB_AJAX;
    $is_api  = defined('NB_API')  && NB_API;
    if (!$is_cli && !$is_ajax && !$is_api) {
        ob_start('_lseo_process_html');
    }
}
