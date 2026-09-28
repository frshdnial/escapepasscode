<?php
declare(strict_types=1);

namespace App;

use Closure;
use PDO;
use Throwable;

/**
 * All game rules live here: the server owns the answers, the clock and the score,
 * so the leaderboard cannot be faked from the browser.
 *
 * Scoring per solved case:
 *   (1000 + 10 x whole seconds left - 100 x wrong tries, minimum 100) x (1 + 0.2 x case index)
 */
final class GameService
{
    private const GRACE_MS = 2500; // allowance for network latency on the 60s timer
    private Closure $clock;

    public function __construct(private PDO $db, private array $config, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => (int) round(microtime(true) * 1000);
    }

    public function publicConfig(): array
    {
        return [
            'totalCases'       => count($this->config['cases']),
            'timeLimitSeconds' => $this->config['timeLimitSeconds'],
            'maxAttempts'      => $this->config['maxAttempts'],
        ];
    }

    public function createRun(string $rawName): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $rawName));
        if (!preg_match('/^[\p{L}\p{N} _.\-]{2,16}$/u', $name)) {
            throw new ApiException(422, 'Name must be 2 to 16 characters: letters, numbers, spaces, _ . or -');
        }

        $id = bin2hex(random_bytes(16));
        $this->db->prepare('INSERT INTO runs (id, player_name, created_at) VALUES (?, ?, ?)')
            ->execute([$id, $name, ($this->clock)()]);

        return ['runId' => $id, 'player' => $name] + $this->publicConfig();
    }

    /** Opens the current case and starts its timer. Safe to call twice: the timer never restarts. */
    public function startCase(string $runId): array
    {
        return $this->transaction(function () use ($runId): array {
            $run = $this->loadRun($runId, true);
            $this->assertActive($run);

            $now = ($this->clock)();
            if ($run['case_started_at'] === null) {
                $run['case_started_at'] = $now;
                $this->save($run);
            }

            $limitMs = $this->limitMs();
            $case = $this->config['cases'][(int) $run['current_case']];

            return [
                'caseIndex'    => (int) $run['current_case'],
                'case'         => [
                    'title' => $case['title'],
                    'rank'  => $case['rank'],
                    'note'  => $case['note'],
                    'clues' => $case['clues'],
                ],
                'remainingMs'  => max(0, $limitMs - ($now - (int) $run['case_started_at'])),
                'attemptsLeft' => $this->config['maxAttempts'] - (int) $run['wrong_attempts'],
                'score'        => (int) $run['score'],
            ];
        });
    }

    public function guess(string $runId, string $code): array
    {
        $code = trim($code);
        if (!preg_match('/^\d{3}$/', $code)) {
            throw new ApiException(422, 'Combination must be exactly 3 digits.');
        }

        return $this->transaction(function () use ($runId, $code): array {
            $run = $this->loadRun($runId, true);
            $this->assertActive($run);
            if ($run['case_started_at'] === null) {
                throw new ApiException(409, 'Open the case before entering a combination.');
            }

            $limitMs = $this->limitMs();
            $elapsed = ($this->clock)() - (int) $run['case_started_at'];

            if ($elapsed > $limitMs + self::GRACE_MS) {
                $run['total_time_ms'] += $limitMs;
                return ['result' => 'timeout', 'final' => $this->finalize($run, 'failed')];
            }

            $index = (int) $run['current_case'];
            $total = count($this->config['cases']);

            if (hash_equals($this->config['cases'][$index]['code'], $code)) {
                $secondsLeft = max(0, intdiv($limitMs - $elapsed, 1000));
                $base        = max(100, 1000 + 10 * $secondsLeft - 100 * (int) $run['wrong_attempts']);
                $caseScore   = (int) round($base * (1 + 0.2 * $index));

                $run['score']          += $caseScore;
                $run['cases_solved']   += 1;
                $run['total_time_ms']  += min($elapsed, $limitMs);
                $run['current_case']    = $index + 1;
                $run['wrong_attempts']  = 0;
                $run['case_started_at'] = null;

                $out = [
                    'result'      => 'correct',
                    'caseScore'   => $caseScore,
                    'score'       => (int) $run['score'],
                    'casesSolved' => (int) $run['cases_solved'],
                    'finished'    => $run['cases_solved'] >= $total,
                ];
                if ($out['finished']) {
                    $out['final'] = $this->finalize($run, 'completed');
                } else {
                    $this->save($run);
                }
                return $out;
            }

            $run['wrong_attempts'] += 1;
            if ($run['wrong_attempts'] >= $this->config['maxAttempts']) {
                $run['total_time_ms'] += min($elapsed, $limitMs);
                return ['result' => 'locked', 'final' => $this->finalize($run, 'failed')];
            }

            $this->save($run);
            return ['result' => 'wrong', 'attemptsLeft' => $this->config['maxAttempts'] - (int) $run['wrong_attempts']];
        });
    }

    /** Called by the client when its countdown hits zero. The server double-checks its own clock. */
    public function timeout(string $runId): array
    {
        return $this->transaction(function () use ($runId): array {
            $run = $this->loadRun($runId, true);
            $this->assertActive($run);
            if ($run['case_started_at'] === null) {
                throw new ApiException(409, 'This case has not been opened.');
            }

            $limitMs = $this->limitMs();
            if (($this->clock)() - (int) $run['case_started_at'] < $limitMs - self::GRACE_MS) {
                throw new ApiException(409, 'Time is not up yet.');
            }

            $run['total_time_ms'] += $limitMs;
            return ['result' => 'timeout', 'final' => $this->finalize($run, 'failed')];
        });
    }

    /** Ranked by score, then faster total time, then whoever finished first. */
    public function leaderboard(int $limit, ?string $runId = null): array
    {
        $st = $this->db->prepare(
            "SELECT id, player_name, score, total_time_ms, cases_solved, status, finished_at
               FROM runs
              WHERE status != 'in_progress' AND cases_solved > 0
              ORDER BY score DESC, total_time_ms ASC, finished_at ASC
              LIMIT ?"
        );
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();

        $entries = [];
        $you = null;
        foreach ($st->fetchAll() as $i => $row) {
            $isYou = $runId !== null && hash_equals($row['id'], $runId);
            $entries[] = $entry = $this->entry($row, $i + 1, $isYou);
            if ($isYou) {
                $you = $entry;
            }
        }

        // The requesting player may sit below the visible cut-off.
        if ($runId !== null && $you === null) {
            try {
                $run = $this->loadRun($runId);
                if ($run['status'] !== 'in_progress' && (int) $run['cases_solved'] > 0) {
                    $you = $this->entry($run, $this->rankOf($run), true);
                }
            } catch (ApiException) {
                // Unknown id: just no highlight.
            }
        }

        return ['entries' => $entries, 'you' => $you];
    }

    // ---------------------------------------------------------------- internals

    private function limitMs(): int
    {
        return (int) $this->config['timeLimitSeconds'] * 1000;
    }

    private function finalize(array $run, string $status): array
    {
        $run['status']          = $status;
        $run['case_started_at'] = null;
        $run['finished_at']     = ($this->clock)();
        $this->save($run);

        return [
            'status'      => $status,
            'score'       => (int) $run['score'],
            'timeMs'      => (int) $run['total_time_ms'],
            'casesSolved' => (int) $run['cases_solved'],
            'totalCases'  => count($this->config['cases']),
            'rank'        => $this->rankOf($run),
        ];
    }

    private function rankOf(array $run): ?int
    {
        if ((int) $run['cases_solved'] < 1) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT COUNT(*) + 1 FROM runs
              WHERE status != 'in_progress' AND cases_solved > 0
                AND (score > ?
                     OR (score = ? AND total_time_ms < ?)
                     OR (score = ? AND total_time_ms = ? AND finished_at < ?))"
        );
        $s = (int) $run['score'];
        $t = (int) $run['total_time_ms'];
        $st->execute([$s, $s, $t, $s, $t, (int) $run['finished_at']]);

        return (int) $st->fetchColumn();
    }

    private function entry(array $row, ?int $rank, bool $isYou): array
    {
        return [
            'rank'        => $rank,
            'name'        => $row['player_name'],
            'score'       => (int) $row['score'],
            'timeMs'      => (int) $row['total_time_ms'],
            'casesSolved' => (int) $row['cases_solved'],
            'completed'   => $row['status'] === 'completed',
            'finishedAt'  => gmdate('c', intdiv((int) $row['finished_at'], 1000)),
            'you'         => $isYou,
        ];
    }

    private function loadRun(string $id, bool $lock = false): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new ApiException(404, 'Run not found.');
        }
        $st = $this->db->prepare('SELECT * FROM runs WHERE id = ?' . ($lock && !$this->isSqlite() ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        $run = $st->fetch();
        if (!$run) {
            throw new ApiException(404, 'Run not found.');
        }
        return $run;
    }

    private function assertActive(array $run): void
    {
        if ($run['status'] !== 'in_progress') {
            throw new ApiException(409, 'This investigation is already over. Start a new one.');
        }
    }

    private function save(array $r): void
    {
        $this->db->prepare(
            'UPDATE runs SET status = ?, current_case = ?, case_started_at = ?, wrong_attempts = ?,
                             cases_solved = ?, score = ?, total_time_ms = ?, finished_at = ?
              WHERE id = ?'
        )->execute([
            $r['status'], $r['current_case'], $r['case_started_at'], $r['wrong_attempts'],
            $r['cases_solved'], $r['score'], $r['total_time_ms'], $r['finished_at'], $r['id'],
        ]);
    }

    private function isSqlite(): bool
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function transaction(callable $fn): array
    {
        $this->db->exec($this->isSqlite() ? 'BEGIN IMMEDIATE' : 'START TRANSACTION');
        try {
            $result = $fn();
            $this->db->exec('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
}
