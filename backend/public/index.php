<?php
declare(strict_types=1);

use App\ApiController;
use App\ApiException;
use App\Database;
use App\GameService;
use App\Middleware\CorsMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// Load backend/.env if present (real environment variables win).
if (is_file($root . '/.env')) {
    foreach (parse_ini_file($root . '/.env', false, INI_SCANNER_RAW) ?: [] as $key => $value) {
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

$debug  = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$origin = getenv('CORS_ORIGIN') ?: '*'; // set to your site's origin in production

$api = new ApiController(fn (): GameService => new GameService(Database::fromEnv($root), require $root . '/config/game.php'));

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errors = $app->addErrorMiddleware($debug, false, false);
$errors->setDefaultErrorHandler(function (ServerRequestInterface $request, Throwable $e, bool $showDetails) use ($app): ResponseInterface {
    $status = match (true) {
        $e instanceof ApiException  => $e->status,
        $e instanceof HttpException => $e->getCode(),
        $e instanceof PDOException  => 503,
        default                     => 500,
    };
    if ($status >= 500) {
        error_log((string) $e);
    }
    $message = match (true) {
        $e instanceof PDOException => 'Database unavailable. Check backend/.env and that database/schema.sql has been run.',
        $status === 500 && !$showDetails => 'Unexpected server error.',
        default => $e->getMessage() ?: 'Request failed.',
    };

    $response = $app->getResponseFactory()->createResponse($status);
    $response->getBody()->write(json_encode(['error' => $message], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->group('/api', function (RouteCollectorProxy $g) use ($api): void {
    $g->get('/config', [$api, 'config']);
    $g->get('/leaderboard', [$api, 'leaderboard']);
    $g->post('/runs', [$api, 'createRun']);
    $g->post('/runs/{id}/start', [$api, 'startCase']);
    $g->post('/runs/{id}/guess', [$api, 'guess']);
    $g->post('/runs/{id}/timeout', [$api, 'timeout']);
});

// Added last = runs first, so it also wraps error responses.
$app->add(new CorsMiddleware($origin, $app->getResponseFactory()));

$app->run();
