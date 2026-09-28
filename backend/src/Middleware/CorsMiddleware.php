<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Outermost middleware, so CORS headers are also present on error responses. */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private string $origin, private ResponseFactoryInterface $responses)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $request->getMethod() === 'OPTIONS'
            ? $this->responses->createResponse(204)
            : $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
