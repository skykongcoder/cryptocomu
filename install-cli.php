<?php
/**
 * NuriBoard CLI 설치기 (자동설치 플랫폼 전용)
 *
 * 사용법:
 *   NB_DB_USER=u NB_DB_PASS=p NB_DB_NAME=d \
 *   NB_ADMIN_ID=admin NB_ADMIN_PW=pw \
 *   php install-cli.php
 *
 * 필수 환경변수: NB_DB_USER, NB_DB_NAME, NB_ADMIN_ID, NB_ADMIN_PW
 * 선택 환경변수: NB_DB_HOST(localhost), NB_DB_PASS(''), NB_SITE_NAME(NuriBoard), NB_SITE_HOST(localhost)
 *
 * 종료 코드: 0=성공, 1=환경변수오류, 2=설치실패
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$required = ['NB_DB_USER', 'NB_DB_NAME', 'NB_ADMIN_ID', 'NB_ADMIN_PW'];
foreach ($required as $k) {
    if (getenv($k) === false) {
        fwrite(STDERR, "ERROR: 환경변수 누락: $k\n");
        exit(1);
    }
}

// install.php가 기대하는 전역 상태 주입
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = getenv('NB_SITE_HOST') ?: 'localhost';
$_SERVER['HTTPS'] = '';

$_POST = [
    'action'    => 'install',
    'db_host'   => getenv('NB_DB_HOST') ?: 'localhost',
    'db_user'   => getenv('NB_DB_USER'),
    'db_pass'   => getenv('NB_DB_PASS') ?: '',
    'db_name'   => getenv('NB_DB_NAME'),
    'admin_id'  => getenv('NB_ADMIN_ID'),
    'admin_pw'  => getenv('NB_ADMIN_PW'),
    'admin_pw2' => getenv('NB_ADMIN_PW'),
    'site_name' => getenv('NB_SITE_NAME') ?: 'NuriBoard',
];

// install.php는 JSON 출력 후 exit 하므로 shutdown 함수에서 결과 파싱
register_shutdown_function(function () {
    $out = ob_get_clean();
    $result = json_decode($out ?: '', true);
    if (is_array($result) && !empty($result['success'])) {
        fwrite(STDOUT, "OK\n");
        exit(0);
    }
    $msg = (is_array($result) && !empty($result['message']))
        ? $result['message']
        : (trim((string)$out) ?: '알 수 없는 오류');
    fwrite(STDERR, "FAIL: $msg\n");
    exit(2);
});

ob_start();
require __DIR__ . '/install.php';
