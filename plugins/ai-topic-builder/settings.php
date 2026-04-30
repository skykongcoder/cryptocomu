<?php
/**
 * AI 토픽 빌더 - 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

// plugin.php 가 먼저 로드되어 있으면 _tb_data_dir() 사용, 없으면 fallback
if (!function_exists('_tb_data_dir')) {
    require_once __DIR__ . '/plugin.php';
}
$_tbConfigFile = _tb_data_dir() . '/config.json';
$_tbQueueFile  = _tb_data_dir() . '/queue.json';

$_tbConfigRaw = file_exists($_tbConfigFile) ? json_decode(file_get_contents($_tbConfigFile), true) : [];
if (!is_array($_tbConfigRaw)) $_tbConfigRaw = [];

$_tbConfig = array_merge([
    'ai_provider' => 'openai',
    'openai_api_key' => '',
    'openai_model' => 'openai/gpt-4o-mini',
    'image_source' => 'unsplash',
    'unsplash_api_key' => '',
    'image_enabled' => '1',
    'images_per_post' => 'auto',
    'interval_minutes' => 30,
    'promo_links' => [],
    'promo_links_per_post' => '0-2',
    'auto_mode' => false,
    'last_run' => '',
    'total_generated' => 0,
    // ===== 자동조종(autopilot) 기본값 =====
    'autopilot' => false,
    'autopilot_boards' => [],
    'autopilot_daily_limit' => 20,
    'autopilot_monthly_limit' => 300,
    'autopilot_refill_count' => 2,
    'autopilot_cluster_count' => 10,
    'autopilot_refill_cooldown_hours' => 6,
    'autopilot_dup_threshold' => 70,
    'autopilot_default_style' => '정보형',
    'autopilot_daily_posted' => 0,
    'autopilot_daily_date' => '',
    'autopilot_monthly_posted' => 0,
    'autopilot_monthly_month' => '',
    'autopilot_last_refill' => '',
    'autopilot_last_error' => '',
    'autopilot_error_at' => '',
], $_tbConfigRaw);

$_tbQueueData = file_exists($_tbQueueFile) ? json_decode(file_get_contents($_tbQueueFile), true) : ['projects' => []];
if (!is_array($_tbQueueData)) $_tbQueueData = ['projects' => []];
if (!isset($_tbQueueData['projects'])) $_tbQueueData['projects'] = [];

$msg = '';

// ===== API 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_save_api'])) {
    $_tbConfig['ai_provider'] = 'openai';
    $_tbConfig['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    $_tbConfig['openai_model'] = trim($_POST['openai_model'] ?? 'openai/gpt-4o-mini');
    $_tbConfig['image_enabled'] = isset($_POST['image_enabled']) ? '1' : '0';
    $_tbConfig['image_source'] = in_array($_POST['image_source'] ?? '', ['unsplash', 'dalle']) ? $_POST['image_source'] : 'unsplash';
    $_tbConfig['unsplash_api_key'] = trim($_POST['unsplash_api_key'] ?? '');
    $_tbConfig['images_per_post'] = trim($_POST['images_per_post'] ?? 'auto');
    $_tbConfig['interval_minutes'] = max(5, (int)($_POST['interval_minutes'] ?? 30));

    // 광고 링크 저장
    $anchors = $_POST['promo_anchor'] ?? [];
    $urls = $_POST['promo_url'] ?? [];
    $promoLinks = [];
    foreach ($anchors as $i => $anchor) {
        $anchor = trim($anchor);
        $url = trim($urls[$i] ?? '');
        if ($anchor !== '' && $url !== '') {
            $promoLinks[] = ['anchor' => $anchor, 'url' => $url];
        }
    }
    $_tbConfig['promo_links'] = $promoLinks;
    $_tbConfig['promo_links_per_post'] = in_array($_POST['promo_links_per_post'] ?? '', ['0', '0-1', '0-2', '0-3', '1', '1-2', '2-3', '3-5']) ? $_POST['promo_links_per_post'] : '0-2';
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">API 설정이 저장되었습니다.</div>';
}

// ===== 자동조종(autopilot) 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_save_autopilot'])) {
    $_tbConfig['autopilot'] = isset($_POST['autopilot']) ? true : false;
    $boards = $_POST['autopilot_boards'] ?? [];
    $_tbConfig['autopilot_boards'] = is_array($boards) ? array_values(array_filter(array_map('trim', $boards))) : [];
    $_tbConfig['autopilot_refill_count'] = max(1, min(10, (int)($_POST['autopilot_refill_count'] ?? 2)));
    $_tbConfig['autopilot_cluster_count'] = max(5, min(20, (int)($_POST['autopilot_cluster_count'] ?? 10)));
    $_tbConfig['autopilot_default_style'] = in_array($_POST['autopilot_default_style'] ?? '', ['정보형','후기형','튜토리얼','리스트형']) ? $_POST['autopilot_default_style'] : '정보형';
    $_tbConfig['autopilot_daily_limit'] = max(0, (int)($_POST['autopilot_daily_limit'] ?? 20));
    $_tbConfig['autopilot_monthly_limit'] = max(0, (int)($_POST['autopilot_monthly_limit'] ?? 300));
    $_tbConfig['autopilot_refill_cooldown_hours'] = max(1, (int)($_POST['autopilot_refill_cooldown_hours'] ?? 6));
    $_tbConfig['autopilot_dup_threshold'] = max(0, min(100, (int)($_POST['autopilot_dup_threshold'] ?? 70)));
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">✅ 자동조종 설정이 저장되었습니다.' . ($_tbConfig['autopilot'] ? ' 자동 모드 ON' : '') . '</div>';
}

// ===== 자동조종 카운터 리셋 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_autopilot_reset_counter'])) {
    $_tbConfig['autopilot_daily_posted'] = 0;
    $_tbConfig['autopilot_daily_date'] = date('Y-m-d');
    $_tbConfig['autopilot_monthly_posted'] = 0;
    $_tbConfig['autopilot_monthly_month'] = date('Y-m');
    $_tbConfig['autopilot_last_error'] = '';
    $_tbConfig['autopilot_error_at'] = '';
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">카운터 / 에러 초기화 완료.</div>';
}

// ===== 자동조종: 수동으로 지금 리필 실행 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_autopilot_refill_now'])) {
    require_once __DIR__ . '/plugin.php';
    // 쿨다운 강제 리셋 후 시도
    $_tbConfig['autopilot_last_refill'] = '';
    $_tbConfig['autopilot_last_error'] = '';
    $_tbConfig['autopilot_error_at'] = '';
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $added = _tb_autopilot_refill($_tbConfig, $_tbConfigFile);
    // 저장된 최신 config 다시 읽기
    $_tbConfig = array_merge($_tbConfig, json_decode(file_get_contents($_tbConfigFile), true) ?: []);
    if ($added) {
        $msg = '<div class="alert success">✅ 리필 완료 — ' . $added . '개 새 프로젝트가 큐에 등록됐습니다.</div>';
    } else {
        $err = $_tbConfig['autopilot_last_error'] ?? '알 수 없는 오류';
        $msg = '<div class="alert error">리필 실패: ' . htmlspecialchars($err) . '</div>';
    }
}

// ===== 토픽 맵 설계 (AJAX처럼 동작 — POST로 받아서 AI 호출 후 결과 반환) =====
$designedMap = null;
$designFormData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_design'])) {
    $topic = trim($_POST['topic'] ?? '');
    $clusterCount = max(3, min(30, (int)($_POST['cluster_count'] ?? 10)));
    $style = trim($_POST['style'] ?? '정보형');
    $boardId = trim($_POST['board_id'] ?? '');
    $intervalMinutes = max(5, (int)($_POST['interval_minutes'] ?? 30));

    $designFormData = compact('topic', 'clusterCount', 'style', 'boardId', 'intervalMinutes');

    if ($topic === '' || $boardId === '') {
        $msg = '<div class="alert error">주제와 게시판을 모두 입력해주세요.</div>';
    } else {
        // AI 호출
        require_once __DIR__ . '/plugin.php';
        $result = _tb_design_topic_map($topic, $clusterCount, $style, $_tbConfig);
        if (!$result['success']) {
            $msg = '<div class="alert error">토픽 맵 생성 실패: ' . htmlspecialchars($result['error']) . '</div>';
        } else {
            $designedMap = $result['map'];
        }
    }
}

// ===== 승인 → 큐에 등록 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_approve'])) {
    $mapJson = $_POST['map_json'] ?? '';
    $topic = trim($_POST['topic'] ?? '');
    $style = trim($_POST['style'] ?? '정보형');
    $boardId = trim($_POST['board_id'] ?? '');
    $intervalMinutes = max(5, (int)($_POST['interval_minutes'] ?? 30));

    $map = json_decode($mapJson, true);
    if (!is_array($map) || empty($map['pillar']) || empty($map['clusters'])) {
        $msg = '<div class="alert error">잘못된 토픽 맵 데이터입니다.</div>';
    } else {
        // 큐에 프로젝트 추가
        $items = [];
        $items[] = [
            'type' => 'pillar',
            'title' => $map['pillar']['title'],
            'keyword' => $map['pillar']['keyword'] ?? '',
            'description' => $map['pillar']['description'] ?? '',
            'status' => 'pending',
            'post_id' => 0,
        ];
        foreach ($map['clusters'] as $c) {
            $items[] = [
                'type' => 'cluster',
                'title' => $c['title'] ?? '',
                'keyword' => $c['keyword'] ?? '',
                'description' => $c['description'] ?? '',
                'status' => 'pending',
                'post_id' => 0,
            ];
        }

        $newProject = [
            'id' => uniqid('p_'),
            'topic' => $topic,
            'style' => $style,
            'board_id' => $boardId,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'pillar_post_id' => 0,
            'pillar_title' => '',
            'items' => $items,
        ];

        $_tbQueueData['projects'][] = $newProject;
        // 간격도 저장
        $_tbConfig['interval_minutes'] = $intervalMinutes;
        file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($_tbQueueFile, json_encode($_tbQueueData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = '<div class="alert success">프로젝트가 큐에 등록되었습니다. 간격 발행이 시작됩니다.</div>';
    }
}

// ===== 프로젝트 일시정지/재시작/삭제 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_project_action'])) {
    $projId = $_POST['project_id'] ?? '';
    $action = $_POST['tb_project_action'];
    foreach ($_tbQueueData['projects'] as $idx => $p) {
        if ($p['id'] !== $projId) continue;
        if ($action === 'pause') $_tbQueueData['projects'][$idx]['status'] = 'paused';
        elseif ($action === 'resume') $_tbQueueData['projects'][$idx]['status'] = 'active';
        elseif ($action === 'delete') array_splice($_tbQueueData['projects'], $idx, 1);
        break;
    }
    file_put_contents($_tbQueueFile, json_encode($_tbQueueData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">처리되었습니다.</div>';
}

// ===== 자동 실행 시작 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_start_auto'])) {
    require_once __DIR__ . '/plugin.php';
    $_tbConfig['auto_mode'] = true;
    $_tbConfig['last_run'] = '';  // 첫 실행 간격 무시
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    @set_time_limit(180);
    _tb_process_queue($_tbConfig, $_tbConfigFile);

    // 큐/설정 재로드
    $_tbQueueData = json_decode(file_get_contents($_tbQueueFile), true) ?: ['projects' => []];
    $_tbConfig = array_merge($_tbConfig, json_decode(file_get_contents($_tbConfigFile), true) ?: []);

    $msg = '<div class="alert success">▶ 자동 실행 시작. 첫 글이 생성되었습니다.</div>';
}

// ===== 자동 실행 중지 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_stop_auto'])) {
    $_tbConfig['auto_mode'] = false;
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">⏸ 자동 실행이 중지되었습니다.</div>';
}

// ===== 지금 실행 (수동 트리거) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_run_now'])) {
    require_once __DIR__ . '/plugin.php';
    // last_run 리셋 (간격 무시)
    $_tbConfig['last_run'] = '';
    file_put_contents($_tbConfigFile, json_encode($_tbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 글 1개 생성 시도
    @set_time_limit(180);
    _tb_process_queue($_tbConfig, $_tbConfigFile);

    // 큐 재로드
    $_tbQueueData = file_exists($_tbQueueFile) ? json_decode(file_get_contents($_tbQueueFile), true) : ['projects' => []];
    $_tbConfigRaw = file_exists($_tbConfigFile) ? json_decode(file_get_contents($_tbConfigFile), true) : [];
    $_tbConfig = array_merge($_tbConfig, $_tbConfigRaw);

    // 최근 에러 찾기
    $recentError = '';
    foreach ($_tbQueueData['projects'] as $p) {
        foreach ($p['items'] as $it) {
            if (($it['status'] ?? '') === 'failed' && !empty($it['error'])) {
                $recentError = $it['error'];
            }
        }
    }

    if ($recentError) {
        $msg = '<div class="alert error">실행 완료했지만 실패한 글이 있습니다: ' . htmlspecialchars($recentError) . '</div>';
    } else {
        $msg = '<div class="alert success">✅ 실행 완료. 아래 진행 상황을 확인하세요.</div>';
    }
}

// ===== 깨진 내부링크 일괄 제거 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_clean_broken_links'])) {
    require_once __DIR__ . '/plugin.php';
    $prefix2 = DB::getPrefix();
    // tb-related 블록이 있는 글 전체 조회
    $postsWithLinks = DB::fetchAll(
        "SELECT id, content FROM {$prefix2}posts WHERE content LIKE '%<!--tb-related-start-->%'"
    );
    $cleanCount = 0;
    foreach ($postsWithLinks as $p) {
        $clean = _tb_strip_related_blocks($p['content']);
        if ($clean !== $p['content']) {
            DB::query("UPDATE {$prefix2}posts SET content = ? WHERE id = ?", [trim($clean), $p['id']]);
            $cleanCount++;
        }
    }
    $msg = '<div class="alert success">✅ 내부링크 블록 제거 완료 — ' . $cleanCount . '개 글 정리됨.</div>';
}

// ===== 모든 프로젝트 링크 재생성 (깨진 URL 복구) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_relink_all'])) {
    require_once __DIR__ . '/plugin.php';
    $totalUpdated = 0;
    $projectCount = 0;
    foreach ($_tbQueueData['projects'] as $proj) {
        if (empty($proj['board_id'])) continue;
        $updated = _tb_apply_project_links($proj);
        $totalUpdated += (int)$updated;
        $projectCount++;
    }
    $msg = '<div class="alert success">✅ 전체 프로젝트 링크 재생성 완료 — ' . $projectCount . '개 프로젝트 / ' . $totalUpdated . '개 글 업데이트됨. 깨진 링크(404)는 DB에서 실제 게시판을 다시 찾아 연결했어요.</div>';
}

// ===== 게시판 목록 =====
$prefix = DB::getPrefix();
$boards = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards WHERE is_active = 1 ORDER BY title");

// ===== 기존 글 분석 처리 =====
$_tbAnalysisCacheFile = __DIR__ . '/analysis-cache.json';
$_tbAnalysisResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_analyze'])) {
    $selectedBoards = $_POST['analyze_boards'] ?? [];
    $maxPosts = max(5, min(100, (int)($_POST['analyze_max'] ?? 30)));
    $mode = ($_POST['analyze_mode'] ?? 'title_only') === 'full_content' ? 'full_content' : 'title_only';

    if (empty($selectedBoards)) {
        $msg = '<div class="alert error">최소 1개 게시판을 선택해주세요.</div>';
    } else {
        require_once __DIR__ . '/plugin.php';
        @set_time_limit(180);

        if ($mode === 'title_only') {
            // 제목만 분석 → 새 프로젝트 추천
            $result = _tb_suggest_new_projects_by_boards($selectedBoards, $_tbConfig, $maxPosts);
            if (!$result['success']) {
                $msg = '<div class="alert error">분석 실패: ' . htmlspecialchars($result['error']) . '</div>';
            } else {
                $_tbAnalysisResult = [
                    'mode' => 'title_only',
                    'suggestions' => $result['suggestions'],
                    'total_posts' => $result['total_posts'] ?? 0,
                    'analyzed_at' => date('Y-m-d H:i:s'),
                    'boards' => $selectedBoards,
                ];
                file_put_contents($_tbAnalysisCacheFile, json_encode($_tbAnalysisResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $msg = '<div class="alert success">✅ AI가 ' . count($result['suggestions']) . '개 새 프로젝트를 추천했습니다.</div>';
            }
        } else {
            // 본문까지 분석 → 그룹핑 + 내부링크 자동 적용 + 빠진 주제
            $result = _tb_analyze_existing_posts($selectedBoards, $_tbConfig, $maxPosts);
            if (!$result['success']) {
                $msg = '<div class="alert error">분석 실패: ' . htmlspecialchars($result['error']) . '</div>';
            } else {
                // 내부 링크 자동 적용
                $totalLinked = 0;
                $linkedGroups = 0;
                foreach ($result['analysis']['groups'] ?? [] as $grp) {
                    if (!empty($grp['pillar']) && !empty($grp['clusters'])) {
                        $totalLinked += _tb_apply_internal_links($grp);
                        $linkedGroups++;
                    }
                }

                $_tbAnalysisResult = [
                    'mode' => 'full_content',
                    'analysis' => $result['analysis'],
                    'total_posts' => $result['total_posts'],
                    'analyzed_at' => date('Y-m-d H:i:s'),
                    'boards' => $selectedBoards,
                    'auto_linked' => ['groups' => $linkedGroups, 'posts' => $totalLinked],
                ];
                file_put_contents($_tbAnalysisCacheFile, json_encode($_tbAnalysisResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $msg = '<div class="alert success">✅ 분석 + 내부 링크 자동 연결 완료 (' . $linkedGroups . '개 그룹, ' . $totalLinked . '개 글 업데이트)</div>';
            }
        }
    }
}

// 캐시된 분석 결과 로드
if (!$_tbAnalysisResult && file_exists($_tbAnalysisCacheFile)) {
    $cached = json_decode(file_get_contents($_tbAnalysisCacheFile), true);
    if (is_array($cached) && !empty($cached['analysis'])) {
        $_tbAnalysisResult = $cached;
    }
}

// 내부링크 적용
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_apply_links'])) {
    $groupIdx = (int)$_POST['group_idx'];
    if ($_tbAnalysisResult && isset($_tbAnalysisResult['analysis']['groups'][$groupIdx])) {
        require_once __DIR__ . '/plugin.php';
        $count = _tb_apply_internal_links($_tbAnalysisResult['analysis']['groups'][$groupIdx]);
        $msg = '<div class="alert success">✅ 내부 링크 연결 완료: ' . $count . '개 글 업데이트됨.</div>';
    }
}

// 전체 빠진 주제 일괄 큐 등록
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_queue_all_missing'])) {
    $boardId = $_POST['queue_all_board_id'] ?? '';
    $style = $_POST['queue_all_style'] ?? '정보형';

    if ($_tbAnalysisResult && !empty($_tbAnalysisResult['analysis']['groups']) && $boardId) {
        require_once __DIR__ . '/plugin.php';
        $queuedGroups = 0;
        $totalTopics = 0;
        foreach ($_tbAnalysisResult['analysis']['groups'] as $grp) {
            if (!empty($grp['missing_topics'])) {
                $projId = _tb_queue_missing_topics(
                    $grp['topic'] ?? '주제',
                    $grp['missing_topics'],
                    $boardId,
                    $style,
                    $grp['pillar'] ?? null
                );
                if ($projId) {
                    $queuedGroups++;
                    $totalTopics += count($grp['missing_topics']);
                }
            }
        }
        // 큐 데이터 갱신
        $_tbQueueData = json_decode(file_get_contents($_tbQueueFile), true) ?: ['projects' => []];
        $msg = '<div class="alert success">✅ 전체 빠진 주제 ' . $totalTopics . '개가 ' . $queuedGroups . '개 프로젝트로 큐에 등록되었습니다. <a href="?' . http_build_query(array_merge($_GET, ['tab' => 'queue'])) . '" style="color:#15803d;font-weight:600">큐 관리 탭에서 확인 →</a></div>';
    } else if (!$boardId) {
        $msg = '<div class="alert error">게시판을 선택해주세요.</div>';
    }
}

// 빠진 주제 큐에 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_queue_missing'])) {
    $groupIdx = (int)$_POST['group_idx'];
    $boardId = $_POST['queue_board_id'] ?? '';
    $style = $_POST['queue_style'] ?? '정보형';

    if ($_tbAnalysisResult && isset($_tbAnalysisResult['analysis']['groups'][$groupIdx]) && $boardId) {
        require_once __DIR__ . '/plugin.php';
        $group = $_tbAnalysisResult['analysis']['groups'][$groupIdx];
        $projId = _tb_queue_missing_topics(
            $group['topic'] ?? '주제',
            $group['missing_topics'] ?? [],
            $boardId,
            $style,
            $group['pillar'] ?? null
        );
        if ($projId) {
            $msg = '<div class="alert success">✅ 빠진 주제 ' . count($group['missing_topics'] ?? []) . '개가 큐에 등록되었습니다.</div>';
        } else {
            $msg = '<div class="alert error">큐 등록 실패 (빠진 주제가 없을 수 있음).</div>';
        }
    }
}

// 캐시 비우기
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tb_clear_analysis'])) {
    if (file_exists($_tbAnalysisCacheFile)) unlink($_tbAnalysisCacheFile);
    $_tbAnalysisResult = null;
    $msg = '<div class="alert success">분석 결과가 초기화되었습니다.</div>';
}


// 활성 탭
$activeTab = $_GET['tab'] ?? 'create';
if (isset($_POST['tb_design']) || isset($_POST['tb_approve'])) $activeTab = 'create';
if (isset($_POST['tb_project_action']) || isset($_POST['tb_run_now']) || isset($_POST['tb_start_auto']) || isset($_POST['tb_stop_auto'])) $activeTab = 'queue';
if (isset($_POST['tb_save_api'])) $activeTab = 'api';
if (isset($_POST['tb_analyze']) || isset($_POST['tb_queue_missing']) || isset($_POST['tb_clear_analysis']) || isset($_POST['tb_queue_all_missing'])) $activeTab = 'analyze';
?>

<style>
.tb-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; margin-bottom:24px }
.tb-tab { padding:12px 20px; cursor:pointer; border:none; background:none; font-size:14px; font-weight:600; color:#64748b; border-bottom:2px solid transparent; margin-bottom:-2px }
.tb-tab.active { color:#2563eb; border-bottom-color:#2563eb }
.tb-tab:hover { color:#2563eb }
.tb-panel { display:none }
.tb-panel.active { display:block }

.tb-form-row { display:grid; grid-template-columns:140px 1fr; gap:16px; align-items:center; margin-bottom:16px }
.tb-form-row label { font-weight:600; color:#334155; font-size:14px }
.tb-form-row input[type="text"], .tb-form-row input[type="number"], .tb-form-row input[type="password"], .tb-form-row select, .tb-form-row textarea { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px }
.tb-form-row small { color:#94a3b8; font-size:12px; grid-column:2 }

.tb-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:16px }

.tb-api-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:16px }
.tb-api-card.selected { border-color:#2563eb; background:#eff6ff }
.tb-provider-label { display:flex; align-items:center; gap:8px; font-size:16px; font-weight:700; margin-bottom:16px; color:#1e293b }
.tb-test-btn { padding:8px 16px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; cursor:pointer }
.tb-test-btn:hover { background:#e2e8f0 }
.tb-test-result { display:inline-block; margin-left:12px; font-size:13px; font-weight:600 }

.tb-map-preview { background:#f8fafc; border:2px solid #3b82f6; border-radius:12px; padding:24px; margin:20px 0 }
.tb-pillar { background:#dbeafe; border:2px solid #3b82f6; border-radius:8px; padding:16px; margin-bottom:16px }
.tb-pillar-label { display:inline-block; background:#3b82f6; color:white; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:700; margin-bottom:8px }
.tb-pillar-title { font-size:17px; font-weight:700; color:#1e3a8a; margin-bottom:6px }
.tb-pillar-desc { color:#334155; font-size:13px }
.tb-clusters { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px }
.tb-cluster { background:white; border:1px solid #e2e8f0; border-radius:6px; padding:12px }
.tb-cluster-label { display:inline-block; background:#64748b; color:white; padding:1px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-bottom:6px }
.tb-cluster-title { font-size:14px; font-weight:600; color:#1e293b; margin-bottom:4px }
.tb-cluster-desc { color:#64748b; font-size:12px; line-height:1.5 }
.tb-cluster-kw { color:#2563eb; font-size:11px; margin-top:6px }

.tb-project { background:white; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:12px }
.tb-project-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px }
.tb-project-title { font-size:15px; font-weight:700; color:#1e293b }
.tb-project-status { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700 }
.tb-status-active { background:#dcfce7; color:#166534 }
.tb-status-paused { background:#fef3c7; color:#92400e }
.tb-status-completed { background:#dbeafe; color:#1e40af }

.tb-progress { background:#f1f5f9; border-radius:8px; overflow:hidden; height:8px; margin:12px 0 }
.tb-progress-bar { background:linear-gradient(90deg, #3b82f6, #2563eb); height:100%; transition:width .3s }

.tb-item-list { max-height:200px; overflow-y:auto; border:1px solid #f1f5f9; border-radius:6px; padding:8px; background:#fafbfc }
.tb-item { display:flex; align-items:center; gap:8px; padding:4px 8px; font-size:12px; border-bottom:1px solid #f1f5f9 }
.tb-item:last-child { border-bottom:none }
.tb-item-icon { width:20px; text-align:center }
.tb-item-type { display:inline-block; padding:1px 6px; background:#e2e8f0; border-radius:4px; font-size:10px; font-weight:700 }
.tb-item-type.pillar { background:#3b82f6; color:white }

.tb-stats { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:20px }
.tb-stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; text-align:center }
.tb-stat-num { font-size:22px; font-weight:800; color:#2563eb; line-height:1.2 }
.tb-stat-label { font-size:12px; color:#64748b; margin-top:4px }
</style>

<?= $msg ?>

<div class="tb-tabs">
    <button type="button" class="tb-tab <?= $activeTab === 'create' ? 'active' : '' ?>" onclick="tbShowTab('create')">🚀 새 프로젝트</button>
    <button type="button" class="tb-tab <?= $activeTab === 'queue' ? 'active' : '' ?>" onclick="tbShowTab('queue')">📋 큐 관리</button>
    <button type="button" class="tb-tab <?= $activeTab === 'analyze' ? 'active' : '' ?>" onclick="tbShowTab('analyze')">📊 AI 대시보드</button>
    <button type="button" class="tb-tab <?= $activeTab === 'api' ? 'active' : '' ?>" onclick="tbShowTab('api')">⚙️ API 설정</button>
</div>

<!-- ========== 탭 1: 새 프로젝트 ========== -->
<div class="tb-panel <?= $activeTab === 'create' ? 'active' : '' ?>" id="tb-panel-create">

    <?php if (!$designedMap): ?>
        <div style="padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:16px;font-size:13px;color:#1e40af">
            💡 <strong>Tip:</strong> 어떤 주제로 시작할지 모르겠다면 <a href="?<?= http_build_query(array_merge($_GET, ['tab' => 'analyze'])) ?>" style="color:#2563eb;font-weight:600">📊 AI 대시보드</a>에서 AI 추천을 받아보세요.
        </div>
    <?php endif; ?>

    <?php if ($designedMap): ?>
        <!-- 설계된 토픽 맵 미리보기 + 승인 -->
        <div class="tb-card">
            <h3 style="margin:0 0 8px;font-size:16px">✅ AI가 설계한 토픽 맵</h3>
            <p style="color:#64748b;font-size:13px;margin-bottom:16px">아래 구조로 총 <?= 1 + count($designedMap['clusters']) ?>개 글이 <?= htmlspecialchars($designFormData['intervalMinutes']) ?>분 간격으로 자동 발행됩니다.</p>

            <div class="tb-map-preview">
                <div class="tb-pillar">
                    <span class="tb-pillar-label">PILLAR · 필러 글</span>
                    <div class="tb-pillar-title"><?= htmlspecialchars($designedMap['pillar']['title']) ?></div>
                    <div class="tb-pillar-desc"><?= htmlspecialchars($designedMap['pillar']['description'] ?? '') ?></div>
                    <div style="font-size:12px;color:#2563eb;margin-top:6px">키워드: <?= htmlspecialchars($designedMap['pillar']['keyword'] ?? '') ?></div>
                </div>

                <div class="tb-clusters">
                    <?php foreach ($designedMap['clusters'] as $c): ?>
                        <div class="tb-cluster">
                            <span class="tb-cluster-label">CLUSTER</span>
                            <div class="tb-cluster-title"><?= htmlspecialchars($c['title'] ?? '') ?></div>
                            <div class="tb-cluster-desc"><?= htmlspecialchars($c['description'] ?? '') ?></div>
                            <div class="tb-cluster-kw">🔑 <?= htmlspecialchars($c['keyword'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" style="display:flex;gap:8px;justify-content:flex-end">
                <input type="hidden" name="map_json" value='<?= htmlspecialchars(json_encode($designedMap, JSON_UNESCAPED_UNICODE)) ?>'>
                <input type="hidden" name="topic" value="<?= htmlspecialchars($designFormData['topic']) ?>">
                <input type="hidden" name="style" value="<?= htmlspecialchars($designFormData['style']) ?>">
                <input type="hidden" name="board_id" value="<?= htmlspecialchars($designFormData['boardId']) ?>">
                <input type="hidden" name="interval_minutes" value="<?= htmlspecialchars($designFormData['intervalMinutes']) ?>">
                <a href="?<?= http_build_query(array_merge($_GET, ['tab' => 'create'])) ?>" class="btn">다시 설계</a>
                <button type="submit" name="tb_approve" value="1" class="btn btn-primary">🚀 승인하고 큐에 등록</button>
            </form>
        </div>

    <?php else: ?>
        <!-- 주제 입력 폼 -->
        <div class="tb-card">
            <h3 style="margin:0 0 8px;font-size:16px">🎯 새 토픽 프로젝트 만들기</h3>
            <p style="color:#64748b;font-size:13px;margin-bottom:20px">주제만 입력하면 AI가 필러 글 1개 + 클러스터 글 N개 구조를 자동 설계합니다. 승인 후 자동 발행됩니다.</p>

            <form method="post">
                <div class="tb-form-row">
                    <label>주제</label>
                    <input type="text" name="topic" placeholder="예: 재테크 커뮤니티, 맥북 활용법, 서울 맛집" required>
                </div>

                <div class="tb-form-row">
                    <label>클러스터 개수</label>
                    <select name="cluster_count">
                        <option value="5">5개 (작게)</option>
                        <option value="10" selected>10개 (기본)</option>
                        <option value="15">15개</option>
                        <option value="20">20개 (크게)</option>
                        <option value="30">30개 (대규모)</option>
                    </select>
                </div>

                <div class="tb-form-row">
                    <label>글 스타일</label>
                    <select name="style">
                        <option value="정보형">정보형 (전문 지식 중심)</option>
                        <option value="후기형">후기형 (경험담 중심)</option>
                        <option value="튜토리얼">튜토리얼 (방법/단계 중심)</option>
                        <option value="리스트형">리스트형 (순위/모음)</option>
                    </select>
                </div>

                <div class="tb-form-row">
                    <label>발행 게시판</label>
                    <select name="board_id" required>
                        <option value="">-- 선택 --</option>
                        <?php foreach ($boards as $b): ?>
                            <option value="<?= htmlspecialchars($b['board_id']) ?>"><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tb-form-row">
                    <label>발행 간격</label>
                    <select name="interval_minutes">
                        <option value="10">10분마다</option>
                        <option value="30" selected>30분마다 (추천)</option>
                        <option value="60">1시간마다</option>
                        <option value="180">3시간마다</option>
                        <option value="360">6시간마다</option>
                        <option value="720">12시간마다</option>
                    </select>
                    <small>너무 짧으면 구글이 봇으로 판단할 수 있음. 30분~3시간 권장.</small>
                </div>

                <div style="margin-top:20px;text-align:right">
                    <button type="submit" name="tb_design" value="1" class="btn btn-primary">🧠 AI로 토픽 맵 설계하기</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- ========== 탭 2: 큐 관리 ========== -->
<div class="tb-panel <?= $activeTab === 'queue' ? 'active' : '' ?>" id="tb-panel-queue">
    <?php
    $totalProjects = count($_tbQueueData['projects']);
    $activeCount = count(array_filter($_tbQueueData['projects'], fn($p) => $p['status'] === 'active'));
    $completedCount = count(array_filter($_tbQueueData['projects'], fn($p) => $p['status'] === 'completed'));
    ?>

    <div class="tb-stats">
        <div class="tb-stat">
            <div class="tb-stat-num"><?= $totalProjects ?></div>
            <div class="tb-stat-label">전체 프로젝트</div>
        </div>
        <div class="tb-stat">
            <div class="tb-stat-num"><?= $activeCount ?></div>
            <div class="tb-stat-label">진행 중</div>
        </div>
        <div class="tb-stat">
            <div class="tb-stat-num"><?= (int)($_tbConfig['total_generated'] ?? 0) ?></div>
            <div class="tb-stat-label">총 발행 글</div>
        </div>
    </div>

    <!-- ===== 🤖 자동조종 패널 (완전 자율 AI 모드) ===== -->
    <?php
        $autopilotOn = !empty($_tbConfig['autopilot']);
        $apBoards = $_tbConfig['autopilot_boards'] ?? [];
        if (!is_array($apBoards)) $apBoards = [];
        $apDaily = (int)($_tbConfig['autopilot_daily_posted'] ?? 0);
        $apMonthly = (int)($_tbConfig['autopilot_monthly_posted'] ?? 0);
        $apDailyLimit = (int)($_tbConfig['autopilot_daily_limit'] ?? 20);
        $apMonthlyLimit = (int)($_tbConfig['autopilot_monthly_limit'] ?? 300);
        $apLastRefill = $_tbConfig['autopilot_last_refill'] ?? '';
        $apLastError = $_tbConfig['autopilot_last_error'] ?? '';
    ?>
    <div style="background:<?= $autopilotOn ? '#ecfdf5' : '#f9fafb' ?>;border:2px solid <?= $autopilotOn ? '#22c55e' : '#e5e7eb' ?>;border-radius:12px;padding:18px 20px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:22px">🤖</span>
                <div>
                    <strong style="font-size:16px;color:<?= $autopilotOn ? '#15803d' : '#374151' ?>">자동조종 (완전 자율 AI)</strong>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px">
                        <?php if ($autopilotOn): ?>
                            <span style="color:#15803d;font-weight:600">● 활성</span> — 큐 소진 시 AI가 새 주제 자동 생성 → 계속 발행
                        <?php else: ?>
                            <span style="color:#9ca3af">○ 비활성</span> — 켜면 관리자 개입 없이 스스로 주제 찾고 발행
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($autopilotOn): ?>
                <div style="display:flex;gap:12px;align-items:center;font-size:13px">
                    <div style="text-align:center;background:#fff;padding:6px 14px;border-radius:8px;border:1px solid #bbf7d0">
                        <div style="font-size:11px;color:#6b7280">오늘</div>
                        <strong style="color:<?= ($apDailyLimit > 0 && $apDaily >= $apDailyLimit) ? '#dc2626' : '#15803d' ?>"><?= $apDaily ?>/<?= $apDailyLimit ?></strong>
                    </div>
                    <div style="text-align:center;background:#fff;padding:6px 14px;border-radius:8px;border:1px solid #bbf7d0">
                        <div style="font-size:11px;color:#6b7280">이달</div>
                        <strong style="color:<?= ($apMonthlyLimit > 0 && $apMonthly >= $apMonthlyLimit) ? '#dc2626' : '#15803d' ?>"><?= $apMonthly ?>/<?= $apMonthlyLimit ?></strong>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($apLastError)): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:10px">
            ⚠ 마지막 에러: <?= htmlspecialchars($apLastError) ?> (<?= htmlspecialchars($_tbConfig['autopilot_error_at'] ?? '') ?>)
        </div>
        <?php endif; ?>

        <form method="post" id="autopilot-form">
            <input type="hidden" name="tb_save_autopilot" value="1">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;margin-bottom:14px;padding:10px 12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb">
                <input type="checkbox" name="autopilot" <?= $autopilotOn ? 'checked' : '' ?> style="width:18px;height:18px">
                <strong>자동조종 ON</strong> — 한번 켜두면 관리자 개입 없이 알아서 게시판 분석 → 주제 만들기 → 발행 → 반복
            </label>

            <div style="background:#fff;padding:14px;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:10px">
                <div style="font-weight:600;margin-bottom:8px;font-size:13px">대상 게시판 (체크한 게시판을 AI가 분석/발행)</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;max-height:180px;overflow-y:auto">
                    <?php foreach ($boards as $b): ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#f9fafb;border-radius:6px;cursor:pointer;font-size:13px">
                            <input type="checkbox" name="autopilot_boards[]" value="<?= htmlspecialchars($b['board_id']) ?>" <?= in_array($b['board_id'], $apBoards, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($b['title']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:10px">
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">일일 한도</label>
                    <input type="number" name="autopilot_daily_limit" value="<?= (int)$_tbConfig['autopilot_daily_limit'] ?>" min="0" max="500" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                    <small style="color:#9ca3af;font-size:11px">0=무제한</small>
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">월간 한도</label>
                    <input type="number" name="autopilot_monthly_limit" value="<?= (int)$_tbConfig['autopilot_monthly_limit'] ?>" min="0" max="10000" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">리필 프로젝트 수</label>
                    <input type="number" name="autopilot_refill_count" value="<?= (int)$_tbConfig['autopilot_refill_count'] ?>" min="1" max="10" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                    <small style="color:#9ca3af;font-size:11px">큐 비면 N개 생성</small>
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">클러스터 수</label>
                    <input type="number" name="autopilot_cluster_count" value="<?= (int)$_tbConfig['autopilot_cluster_count'] ?>" min="5" max="20" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                    <small style="color:#9ca3af;font-size:11px">프로젝트당</small>
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">리필 쿨다운 (시간)</label>
                    <input type="number" name="autopilot_refill_cooldown_hours" value="<?= (int)$_tbConfig['autopilot_refill_cooldown_hours'] ?>" min="1" max="168" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                    <small style="color:#9ca3af;font-size:11px">이 시간 지나야 재리필</small>
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">중복 차단 기준 (%)</label>
                    <input type="number" name="autopilot_dup_threshold" value="<?= (int)$_tbConfig['autopilot_dup_threshold'] ?>" min="0" max="100" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                    <small style="color:#9ca3af;font-size:11px">유사도 이상이면 skip (60~80 권장)</small>
                </div>
                <div><label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">기본 스타일</label>
                    <select name="autopilot_default_style" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px">
                        <?php foreach (['정보형','후기형','튜토리얼','리스트형'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($_tbConfig['autopilot_default_style'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button type="submit" class="btn btn-primary" style="background:#22c55e;border-color:#22c55e">💾 자동조종 설정 저장</button>
                <button type="submit" formmethod="post" formaction="" name="tb_autopilot_refill_now" value="1" class="btn" style="background:#dbeafe;border-color:#93c5fd;color:#1e40af" onclick="return confirm('지금 바로 새 프로젝트 리필을 시도합니다. (OpenAI 호출 발생)')">⚡ 지금 리필 테스트</button>
                <button type="submit" formmethod="post" formaction="" name="tb_autopilot_reset_counter" value="1" class="btn" style="background:#f3f4f6" onclick="return confirm('오늘/이달 카운터와 에러 로그를 초기화합니다.')">🔄 카운터 리셋</button>
                <span style="margin-left:auto;font-size:11px;color:#9ca3af">
                    <?php if ($apLastRefill): ?>마지막 리필: <?= date('m/d H:i', strtotime($apLastRefill)) ?><?php endif; ?>
                </span>
            </div>
        </form>
    </div>

    <?php if ($activeCount > 0):
        $autoMode = !empty($_tbConfig['auto_mode']);
        $lastRunTs = $_tbConfig['last_run'] ? strtotime($_tbConfig['last_run']) : 0;
        $intervalSec = (int)($_tbConfig['interval_minutes'] ?? 30) * 60;

        // 대기 중 글 수 계산
        $pendingCount = 0;
        $doneCount = 0;
        $totalItems = 0;
        foreach ($_tbQueueData['projects'] as $p) {
            if ($p['status'] !== 'active') continue;
            foreach ($p['items'] as $it) {
                $totalItems++;
                if ($it['status'] === 'pending') $pendingCount++;
                if ($it['status'] === 'done') $doneCount++;
            }
        }
    ?>

    <?php if (!$autoMode): ?>
        <!-- 자동 실행 OFF 상태 -->
        <div style="background:#fef3c7;border:1px solid #fde047;border-radius:8px;padding:16px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <strong style="color:#854d0e;font-size:14px">▶ 자동 실행</strong>
                    <div style="font-size:12px;color:#78716c;margin-top:4px">
                        시작하면 첫 글이 바로 생성되고, <?= (int)($_tbConfig['interval_minutes'] ?? 30) ?>분마다 자동으로 1개씩 발행됩니다.<br>
                        ⚠️ 이 탭을 열어둔 상태에서만 작동합니다. (탭 닫으면 중지)
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <form method="post" style="margin:0" onsubmit="document.getElementById('tb-start-auto-btn').innerText='⏳ 첫 글 생성 중... (30~60초)';">
                        <input type="hidden" name="tb_start_auto" value="1">
                        <button type="submit" id="tb-start-auto-btn" class="btn btn-primary" style="background:#16a34a;border-color:#16a34a">▶ 자동 실행 시작</button>
                    </form>
                    <form method="post" style="margin:0" onsubmit="document.getElementById('tb-run-btn').innerText='⏳ 생성 중...';">
                        <input type="hidden" name="tb_run_now" value="1">
                        <button type="submit" id="tb-run-btn" class="btn">⚡ 1개만 실행</button>
                    </form>
                    <form method="post" style="margin:0" onsubmit="return confirm('전체 프로젝트를 다시 훑어서 링크를 재생성합니다.\n(404 뜨는 링크 → DB에서 실제 게시판 찾아서 올바른 URL로 교체)\n진행하시겠습니까?')">
                        <input type="hidden" name="tb_relink_all" value="1">
                        <button type="submit" class="btn" style="background:#ecfdf5;border-color:#86efac;color:#15803d">🔄 링크 재생성 (404 복구)</button>
                    </form>
                    <form method="post" style="margin:0" onsubmit="return confirm('기존 글에 삽입된 관련글 링크 박스를 모두 제거합니다.\n(깨진 404 링크 정리용)\n진행하시겠습니까?')">
                        <input type="hidden" name="tb_clean_broken_links" value="1">
                        <button type="submit" class="btn" style="background:#fef2f2;border-color:#fca5a5;color:#dc2626">🧹 깨진 링크 전체 제거</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- 자동 실행 ON 상태 -->
        <?php if ($pendingCount === 0): ?>
            <div style="background:#dbeafe;border:2px solid #3b82f6;border-radius:10px;padding:16px;margin-bottom:16px;text-align:center">
                <strong style="color:#1e40af;font-size:16px">🎉 모든 글 발행 완료!</strong>
                <div style="font-size:13px;color:#1e40af;margin-top:4px">더 이상 생성할 글이 없습니다. 총 <?= $doneCount ?>개 발행 완료.</div>
                <div style="margin-top:12px">
                    <form method="post" style="margin:0;display:inline">
                        <input type="hidden" name="tb_stop_auto" value="1">
                        <button type="submit" class="btn btn-primary">확인</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div style="background:linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%);border:2px solid #16a34a;border-radius:10px;padding:16px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
                    <div style="flex:1;min-width:240px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div id="tb-pulse" style="width:12px;height:12px;background:#16a34a;border-radius:50%;animation:tbPulse 1.5s infinite"></div>
                            <strong style="color:#166534;font-size:15px">🔄 자동 실행 중 (<?= $doneCount ?>/<?= $totalItems ?> 완료)</strong>
                        </div>
                        <div style="font-size:13px;color:#166534;margin-top:8px">
                            다음 포스팅: <strong style="font-size:16px" id="tb-countdown">계산 중...</strong>
                        </div>
                        <div style="font-size:11px;color:#15803d;margin-top:4px" id="tb-auto-status">
                            마지막 실행: <?= $lastRunTs ? date('H:i:s', $lastRunTs) : '없음' ?> · 간격 <?= (int)($_tbConfig['interval_minutes'] ?? 30) ?>분 · 남은 글 <?= $pendingCount ?>개
                        </div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <form method="post" style="margin:0">
                            <input type="hidden" name="tb_stop_auto" value="1">
                            <button type="submit" class="btn" style="background:#dc2626;color:white;border-color:#dc2626;white-space:nowrap">⏸ 자동 실행 중지</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 완료 시 auto_mode 끄는 폼 -->
        <?php if ($pendingCount === 0): ?>
        <form method="post" style="display:none" id="tb-auto-complete-form">
            <input type="hidden" name="tb_stop_auto" value="1">
        </form>
        <?php endif; ?>

        <!-- 자동 실행 폼 (JS가 자동 제출) -->
        <form id="tb-auto-run-form" method="post" style="display:none">
            <input type="hidden" name="tb_run_now" value="1">
        </form>

        <style>
        @keyframes tbPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }
        </style>

        <script>
        (function() {
            const countdownEl = document.getElementById('tb-countdown');
            if (!countdownEl) return; // 완료 상태면 스킵

            const lastRunTs = <?= $lastRunTs ?: 0 ?>;
            const intervalSec = <?= $intervalSec ?>;
            const now = Math.floor(Date.now() / 1000);

            // 다음 실행 시각 (last_run 없으면 즉시 실행)
            let nextRunTs = lastRunTs ? lastRunTs + intervalSec : now;

            function tbUpdateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = nextRunTs - now;

                if (remaining <= 0) {
                    countdownEl.innerText = '⏳ 지금 생성 중...';
                    const statusEl = document.getElementById('tb-auto-status');
                    if (statusEl) statusEl.innerText = '글 생성 중입니다. 30~60초 기다려주세요...';
                    const form = document.getElementById('tb-auto-run-form');
                    if (form) form.submit();
                    return;
                }

                const min = Math.floor(remaining / 60);
                const sec = remaining % 60;
                countdownEl.innerText = min + '분 ' + String(sec).padStart(2, '0') + '초 후';
                setTimeout(tbUpdateCountdown, 1000);
            }

            tbUpdateCountdown();
        })();
        </script>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($_tbQueueData['projects'])): ?>
        <div style="text-align:center;padding:60px 20px;color:#94a3b8">
            <div style="font-size:40px;margin-bottom:12px">📭</div>
            <div>아직 프로젝트가 없습니다</div>
            <div style="font-size:12px;margin-top:4px">"새 프로젝트" 탭에서 시작하세요</div>
        </div>
    <?php else: ?>
        <?php foreach (array_reverse($_tbQueueData['projects']) as $project):
            $items = $project['items'] ?? [];
            $total = count($items);
            $done = count(array_filter($items, fn($it) => $it['status'] === 'done'));
            $failed = count(array_filter($items, fn($it) => $it['status'] === 'failed'));
            $progress = $total > 0 ? round(($done / $total) * 100) : 0;
        ?>
            <div class="tb-project">
                <div class="tb-project-header">
                    <div>
                        <div class="tb-project-title"><?= htmlspecialchars($project['topic']) ?></div>
                        <div style="font-size:12px;color:#64748b;margin-top:2px">
                            📅 <?= htmlspecialchars($project['created_at']) ?>
                            · 🎨 <?= htmlspecialchars($project['style']) ?>
                            · 📝 <?= htmlspecialchars($project['board_id']) ?>
                        </div>
                    </div>
                    <div>
                        <span class="tb-project-status tb-status-<?= $project['status'] ?>">
                            <?= ['active' => '진행중', 'paused' => '일시정지', 'completed' => '완료'][$project['status']] ?? $project['status'] ?>
                        </span>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px">
                    <span><?= $done ?> / <?= $total ?> 완료<?= $failed > 0 ? " (실패 $failed)" : '' ?></span>
                    <span><?= $progress ?>%</span>
                </div>
                <div class="tb-progress">
                    <div class="tb-progress-bar" style="width:<?= $progress ?>%"></div>
                </div>

                <details style="margin-top:12px">
                    <summary style="cursor:pointer;font-size:13px;color:#64748b;font-weight:600">📋 글 목록 펼치기 (<?= $total ?>개)</summary>

                    <div class="tb-item-list" style="margin-top:12px">
                        <?php foreach ($items as $it):
                            $icon = ['pending' => '⏳', 'done' => '✅', 'failed' => '❌'][$it['status']] ?? '•';
                        ?>
                            <div class="tb-item">
                                <span class="tb-item-icon"><?= $icon ?></span>
                                <span class="tb-item-type <?= $it['type'] ?>"><?= strtoupper($it['type']) ?></span>
                                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($it['title']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>

                <div style="display:flex;gap:6px;margin-top:12px;justify-content:flex-end">
                    <form method="post" style="margin:0">
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                        <?php if ($project['status'] === 'active'): ?>
                            <button type="submit" name="tb_project_action" value="pause" class="btn btn-sm">⏸ 일시정지</button>
                        <?php elseif ($project['status'] === 'paused'): ?>
                            <button type="submit" name="tb_project_action" value="resume" class="btn btn-sm btn-primary">▶ 재시작</button>
                        <?php endif; ?>
                        <button type="submit" name="tb_project_action" value="delete" class="btn btn-sm" style="color:#dc2626" onclick="return confirm('이 프로젝트를 삭제할까요? (이미 발행된 글은 삭제되지 않습니다)')">🗑 삭제</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ========== 탭: AI 대시보드 ========== -->
<div class="tb-panel <?= $activeTab === 'analyze' ? 'active' : '' ?>" id="tb-panel-analyze">

    <?php if (!$_tbAnalysisResult): ?>
        <div class="tb-card">
            <h3 style="margin:0 0 8px;font-size:16px">🤖 AI 분석 대시보드</h3>
            <p style="color:#64748b;font-size:13px;margin-bottom:16px">
                기존 글을 AI가 분석해서 <strong>새 주제 추천</strong> 또는 <strong>내부 링크 자동 연결 + 빠진 주제 보충</strong>을 도와줍니다.
            </p>

            <form method="post" onsubmit="document.getElementById('tb-analyze-btn').innerText='⏳ 분석 중 (30~90초)...';">
                <input type="hidden" name="tb_analyze" value="1">

                <!-- 분석 모드 선택 (라디오 카드) -->
                <div class="tb-form-row" style="grid-template-columns:140px 1fr">
                    <label>분석 유형</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label style="border:2px solid #e2e8f0;border-radius:8px;padding:14px;cursor:pointer;display:block;font-weight:normal" onclick="this.parentElement.querySelectorAll('label').forEach(l=>l.style.borderColor='#e2e8f0');this.style.borderColor='#3b82f6';this.querySelector('input').checked=true">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                                <input type="radio" name="analyze_mode" value="title_only" checked>
                                <strong style="color:#1e293b">⚡ 제목만 분석</strong>
                            </div>
                            <div style="font-size:12px;color:#64748b;line-height:1.5">
                                빠르고 저렴 (~$0.003)<br>
                                → <strong>새 주제 3개 추천</strong>
                            </div>
                        </label>

                        <label style="border:2px solid #e2e8f0;border-radius:8px;padding:14px;cursor:pointer;display:block;font-weight:normal" onclick="this.parentElement.querySelectorAll('label').forEach(l=>l.style.borderColor='#e2e8f0');this.style.borderColor='#3b82f6';this.querySelector('input').checked=true">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                                <input type="radio" name="analyze_mode" value="full_content">
                                <strong style="color:#1e293b">🔬 본문까지 분석</strong>
                            </div>
                            <div style="font-size:12px;color:#64748b;line-height:1.5">
                                정확 (~$0.01 ~ $0.04)<br>
                                → <strong>그룹핑 + 내부링크 + 빠진 주제</strong>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="tb-form-row">
                    <label>분석할 게시판</label>
                    <div>
                        <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#dbeafe;border:1px solid #3b82f6;border-radius:6px;cursor:pointer;font-weight:600;color:#1e40af;margin-bottom:8px">
                            <input type="checkbox" id="tb-board-all" onclick="tbToggleAllBoards(this.checked)">
                            전체 선택
                        </label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <?php foreach ($boards as $b): ?>
                                <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f1f5f9;border-radius:6px;cursor:pointer;font-weight:normal">
                                    <input type="checkbox" name="analyze_boards[]" value="<?= htmlspecialchars($b['board_id']) ?>" class="tb-board-cb">
                                    <?= htmlspecialchars($b['title']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="tb-form-row">
                    <label>분석 글 수</label>
                    <select name="analyze_max">
                        <option value="10">10개 (빠름)</option>
                        <option value="30" selected>30개 (추천)</option>
                        <option value="50">50개</option>
                        <option value="100">100개 (느림)</option>
                    </select>
                    <small>최신순으로 가져옵니다. 글 수가 많을수록 AI 비용 증가.</small>
                </div>

                <div style="margin-top:20px;text-align:right">
                    <button type="submit" id="tb-analyze-btn" class="btn btn-primary">🧠 AI로 분석 시작</button>
                </div>
            </form>
        </div>

    <?php else:
        $mode = $_tbAnalysisResult['mode'] ?? 'full_content';
    ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <div>
                <h3 style="margin:0;font-size:16px">📊 분석 결과 —
                    <?= $mode === 'title_only' ? '⚡ 제목만 분석' : '🔬 본문까지 분석' ?>
                </h3>
                <div style="font-size:12px;color:#64748b;margin-top:4px">
                    분석 글 수: <?= (int)($_tbAnalysisResult['total_posts'] ?? 0) ?>개
                    · 분석 시각: <?= htmlspecialchars($_tbAnalysisResult['analyzed_at'] ?? '') ?>
                </div>
            </div>
            <form method="post" style="margin:0">
                <button type="submit" name="tb_clear_analysis" value="1" class="btn btn-sm" onclick="return confirm('분석 결과를 초기화하고 다시 분석할까요?')">🔄 다시 분석</button>
            </form>
        </div>

        <?php if ($mode === 'title_only'):
            // 제목만 분석 → 새 프로젝트 추천
            $suggestions = $_tbAnalysisResult['suggestions'] ?? [];
        ?>
            <?php if (empty($suggestions)): ?>
                <div style="padding:30px;text-align:center;color:#94a3b8;background:#f8fafc;border-radius:8px">AI가 추천을 생성하지 못했습니다.</div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
                    <?php foreach ($suggestions as $sugg):
                        $topic = htmlspecialchars($sugg['topic'] ?? '');
                        $reason = htmlspecialchars($sugg['reason'] ?? '');
                        $clusterCount = (int)($sugg['cluster_count'] ?? 10);
                        $style = htmlspecialchars($sugg['style'] ?? '정보형');
                        $score = (int)($sugg['seo_score'] ?? 0);
                    ?>
                        <div style="background:white;border:2px solid #fde68a;border-radius:10px;padding:16px;position:relative">
                            <div style="position:absolute;top:10px;right:12px;font-size:11px;font-weight:800;padding:3px 10px;border-radius:12px;color:white;background:<?= $score >= 80 ? '#16a34a' : ($score >= 60 ? '#f59e0b' : '#94a3b8') ?>">SEO <?= $score ?></div>
                            <div style="font-weight:700;font-size:15px;color:#1e293b;margin-bottom:8px;padding-right:70px"><?= $topic ?></div>
                            <div style="font-size:12px;color:#64748b;line-height:1.5;margin-bottom:12px"><?= $reason ?></div>
                            <div style="display:flex;gap:8px;font-size:11px;color:#94a3b8;margin-bottom:12px">
                                <span>🎨 <?= $style ?></span>·<span>📝 <?= $clusterCount ?>개 글</span>
                            </div>
                            <button type="button" class="btn btn-primary" style="width:100%" onclick="tbApplySuggestion('<?= addslashes($topic) ?>', <?= $clusterCount ?>, '<?= addslashes($style) ?>');tbShowTabByName('create')">이 주제로 시작 →</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else:
            // 본문까지 분석 → 그룹핑
            $analysis = $_tbAnalysisResult['analysis'] ?? ['groups' => []];
            $groups = $analysis['groups'] ?? [];
            $orphanIds = $analysis['orphan_ids'] ?? [];
        ?>
            <div style="font-size:12px;color:#64748b;margin-bottom:16px">
                토픽 그룹: <?= count($groups) ?>개 · 미분류: <?= count($orphanIds) ?>개
            </div>

            <?php if (empty($groups)): ?>
                <div style="padding:30px;text-align:center;color:#94a3b8;background:#f8fafc;border-radius:8px">
                    AI가 그룹을 찾지 못했습니다. 글이 다양한 주제라 재구성이 어려울 수 있습니다.
                </div>
            <?php else:
                // 통계 계산
                $totalGroupsWithClusters = 0;
                $totalMissingTopics = 0;
                foreach ($groups as $grp) {
                    if (!empty($grp['pillar']) && !empty($grp['clusters'])) $totalGroupsWithClusters++;
                    $totalMissingTopics += count($grp['missing_topics'] ?? []);
                }
            ?>
                <?php
                $autoLinked = $_tbAnalysisResult['auto_linked'] ?? null;
                if ($autoLinked && $autoLinked['groups'] > 0):
                ?>
                    <div style="background:#dcfce7;border:1px solid #22c55e;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#166534">
                        🔗 <strong>내부 링크 자동 적용됨:</strong> <?= $autoLinked['groups'] ?>개 그룹, <?= $autoLinked['posts'] ?>개 글에 관련글 박스가 추가되었습니다.
                    </div>
                <?php endif; ?>

                <!-- 전체 큐에 등록 바 -->
                <?php if ($totalMissingTopics > 0): ?>
                    <div style="background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);border:1px solid #f59e0b;border-radius:10px;padding:14px 18px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                            <div>
                                <strong style="color:#854d0e;font-size:14px">✨ 빠진 주제를 AI가 자동 작성</strong>
                                <div style="font-size:12px;color:#78716c;margin-top:2px">
                                    아래 빠진 주제 총 <?= $totalMissingTopics ?>개를 큐에 등록 → 큐 관리 탭에서 자동 발행
                                </div>
                            </div>
                            <form method="post" style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap" onsubmit="return confirm('빠진 주제 <?= $totalMissingTopics ?>개를 큐에 등록합니다. 진행할까요?')">
                                <select name="queue_all_board_id" required style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                    <option value="">-- 발행 게시판 --</option>
                                    <?php foreach ($boards as $b): ?>
                                        <option value="<?= htmlspecialchars($b['board_id']) ?>"><?= htmlspecialchars($b['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="queue_all_style" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                    <option value="정보형">정보형</option>
                                    <option value="후기형">후기형</option>
                                    <option value="튜토리얼">튜토리얼</option>
                                    <option value="리스트형">리스트형</option>
                                </select>
                                <button type="submit" name="tb_queue_all_missing" value="1" class="btn btn-sm" style="background:#f59e0b;color:white;border-color:#f59e0b">✨ 전체 큐에 등록</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            <?php foreach ($groups as $gidx => $group):
                $pillar = $group['pillar'] ?? null;
                $clusters = $group['clusters'] ?? [];
                $missingTopics = $group['missing_topics'] ?? [];
            ?>
                <div class="tb-card" style="border-left:4px solid #3b82f6">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                        <h4 style="margin:0;font-size:15px;color:#1e293b">📂 <?= htmlspecialchars($group['topic'] ?? '그룹 ' . ($gidx + 1)) ?></h4>
                        <span style="font-size:12px;color:#64748b"><?= count($clusters) + ($pillar ? 1 : 0) ?>개 글</span>
                    </div>

                    <?php if ($pillar): ?>
                        <div style="background:#dbeafe;border:1px solid #3b82f6;border-radius:6px;padding:10px 14px;margin:10px 0">
                            <div style="display:inline-block;background:#3b82f6;color:white;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;margin-bottom:4px">PILLAR</div>
                            <div style="font-weight:600;color:#1e3a8a;font-size:14px"><?= htmlspecialchars($pillar['title']) ?></div>
                            <a href="/board/<?= htmlspecialchars($pillar['board_id']) ?>/<?= (int)$pillar['id'] ?>" target="_blank" style="font-size:11px;color:#2563eb">🔗 글 보기</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($clusters)): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;margin-bottom:12px">
                            <?php foreach ($clusters as $c): ?>
                                <div style="background:white;border:1px solid #e2e8f0;border-radius:4px;padding:8px 12px;font-size:12px">
                                    <span style="display:inline-block;background:#64748b;color:white;padding:1px 6px;border-radius:8px;font-size:9px;font-weight:700;margin-right:6px">CLUSTER</span>
                                    <span style="font-weight:500"><?= htmlspecialchars($c['title']) ?></span>
                                    <br><a href="/board/<?= htmlspecialchars($c['board_id']) ?>/<?= (int)$c['id'] ?>" target="_blank" style="font-size:10px;color:#2563eb">🔗 보기</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missingTopics)): ?>
                        <div style="background:#fef3c7;border:1px solid #fde047;border-radius:6px;padding:10px 14px;margin-bottom:12px">
                            <strong style="color:#854d0e;font-size:13px">💡 AI가 추천하는 빠진 주제</strong>
                            <ul style="margin:6px 0 0;padding-left:20px;font-size:12px;color:#78716c">
                                <?php foreach ($missingTopics as $mt): ?>
                                    <li><?= htmlspecialchars($mt) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:12px">
                        <?php if (!empty($missingTopics)): ?>
                            <form method="post" style="margin:0;display:flex;gap:6px;align-items:center" onsubmit="return confirm('빠진 주제 ' + <?= count($missingTopics) ?> + '개를 큐에 등록합니다. 진행할까요?')">
                                <input type="hidden" name="group_idx" value="<?= $gidx ?>">
                                <select name="queue_board_id" required style="padding:6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                    <option value="">-- 게시판 --</option>
                                    <?php foreach ($boards as $b): ?>
                                        <option value="<?= htmlspecialchars($b['board_id']) ?>" <?= ($pillar && $pillar['board_id'] === $b['board_id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="queue_style" style="padding:6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                    <option value="정보형">정보형</option>
                                    <option value="후기형">후기형</option>
                                    <option value="튜토리얼">튜토리얼</option>
                                    <option value="리스트형">리스트형</option>
                                </select>
                                <button type="submit" name="tb_queue_missing" value="1" class="btn btn-sm" style="background:#f59e0b;color:white">✨ 큐에 등록</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($orphanIds)): ?>
            <div style="margin-top:16px;padding:12px 16px;background:#f1f5f9;border-radius:8px;font-size:13px;color:#64748b">
                <strong>📝 미분류 글 <?= count($orphanIds) ?>개:</strong> 어느 그룹에도 속하지 않습니다. 독립적인 주제이거나 글 수가 적어 그룹이 안 만들어진 경우입니다.
            </div>
        <?php endif; ?>
        <?php endif; /* end mode branch */ ?>
    <?php endif; /* end analysis result */ ?>
</div>

<!-- ========== 탭 3: API 설정 ========== -->
<div class="tb-panel <?= $activeTab === 'api' ? 'active' : '' ?>" id="tb-panel-api">

    <form method="post">
        <input type="hidden" name="tb_save_api" value="1">

        <h3 style="margin:0 0 16px;font-size:16px">🤖 OpenAI 설정</h3>

        <div class="tb-api-card selected">
            <div class="tb-provider-label">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 18V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2z"/><path d="M21 12V6a2 2 0 0 0-2-2h-2"/></svg>
                OpenAI (GPT)
            </div>

            <div class="tb-form-row">
                <label>API 키</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="password" name="openai_api_key" id="openai_api_key" value="<?= htmlspecialchars($_tbConfig['openai_api_key']) ?>" placeholder="sk-or-v1-..." style="flex:1">
                    <button type="button" class="tb-test-btn" onclick="tbTestOpenAI()">🧪 테스트</button>
                    <span class="tb-test-result" id="openai_test_result"></span>
                </div>
            </div>

            <div class="tb-form-row">
                <label>모델</label>
                <select name="openai_model">
                    <?= nb_openrouter_options($_tbConfig['openai_model'] ?? '') ?>
                </select>
            </div>

            <small style="display:block;color:#64748b;font-size:12px">
                🔗 <a href="https://openrouter.ai/keys" target="_blank" style="color:#2563eb">OpenRouter API 키 발급받기</a>
            </small>
        </div>

        <h3 style="margin:24px 0 16px;font-size:16px">🖼 이미지 자동 삽입</h3>

        <div class="tb-api-card selected">
            <div class="tb-form-row">
                <label>이미지 사용</label>
                <label style="font-weight:normal;display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="image_enabled" value="1" <?= $_tbConfig['image_enabled'] === '1' ? 'checked' : '' ?>>
                    글에 이미지 자동 삽입 (H2 소제목마다 분산 배치)
                </label>
            </div>

            <div class="tb-form-row">
                <label>이미지 소스</label>
                <select name="image_source" onchange="tbToggleImageSource()">
                    <option value="unsplash" <?= $_tbConfig['image_source'] === 'unsplash' ? 'selected' : '' ?>>Unsplash (무료 스톡 사진, 추천)</option>
                    <option value="dalle" <?= $_tbConfig['image_source'] === 'dalle' ? 'selected' : '' ?>>DALL-E 3 (AI 생성, 유니크, $0.04/장)</option>
                </select>
            </div>

            <div class="tb-form-row" id="tb-unsplash-row" style="display:<?= $_tbConfig['image_source'] === 'unsplash' ? 'grid' : 'none' ?>">
                <label>Unsplash API 키</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="password" name="unsplash_api_key" id="unsplash_api_key" value="<?= htmlspecialchars($_tbConfig['unsplash_api_key']) ?>" placeholder="Unsplash Access Key" style="flex:1">
                    <button type="button" class="tb-test-btn" onclick="tbTestUnsplash()">🧪 테스트</button>
                    <span class="tb-test-result" id="unsplash_test_result"></span>
                </div>
                <small>🔗 <a href="https://unsplash.com/oauth/applications" target="_blank" style="color:#2563eb">Unsplash API 키 발급받기</a> (무료 월 5,000회)</small>
            </div>

            <div class="tb-form-row">
                <label>장수 (글당)</label>
                <select name="images_per_post">
                    <option value="auto" <?= $_tbConfig['images_per_post'] === 'auto' ? 'selected' : '' ?>>자동 (필러 3장, 클러스터 2장)</option>
                    <option value="1" <?= $_tbConfig['images_per_post'] === '1' ? 'selected' : '' ?>>1장</option>
                    <option value="2" <?= $_tbConfig['images_per_post'] === '2' ? 'selected' : '' ?>>2장</option>
                    <option value="3" <?= $_tbConfig['images_per_post'] === '3' ? 'selected' : '' ?>>3장</option>
                    <option value="4" <?= $_tbConfig['images_per_post'] === '4' ? 'selected' : '' ?>>4장</option>
                    <option value="5" <?= $_tbConfig['images_per_post'] === '5' ? 'selected' : '' ?>>5장</option>
                </select>
            </div>
        </div>

        <h3 style="margin:24px 0 16px;font-size:16px">🔗 광고 링크 자동 삽입 (스텔스 광고)</h3>
        <div class="tb-api-card selected" style="background:#fef3c7;border-color:#fde047">
            <div style="font-size:13px;color:#78716c;margin-bottom:12px;line-height:1.6">
                AI가 글을 생성할 때 본문에 <strong>자연스럽게 광고 링크를 끼워넣습니다.</strong><br>
                여러 개 등록하면 글마다 랜덤으로 선택되어 삽입됩니다.
            </div>

            <div class="tb-form-row">
                <label>글당 삽입 개수</label>
                <select name="promo_links_per_post">
                    <option value="0" <?= $_tbConfig['promo_links_per_post'] === '0' ? 'selected' : '' ?>>삽입 안 함</option>
                    <option value="0-1" <?= $_tbConfig['promo_links_per_post'] === '0-1' ? 'selected' : '' ?>>0~1개 랜덤 (가끔 삽입)</option>
                    <option value="0-2" <?= $_tbConfig['promo_links_per_post'] === '0-2' ? 'selected' : '' ?>>0~2개 랜덤 (추천, 자연스러움)</option>
                    <option value="0-3" <?= $_tbConfig['promo_links_per_post'] === '0-3' ? 'selected' : '' ?>>0~3개 랜덤</option>
                    <option value="1" <?= $_tbConfig['promo_links_per_post'] === '1' ? 'selected' : '' ?>>1개 고정 (항상)</option>
                    <option value="1-2" <?= $_tbConfig['promo_links_per_post'] === '1-2' ? 'selected' : '' ?>>1~2개 랜덤 (항상 삽입)</option>
                    <option value="2-3" <?= $_tbConfig['promo_links_per_post'] === '2-3' ? 'selected' : '' ?>>2~3개 랜덤</option>
                    <option value="3-5" <?= $_tbConfig['promo_links_per_post'] === '3-5' ? 'selected' : '' ?>>3~5개 랜덤</option>
                </select>
                <small style="color:#78716c">"0~2개"는 어떤 글은 0개, 어떤 글은 1~2개로 랜덤 삽입 → 자연스럽고 스팸으로 안 보임</small>
            </div>

            <div class="tb-form-row">
                <label>링크 목록</label>
                <div>
                    <div id="tb-promo-links-list">
                        <?php
                        $promoLinks = $_tbConfig['promo_links'] ?? [];
                        if (empty($promoLinks)) $promoLinks = [['anchor' => '', 'url' => '']];
                        foreach ($promoLinks as $idx => $link):
                        ?>
                            <div class="tb-promo-row" style="display:flex;gap:6px;margin-bottom:6px;align-items:center">
                                <input type="text" name="promo_anchor[]" value="<?= htmlspecialchars($link['anchor'] ?? '') ?>" placeholder="앵커텍스트 (예: 구글 상위노출)" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                                <span style="color:#94a3b8">:</span>
                                <input type="text" name="promo_url[]" value="<?= htmlspecialchars($link['url'] ?? '') ?>" placeholder="https://example.com/page" style="flex:1.5;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                                <button type="button" class="btn-delete" onclick="tbRemovePromoRow(this)" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:4px 8px">🗑</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm" onclick="tbAddPromoRow()" style="margin-top:4px">+ 링크 추가</button>
                </div>
                <small style="color:#78716c">형식: <code>앵커텍스트 : URL</code><br>예: <code>구글 상위노출 : https://mysite.com/seo</code> → 본문에 "<u>구글 상위노출</u>" 링크로 자연스럽게 삽입됨</small>
            </div>
        </div>

        <h3 style="margin:24px 0 16px;font-size:16px">⏱ 기본 발행 간격</h3>
        <div class="tb-form-row">
            <label>간격 (분)</label>
            <input type="number" name="interval_minutes" value="<?= (int)$_tbConfig['interval_minutes'] ?>" min="5" max="1440" style="max-width:140px">
            <small>새 프로젝트의 기본 간격. 프로젝트별로 변경 가능.</small>
        </div>

        <div style="margin-top:24px;text-align:right">
            <button type="submit" class="btn btn-primary">💾 설정 저장</button>
        </div>
    </form>

    <div style="margin-top:24px;padding:16px;background:#f8fafc;border-radius:8px">
        <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">💡 사용 팁</h4>
        <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
            <li>저장 전 반드시 테스트 버튼으로 키 유효성 확인</li>
            <li>gpt-4o-mini는 토픽 맵 1개 생성에 약 0.001달러 (매우 저렴)</li>
            <li>간격 발행은 방문자가 사이트 접속할 때 체크됨 (별도 크론 불필요)</li>
            <li>관리자 페이지에서도 자동 체크됨</li>
        </ul>
    </div>
</div>

<script>
function tbShowTab(name) {
    document.querySelectorAll('.tb-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tb-panel').forEach(p => p.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('tb-panel-' + name).classList.add('active');
}

function tbShowTabByName(name) {
    document.querySelectorAll('.tb-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tb-panel').forEach(p => p.classList.remove('active'));
    const idx = {'create':0, 'queue':1, 'analyze':2, 'api':3}[name];
    document.querySelectorAll('.tb-tab')[idx]?.classList.add('active');
    document.getElementById('tb-panel-' + name).classList.add('active');
    window.scrollTo({top:0, behavior:'smooth'});
}

function tbToggleAllBoards(checked) {
    document.querySelectorAll('.tb-board-cb').forEach(cb => cb.checked = checked);
}

// 광고 링크 행 추가
function tbAddPromoRow() {
    const list = document.getElementById('tb-promo-links-list');
    const row = document.createElement('div');
    row.className = 'tb-promo-row';
    row.style.cssText = 'display:flex;gap:6px;margin-bottom:6px;align-items:center';
    row.innerHTML = '<input type="text" name="promo_anchor[]" placeholder="앵커텍스트 (예: 구글 상위노출)" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">'
                  + '<span style="color:#94a3b8">:</span>'
                  + '<input type="text" name="promo_url[]" placeholder="https://example.com/page" style="flex:1.5;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">'
                  + '<button type="button" class="btn-delete" onclick="tbRemovePromoRow(this)" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:4px 8px">🗑</button>';
    list.appendChild(row);
}

function tbRemovePromoRow(btn) {
    const row = btn.closest('.tb-promo-row');
    const list = document.getElementById('tb-promo-links-list');
    if (list.querySelectorAll('.tb-promo-row').length > 1) {
        row.remove();
    } else {
        // 마지막 하나는 비우기만
        row.querySelectorAll('input').forEach(i => i.value = '');
    }
}

// AI 추천 클릭 → 폼에 자동 입력
function tbApplySuggestion(topic, clusterCount, style) {
    const topicInput = document.querySelector('input[name="topic"]');
    const clusterSelect = document.querySelector('select[name="cluster_count"]');
    const styleSelect = document.querySelector('select[name="style"]');

    if (topicInput) topicInput.value = topic;

    if (clusterSelect) {
        // 가장 가까운 옵션 선택
        const opts = [5, 10, 15, 20, 30];
        let nearest = opts.reduce((a, b) => Math.abs(b - clusterCount) < Math.abs(a - clusterCount) ? b : a);
        clusterSelect.value = nearest;
    }

    if (styleSelect) {
        const found = Array.from(styleSelect.options).find(o => o.value === style);
        if (found) styleSelect.value = style;
    }

    // 주제 입력창으로 스크롤 + 포커스
    if (topicInput) {
        topicInput.scrollIntoView({behavior: 'smooth', block: 'center'});
        topicInput.focus();
        topicInput.style.background = '#fef3c7';
        setTimeout(() => { topicInput.style.background = ''; }, 1500);
    }
}

// 이미지 소스 변경 시 Unsplash 키 필드 토글
function tbToggleImageSource() {
    const src = document.querySelector('select[name="image_source"]').value;
    document.getElementById('tb-unsplash-row').style.display = (src === 'unsplash') ? 'grid' : 'none';
}

// Unsplash 테스트 (브라우저 직접 호출)
function tbTestUnsplash() {
    const key = document.getElementById('unsplash_api_key').value.trim();
    const result = document.getElementById('unsplash_test_result');
    if (!key) { result.textContent = '키를 입력하세요'; result.style.color = '#dc2626'; return; }
    result.textContent = '⏳ 테스트 중...'; result.style.color = '#64748b';

    fetch('https://api.unsplash.com/photos/random?client_id=' + encodeURIComponent(key))
    .then(r => {
        if (r.ok) { result.textContent = '✅ 연결 성공'; result.style.color = '#16a34a'; }
        else { result.textContent = '❌ 키가 유효하지 않음 (' + r.status + ')'; result.style.color = '#dc2626'; }
    }).catch(e => {
        result.textContent = '❌ 연결 실패'; result.style.color = '#dc2626';
    });
}

// OpenAI 테스트 (브라우저 직접 호출)
function tbTestOpenAI() {
    const key = document.getElementById('openai_api_key').value.trim();
    const result = document.getElementById('openai_test_result');
    if (!key) { result.textContent = '키를 입력하세요'; result.style.color = '#dc2626'; return; }
    result.textContent = '⏳ 테스트 중...'; result.style.color = '#64748b';

    fetch('https://openrouter.ai/api/v1/models', {
        headers: { 'Authorization': 'Bearer ' + key }
    }).then(r => {
        if (r.ok) { result.textContent = '✅ 연결 성공'; result.style.color = '#16a34a'; }
        else { result.textContent = '❌ 키가 유효하지 않음 (' + r.status + ')'; result.style.color = '#dc2626'; }
    }).catch(e => {
        result.textContent = '❌ 연결 실패'; result.style.color = '#dc2626';
    });
}
</script>
