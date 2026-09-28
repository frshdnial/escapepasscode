<?php
declare(strict_types=1);

// Plain-PHP checks for the game rules. Run: composer test
require __DIR__ . '/../vendor/autoload.php';

use App\ApiException;
use App\Database;
use App\GameService;

$config = require __DIR__ . '/../config/game.php';
$now    = 1_000_000;
$game   = new GameService(Database::sqlite(':memory:'), $config, function () use (&$now): int { return $now; });
$fails  = 0;

function check(bool $ok, string $label): void
{
    global $fails;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . PHP_EOL;
    $fails += $ok ? 0 : 1;
}

function throwsStatus(callable $fn, int $status): bool
{
    try { $fn(); } catch (ApiException $e) { return $e->status === $status; }
    return false;
}

// Perfect run: every case solved 10s after opening -> 50s left each.
$run = $game->createRun('  Ada   Lovelace ');
check($run['player'] === 'Ada Lovelace', 'name is trimmed and whitespace collapsed');
$last = [];
foreach ($config['cases'] as $i => $case) {
    $opened = $game->startCase($run['runId']);
    check(!isset($opened['case']['code']) && $opened['caseIndex'] === $i, "case $i served without its answer");
    $now += 10_000;
    $last = $game->guess($run['runId'], $case['code']);
}
check($last['finished'] === true && $last['final']['status'] === 'completed', 'run completes after 5 cases');
check($last['final']['score'] === 10500, 'perfect-pace score is 10500 (got ' . $last['final']['score'] . ')');
check($last['final']['timeMs'] === 50_000, 'total time is 50s');
check($last['final']['rank'] === 1, 'first finisher ranks #1');

// Lock-out after 5 wrong tries.
$now += 1000;
$b = $game->createRun('Bob');
$game->startCase($b['runId']);
$seen = [];
for ($i = 0; $i < 5; $i++) { $seen[] = $game->guess($b['runId'], '000'); }
check($seen[0]['result'] === 'wrong' && $seen[0]['attemptsLeft'] === 4, 'first wrong try leaves 4 attempts');
check($seen[4]['result'] === 'locked' && $seen[4]['final']['rank'] === null, '5th wrong try locks the vault (no score, no rank)');
check(throwsStatus(fn () => $game->guess($b['runId'], '471'), 409), 'finished run rejects further guesses');

// Timeout: too early is refused, on time fails the run but keeps earlier score.
$c = $game->createRun('Cy');
$game->startCase($c['runId']);
$game->guess($c['runId'], $config['cases'][0]['code']);
$game->startCase($c['runId']);
check(throwsStatus(fn () => $game->timeout($c['runId']), 409), 'timeout before 60s is refused');
$now += 61_000;
$t = $game->timeout($c['runId']);
check($t['result'] === 'timeout' && $t['final']['casesSolved'] === 1 && $t['final']['rank'] === 2, 'timeout ends run, keeps 1 solved case, ranks #2');

// Input validation.
check(throwsStatus(fn () => $game->createRun('x'), 422), 'too-short name rejected');
check(throwsStatus(fn () => $game->createRun('<script>'), 422), 'markup characters rejected in names');
$d = $game->createRun('Dee');
check(throwsStatus(fn () => $game->guess($d['runId'], '471'), 409), 'guess before opening the case is refused');
check(throwsStatus(fn () => $game->guess($d['runId'], '12'), 422), 'non 3-digit code rejected');
check(throwsStatus(fn () => $game->startCase(str_repeat('a', 32)), 404), 'unknown run id is a 404');

// Leaderboard.
$board = $game->leaderboard(10, $run['runId']);
check(count($board['entries']) === 2 && $board['entries'][0]['name'] === 'Ada Lovelace', 'leaderboard lists 2 scored runs, Ada first');
check($board['you']['rank'] === 1 && $board['entries'][0]['you'] === true, 'requesting player is flagged');

echo PHP_EOL . ($fails === 0 ? 'All checks passed.' : "$fails check(s) failed.") . PHP_EOL;
exit($fails === 0 ? 0 : 1);
