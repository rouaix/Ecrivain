<?php

class StatsController extends Controller
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function index()
    {
        $user      = $this->currentUser();
        $uid       = $user['id'];
        
        // Get project_id from URL parameter or GET parameter
        $projectId = (int) ($this->f3->get('PARAMS.pid') ?? ($_GET['project_id'] ?? 0));
        
        // Debug: log to file to see what's happening
        file_put_contents('/tmp/stats_debug.log', date('Y-m-d H:i:s') . " - projectId: " . $projectId . "\n", FILE_APPEND);

        // Project scope: restrict to one project or all user projects
        $projectModel = new Project();
        $projects = $projectModel->find(['user_id=?', $uid], ['order' => 'title ASC']) ?: [];

        $scopeClause = $projectId ? 'AND ws.project_id = ?' : '';
        $scopeParam  = $projectId ?: null;

        // --- KPI: total words across all chapters (current state) ---
        $totalSql = 'SELECT COALESCE(SUM(c.word_count), 0) AS total
                     FROM chapters c
                     JOIN projects p ON p.id = c.project_id
                     WHERE p.user_id = ?' . ($projectId ? ' AND c.project_id = ?' : '');
        $totalParams = $projectId ? [$uid, $projectId] : [$uid];
        $totalWords  = (int) ($this->db->exec($totalSql, $totalParams)[0]['total'] ?? 0);

        // --- Daily snapshots for last 31 days ---
        $snapSql = 'SELECT ws.stat_date, ws.chapter_id, ws.word_count
                    FROM writing_stats ws
                    WHERE ws.user_id = ?
                    ' . ($projectId ? 'AND ws.project_id = ?' : '') . '
                    AND ws.stat_date >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)
                    ORDER BY ws.stat_date ASC';
        $snapParams = $projectId ? [$uid, $projectId] : [$uid];
        $snapRows   = $this->db->exec($snapSql, $snapParams) ?: [];

        // Build {date => {chapter_id => word_count}} map
        $snapMap = [];
        foreach ($snapRows as $row) {
            $snapMap[$row['stat_date']][$row['chapter_id']] = (int) $row['word_count'];
        }

        // Build ordered list of dates (last 30 days)
        $dates      = [];
        $dailyWords = []; // words added per day
        $today      = new DateTime();
        for ($i = 29; $i >= 0; $i--) {
            $d = (clone $today)->modify("-{$i} days")->format('Y-m-d');
            $dates[] = $d;
        }

        $prevTotals = []; // chapter_id => word_count from previous day
        // Seed with day -31 if available
        $seedDate = (clone $today)->modify('-31 days')->format('Y-m-d');
        if (isset($snapMap[$seedDate])) {
            $prevTotals = $snapMap[$seedDate];
        }

        foreach ($dates as $date) {
            $daySnap = $snapMap[$date] ?? [];
            $added   = 0;
            foreach ($daySnap as $cid => $wc) {
                $prev   = $prevTotals[$cid] ?? 0;
                $delta  = $wc - $prev;
                if ($delta > 0) $added += $delta;
            }
            $dailyWords[$date] = $added;
            // Merge snapshots: update prevTotals for chapters seen today
            foreach ($daySnap as $cid => $wc) {
                $prevTotals[$cid] = $wc;
            }
        }

        // --- KPIs from daily deltas ---
        $todayKey  = $today->format('Y-m-d');
        $wordsToday = $dailyWords[$todayKey] ?? 0;

        $wordsWeek = 0;
        for ($i = 6; $i >= 0; $i--) {
            $d = (clone $today)->modify("-{$i} days")->format('Y-m-d');
            $wordsWeek += $dailyWords[$d] ?? 0;
        }

        $wordsMonth = array_sum($dailyWords);

        // --- Streak ---
        $streak = 0;
        for ($i = 0; $i <= 29; $i++) {
            $d = (clone $today)->modify("-{$i} days")->format('Y-m-d');
            if (($dailyWords[$d] ?? 0) > 0) {
                $streak++;
            } else {
                break;
            }
        }

        // --- Chapter word distribution ---
        $distSql = 'SELECT c.title, c.word_count
                    FROM chapters c
                    JOIN projects p ON p.id = c.project_id
                    WHERE p.user_id = ? AND c.parent_id IS NULL AND c.word_count > 0'
                   . ($projectId ? ' AND c.project_id = ?' : '') .
                   ' ORDER BY c.word_count DESC LIMIT 15';
        $distParams = $projectId ? [$uid, $projectId] : [$uid];
        $topChapters = $this->db->exec($distSql, $distParams) ?: [];

        // --- Chart data as JSON ---
        $chartLabels = array_map(fn($d) => (new DateTime($d))->format('d/m'), $dates);
        $chartData   = array_values($dailyWords);

        $this->render('stats/index.html', [
            'title'        => 'Statistiques d\'écriture',
            'projects'     => $projects,
            'projectId'    => $projectId,
            'totalWords'   => $totalWords,
            'wordsToday'   => $wordsToday,
            'wordsWeek'    => $wordsWeek,
            'wordsMonth'   => $wordsMonth,
            'streak'       => $streak,
            'topChapters'  => $topChapters,
            'chartLabels'  => json_encode($chartLabels),
            'chartData'    => json_encode($chartData),
        ]);
    }
}
