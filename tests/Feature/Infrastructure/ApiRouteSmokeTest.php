<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteSmokeTest extends TestCase
{
    private const EXCLUDED_URI_FRAGMENTS = [
        'test-fcm',
        'fcm/test',
        'stripe/webhook',
        'stripe/stripe/webhook',
        'wallet/stripe-success',
        'api/v1/',
    ];

    public function test_jwt_protected_api_routes_return_unauthorized_not_server_error(): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isJwtProtectedApiRoute($route)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $testUri = '/'.$this->substituteRouteParameters($route->uri());

                try {
                    $response = $this->json($method, $testUri, []);
                } catch (\Throwable $exception) {
                    $failures[] = "{$method} {$testUri} => exception: {$exception->getMessage()}";

                    continue;
                }

                if ($response->getStatusCode() >= 500) {
                    $failures[] = "{$method} {$testUri} => {$response->getStatusCode()}";
                }
            }
        }

        $this->assertSame(
            [],
            $failures,
            "JWT-protected API routes returned 5xx without authentication:\n".implode("\n", $failures)
        );
    }

    public function test_public_get_api_routes_do_not_return_server_error(): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isPublicGetApiRoute($route)) {
                continue;
            }

            $testUri = '/'.$this->substituteRouteParameters($route->uri());

            try {
                $response = $this->getJson($testUri);
            } catch (\Throwable $exception) {
                $failures[] = "GET {$testUri} => exception: {$exception->getMessage()}";

                continue;
            }

            if ($response->getStatusCode() >= 500) {
                $failures[] = "GET {$testUri} => {$response->getStatusCode()}";
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Public GET API routes returned 5xx:\n".implode("\n", $failures)
        );
    }

    private function isJwtProtectedApiRoute(LaravelRoute $route): bool
    {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/') || $this->shouldExclude($uri)) {
            return false;
        }

        if (str_starts_with($uri, 'api/v1/')) {
            return false;
        }

        return $this->routeUsesMiddleware($route, 'auth:api');
    }

    private function isPublicGetApiRoute(LaravelRoute $route): bool
    {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/') || $this->shouldExclude($uri)) {
            return false;
        }

        if (str_starts_with($uri, 'api/v1/')) {
            return false;
        }

        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        if ($this->routeUsesMiddleware($route, 'auth:api') || $this->routeUsesMiddleware($route, 'auth:sanctum')) {
            return false;
        }

        return true;
    }

    private function routeUsesMiddleware(LaravelRoute $route, string $needle): bool
    {
        return in_array($needle, $route->gatherMiddleware(), true);
    }

    private function shouldExclude(string $uri): bool
    {
        foreach (self::EXCLUDED_URI_FRAGMENTS as $fragment) {
            if (str_contains($uri, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function substituteRouteParameters(string $uri): string
    {
        return (string) preg_replace_callback(
            '/\{[^}]+\}/',
            static function (array $matches): string {
                $parameter = trim($matches[0], '{}');

                if (str_ends_with($parameter, '?')) {
                    $parameter = rtrim($parameter, '?');
                }

                return '1';
            },
            $uri
        );
    }
}
