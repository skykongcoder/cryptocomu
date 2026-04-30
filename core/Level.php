<?php
/**
 * NuriBoard - 회원 등급 관리
 */
class Level
{
    private static array $cache = [];

    private static function table(): string
    {
        return DB::getPrefix() . 'levels';
    }

    // 기본 레벨 10단계 설치 (최초 1회)
    public static function initDefaults(): void
    {
        $table = self::table();
        if (DB::count($table) > 0) return;

        $defaults = [
            [1,  '방문자',   '👋', 0,     0,   0,   1, 1, 1, '처음 방문한 회원'],
            [2,  '입문러',   '📚', 30,    3,   5,   1, 1, 1, '첫 발을 내딛은 회원'],
            [3,  '활동러',   '⚡', 100,   10,  20,  1, 1, 1, '활발하게 활동하는 회원'],
            [4,  '실행러',   '🔧', 300,   30,  60,  1, 1, 1, '직접 실행하는 회원'],
            [5,  '성장러',   '📈', 700,   70,  150, 1, 1, 1, '꾸준히 성장하는 회원'],
            [6,  '실전러',   '⚔️', 1500,  150, 300, 1, 1, 1, '실전 경험이 풍부한 회원'],
            [7,  '최적화러', '🎯', 3000,  300, 600, 1, 1, 1, '효율을 추구하는 회원'],
            [8,  '전략가',   '♟️', 6000,  600, 1200,1, 1, 1, '전략적 사고를 가진 회원'],
            [9,  '마스터',   '💎', 12000, 1200,2400,1, 1, 1, '최고 수준의 실력자'],
            [10, '레전드',   '🏆', 25000, 2500,5000,1, 1, 1, '살아있는 전설'],
        ];

        foreach ($defaults as $d) {
            DB::insert($table, [
                'level'        => $d[0],
                'name'         => $d[1],
                'icon'         => $d[2],
                'icon_type'    => 'emoji',
                'min_point'    => $d[3],
                'min_posts'    => $d[4],
                'min_comments' => $d[5],
                'can_write'    => $d[6],
                'can_upload'   => $d[7],
                'can_comment'  => $d[8],
                'description'  => $d[9],
            ]);
        }
    }

    // 전체 레벨 목록 (캐싱)
    public static function getAll(): array
    {
        if (!empty(self::$cache)) return self::$cache;
        $rows = DB::fetchAll("SELECT * FROM " . self::table() . " ORDER BY level ASC");
        foreach ($rows as $r) {
            self::$cache[(int)$r['level']] = $r;
        }
        return self::$cache;
    }

    // 특정 레벨 정보
    public static function find(int $level): ?array
    {
        $all = self::getAll();
        return $all[$level] ?? null;
    }

    // 레벨 정보 수정
    public static function update(int $level, array $data): void
    {
        DB::update(self::table(), $data, 'level = ?', [$level]);
        self::$cache = [];
    }

    // 새 레벨 추가 (현재 최고 레벨 + 1)
    public static function add(array $data = []): int
    {
        $all     = self::getAll();
        $maxLv   = empty($all) ? 0 : max(array_keys($all));
        $newLv   = $maxLv + 1;
        $prevLv  = self::find($maxLv);

        DB::insert(self::table(), [
            'level'        => $newLv,
            'name'         => $data['name']         ?? 'Lv.' . $newLv,
            'icon'         => $data['icon']          ?? '🌟',
            'icon_type'    => $data['icon_type']     ?? 'emoji',
            'min_point'    => $data['min_point']     ?? (($prevLv['min_point'] ?? 0) * 2 ?: $newLv * 1000),
            'min_posts'    => $data['min_posts']     ?? (($prevLv['min_posts'] ?? 0) * 2 ?: $newLv * 100),
            'min_comments' => $data['min_comments']  ?? (($prevLv['min_comments'] ?? 0) * 2 ?: $newLv * 200),
            'can_write'    => $data['can_write']     ?? 1,
            'can_upload'   => $data['can_upload']    ?? 1,
            'can_comment'  => $data['can_comment']   ?? 1,
            'description'  => $data['description']  ?? '',
        ]);
        self::$cache = [];
        return $newLv;
    }

    // 레벨 삭제 (Lv.1은 삭제 불가, 해당 레벨 회원은 한 단계 아래로)
    public static function delete(int $level): bool
    {
        if ($level <= 1) return false;
        $table = self::table();
        // 해당 레벨 회원을 level-1로 이동
        DB::update(
            DB::getPrefix() . 'members',
            ['level' => $level - 1],
            'level = ?',
            [$level]
        );
        $pdo  = DB::getInstance();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE level = ?");
        $stmt->execute([$level]);
        self::$cache = [];
        return true;
    }

    // 현재 최고 레벨 번호
    public static function maxLevel(): int
    {
        $all = self::getAll();
        return empty($all) ? 10 : max(array_keys($all));
    }

    // 아이콘 HTML 반환
    public static function getIcon(int $level, string $title = ''): string
    {
        $lvl = self::find($level);
        $t   = $title ?: ('Lv.' . $level . ($lvl ? ' ' . $lvl['name'] : ''));

        // 이미지가 설정되어 있으면 이미지 표시
        if ($lvl && $lvl['icon_type'] === 'image' && !empty($lvl['icon'])) {
            $src = nb_url($lvl['icon']);
            return '<img class="level-icon" src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($t, ENT_QUOTES) . '" title="' . htmlspecialchars($t, ENT_QUOTES) . '" style="width:20px;height:20px;vertical-align:middle;border-radius:2px;object-fit:contain">';
        }

        // 이모지가 설정되어 있으면 이모지 표시
        if ($lvl && $lvl['icon_type'] === 'emoji' && !empty($lvl['icon'])) {
            return '<span class="level-icon" title="' . htmlspecialchars($t, ENT_QUOTES) . '">' . $lvl['icon'] . '</span>';
        }

        // 기본: Lv.숫자 텍스트
        return '<span class="level-badge" title="' . htmlspecialchars($t, ENT_QUOTES) . '">Lv.' . $level . '</span>';
    }

    // ─── 자동 등업 ────────────────────────────────────────────

    /**
     * 회원의 현재 활동량을 바탕으로 최고 달성 가능 레벨을 계산해
     * 현재 레벨보다 높으면 자동 등업하고 true 반환
     */
    public static function checkAndUpgrade(int $memberId): bool
    {
        $member = DB::fetch(
            "SELECT id, level, point FROM " . DB::getPrefix() . "members WHERE id = ?",
            [$memberId]
        );
        if (!$member) return false;

        $curLevel   = (int)$member['level'];
        $totalPoint = (int)$member['point'];

        // 게시글/댓글 수 집계
        $postCount    = (int)(DB::fetch(
            "SELECT COUNT(*) AS cnt FROM " . DB::getPrefix() . "posts WHERE member_id = ? AND is_hidden = 0",
            [$memberId]
        )['cnt'] ?? 0);
        $commentCount = (int)(DB::fetch(
            "SELECT COUNT(*) AS cnt FROM " . DB::getPrefix() . "comments WHERE member_id = ? AND is_hidden = 0",
            [$memberId]
        )['cnt'] ?? 0);

        $levels     = self::getAll();
        $targetLevel = $curLevel;

        foreach ($levels as $lv) {
            $lNum = (int)$lv['level'];
            if ($lNum <= $curLevel) continue; // 현재 이하는 건너뜀

            // 조건: 포인트 OR 글 수 OR 댓글 수 중 하나라도 달성하면 등업
            // (각 조건값이 0이면 해당 조건은 무시)
            $byPoint   = (int)$lv['min_point']    === 0 || $totalPoint    >= (int)$lv['min_point'];
            $byPost    = (int)$lv['min_posts']    === 0 || $postCount     >= (int)$lv['min_posts'];
            $byComment = (int)$lv['min_comments'] === 0 || $commentCount  >= (int)$lv['min_comments'];

            // 세 조건 중 어느 하나라도 전부 충족하는 경로가 있으면 등업
            // 실제 조건: (포인트 달성) OR (글 수 달성) OR (댓글 수 달성)
            $pointOk   = $totalPoint    >= (int)$lv['min_point']    || (int)$lv['min_point']    === 0;
            $postOk    = $postCount     >= (int)$lv['min_posts']    || (int)$lv['min_posts']    === 0;
            $commentOk = $commentCount  >= (int)$lv['min_comments'] || (int)$lv['min_comments'] === 0;

            if ($pointOk && $postOk && $commentOk) {
                $targetLevel = $lNum; // 이 레벨까지 충족
            } else {
                break; // 조건 불충족이면 더 높은 레벨도 불가
            }
        }

        if ($targetLevel > $curLevel) {
            DB::update(
                DB::getPrefix() . 'members',
                ['level' => $targetLevel],
                'id = ?',
                [$memberId]
            );
            // 세션도 갱신
            if (isset($_SESSION['member']['id']) && $_SESSION['member']['id'] == $memberId) {
                $_SESSION['member']['level'] = $targetLevel;
            }
            return true;
        }
        return false;
    }

    // 권한 체크
    public static function canWrite(int $level): bool
    {
        $lvl = self::find($level);
        return $lvl ? (bool)$lvl['can_write'] : true;
    }

    public static function canUpload(int $level): bool
    {
        $lvl = self::find($level);
        return $lvl ? (bool)$lvl['can_upload'] : true;
    }

    public static function canComment(int $level): bool
    {
        $lvl = self::find($level);
        return $lvl ? (bool)$lvl['can_comment'] : true;
    }

    // 다음 레벨까지의 진행도 (0.0~1.0)
    public static function progressToNext(int $memberId): array
    {
        $member = DB::fetch(
            "SELECT level, point FROM " . DB::getPrefix() . "members WHERE id = ?",
            [$memberId]
        );
        if (!$member) return ['percent' => 0, 'next' => null, 'current' => null];

        $curLv  = (int)$member['level'];
        $point  = (int)$member['point'];
        $curLvl = self::find($curLv);
        $nextLvl = self::find($curLv + 1);

        if (!$nextLvl) {
            return ['percent' => 100, 'next' => null, 'current' => $curLvl, 'point' => $point, 'need' => 0];
        }

        $curMin  = (int)($curLvl['min_point'] ?? 0);
        $nextMin = (int)$nextLvl['min_point'];
        $range   = max(1, $nextMin - $curMin);
        $earned  = max(0, $point - $curMin);
        $percent = min(100, (int)($earned / $range * 100));

        return [
            'percent'  => $percent,
            'next'     => $nextLvl,
            'current'  => $curLvl,
            'point'    => $point,
            'need'     => max(0, $nextMin - $point),
        ];
    }

    private static function defaultEmoji(int $level): string
    {
        $map = [1=>'🌱',2=>'🌿',3=>'🍀',4=>'🌸',5=>'⭐',6=>'🌳',7=>'💎',8=>'👑',9=>'🔥',10=>'🏆'];
        return $map[$level] ?? '🌿';
    }
}
