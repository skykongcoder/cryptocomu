<?php
/**
 * OpenRouter 모델 목록 공통 헬퍼
 * 모든 AI 플러그인의 모델 드롭다운에서 공유 사용
 *
 * 사용:
 *   require_once __DIR__ . '/../_openrouter_models.php';
 *   echo '<select name="openai_model">' . nb_openrouter_options($currentValue) . '</select>';
 */

if (!function_exists('nb_openrouter_models')) {
    /**
     * OpenRouter 가용 모델 목록 (2026-04-29 검증).
     * 신규 출시/단종된 모델은 https://openrouter.ai/models 에서 확인 후 갱신.
     */
    function nb_openrouter_models() {
        return [
            '⚡ 무료 (Free) — 비용 0원' => [
                'meta-llama/llama-3.3-70b-instruct:free'         => 'Llama 3.3 70B (무료 ★추천, 한국어 OK)',
                'qwen/qwen3-next-80b-a3b-instruct:free'          => 'Qwen3-Next 80B (무료, 한국어 강함, 컨텍스트 262K)',
                'openai/gpt-oss-120b:free'                       => 'GPT-OSS 120B (무료, 고품질)',
                'openai/gpt-oss-20b:free'                        => 'GPT-OSS 20B (무료, 빠름)',
                'google/gemma-3-27b-it:free'                     => 'Gemma 3 27B (무료, Google)',
                'google/gemma-3-12b-it:free'                     => 'Gemma 3 12B (무료, 빠름)',
                'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => 'Dolphin Mistral 24B (무료, 검열 없음)',
            ],
            'OpenAI (유료)' => [
                'openai/gpt-4o-mini'                             => 'GPT-4o-mini (저렴 ★추천)',
                'openai/gpt-4o'                                  => 'GPT-4o (표준)',
                'openai/gpt-4.1-mini'                            => 'GPT-4.1-mini',
                'openai/gpt-4-turbo'                             => 'GPT-4-turbo',
            ],
            'Anthropic Claude (유료)' => [
                'anthropic/claude-3.5-haiku'                     => 'Claude 3.5 Haiku (빠름, 저렴)',
                'anthropic/claude-haiku-4.5'                     => 'Claude Haiku 4.5 (최신 빠른 모델)',
                'anthropic/claude-sonnet-4.6'                    => 'Claude Sonnet 4.6 (고품질, 글쓰기 강함)',
                'anthropic/claude-opus-4.7'                      => 'Claude Opus 4.7 (최고 품질, 비쌈)',
            ],
            'Google Gemini (유료)' => [
                'google/gemini-2.5-flash-lite'                   => 'Gemini 2.5 Flash Lite (저렴, 빠름)',
                'google/gemini-2.5-flash'                        => 'Gemini 2.5 Flash (표준)',
                'google/gemini-2.5-pro'                          => 'Gemini 2.5 Pro (고품질, 긴 컨텍스트)',
                'google/gemini-3-flash-preview'                  => 'Gemini 3 Flash (최신, 빠름)',
            ],
            'DeepSeek (유료, 매우 저렴)' => [
                'deepseek/deepseek-chat'                         => 'DeepSeek Chat (저렴, 한국어 OK)',
                'deepseek/deepseek-r1'                           => 'DeepSeek R1 (추론 특화)',
            ],
            'Meta Llama (유료)' => [
                'meta-llama/llama-3.3-70b-instruct'              => 'Llama 3.3 70B (유료, 안정)',
            ],
            'Mistral (유료)' => [
                'mistralai/mistral-medium-3.1'                   => 'Mistral Medium 3.1',
                'mistralai/mistral-large-2512'                   => 'Mistral Large 2512 (고품질)',
            ],
        ];
    }
}

if (!function_exists('nb_ca_bundle')) {
    /**
     * 프로젝트 동봉 CA 인증서 번들 경로 반환 (Mozilla curated, curl.se).
     * Windows PHP는 기본 cacert.pem 이 없어서 OpenRouter 등 HTTPS 호출 시
     * "unable to get local issuer certificate" 오류 발생 → 이 헬퍼로 해결.
     *
     * 사용:
     *   $ch = curl_init('https://...');
     *   if ($ca = nb_ca_bundle()) curl_setopt($ch, CURLOPT_CAINFO, $ca);
     */
    function nb_ca_bundle() {
        static $cached = false;
        if ($cached !== false) return $cached;
        $candidates = [
            __DIR__ . '/../data/cacert.pem',  // plugins/.. = 프로젝트 루트
            __DIR__ . '/../../data/cacert.pem',
        ];
        foreach ($candidates as $p) {
            if (is_file($p) && is_readable($p)) {
                $cached = realpath($p);
                return $cached;
            }
        }
        $cached = null;
        return null;
    }
}

if (!function_exists('nb_openrouter_is_valid')) {
    /**
     * 모델 ID 형식이 OpenRouter 호환인지 검증.
     * 공식 목록에 없어도 'provider/model' 또는 'provider/model:tag' 형식이면 허용
     * (사용자가 신규 모델을 직접 입력해도 동작하도록).
     */
    function nb_openrouter_is_valid($model) {
        return is_string($model)
            && $model !== ''
            && preg_match('/^[a-z0-9._-]+\/[a-z0-9._:-]+$/i', $model);
    }
}

if (!function_exists('nb_openrouter_options')) {
    function nb_openrouter_options($current = '') {
        // 빈 값이면 가장 추천 모델을 기본 선택
        if ($current === '' || $current === null) $current = 'meta-llama/llama-3.3-70b-instruct:free';
        $html = '';
        $found = false;
        foreach (nb_openrouter_models() as $label => $models) {
            $html .= '<optgroup label="' . htmlspecialchars($label) . '">';
            foreach ($models as $value => $text) {
                $sel = $current === $value ? ' selected' : '';
                if ($sel) $found = true;
                $html .= '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'
                       . htmlspecialchars($text) . '</option>';
            }
            $html .= '</optgroup>';
        }
        // 등록되지 않은 사용자 정의 모델일 경우, 별도 옵션으로 표시해 유실 방지
        if (!$found && $current) {
            $html = '<optgroup label="✏ 사용자 지정">'
                  . '<option value="' . htmlspecialchars($current) . '" selected>' . htmlspecialchars($current) . '</option>'
                  . '</optgroup>' . $html;
        }
        return $html;
    }
}
