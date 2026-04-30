<?php
/**
 * 크립토니안 메인 설정 — 호스팅 환경에 맞게 수정해서 config.php 로 복사
 *
 * 닷홈(DotHome) 무료 호스팅 예시:
 *   db_host: localhost
 *   db_user: 호스트 ID (예: sgkong)
 *   db_pass: 가입 시 받은 DB 비밀번호
 *   db_name: 호스트 ID와 동일 (예: sgkong)
 *   db_prefix: nb_ 그대로
 *   site_url: http://your-id.dothome.co.kr (또는 SSL 신청 후 https://)
 *
 * 로컬 개발:
 *   db_host: localhost
 *   db_user: root
 *   db_pass: ''
 *   db_name: nuriboard
 *   site_url: http://localhost:8090
 */
return [
    'db_host'    => 'localhost',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_name'    => 'nuriboard',
    'db_prefix'  => 'nb_',
    'site_url'   => 'http://localhost:8090',
    // 32-byte 랜덤 hex 문자열로 교체 — 절대 외부 노출 금지
    // 생성: php -r "echo bin2hex(random_bytes(32));"
    'secret_key' => 'CHANGE_ME_TO_RANDOM_64_HEX_CHARS',
];
