<?php
declare(strict_types=1);

namespace App;

use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ApiController
{
    private ?GameService $game = null;

    /** @param Closure(): GameService $factory Built on first use, so DB errors reach the JSON error handler. */
    public function __construct(private Closure $factory)
    {
    }

    private function game(): GameService
    {
        return $this->game ??= ($this->factory)();
    }

    public function config(Request $req, Response $res): Response
    {
        return $this->json($res, $this->game()->publicConfig());
    }

    public function leaderboard(Request $req, Response $res): Response
    {
        $q     = $req->getQueryParams();
        $limit = max(1, min(50, (int) ($q['limit'] ?? 10)));
        $run   = isset($q['run']) && is_string($q['run']) ? $q['run'] : null;

        return $this->json($res, $this->game()->leaderboard($limit, $run));
    }

    public function createRun(Request $req, Response $res): Response
    {
        return $this->json($res, $this->game()->createRun((string) ($this->body($req)['name'] ?? '')), 201);
    }

    public function startCase(Request $req, Response $res, array $args): Response
    {
        return $this->json($res, $this->game()->startCase($args['id']));
    }

    public function guess(Request $req, Response $res, array $args): Response
    {
        return $this->json($res, $this->game()->guess($args['id'], (string) ($this->body($req)['code'] ?? '')));
    }

    public function timeout(Request $req, Response $res, array $args): Response
    {
        return $this->json($res, $this->game()->timeout($args['id']));
    }

    private function body(Request $req): array
    {
        $body = $req->getParsedBody();
        return is_array($body) ? $body : [];
    }

    private function json(Response $res, array $data, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $res->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }
}
