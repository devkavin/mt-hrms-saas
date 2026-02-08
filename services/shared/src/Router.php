<?php

declare(strict_types=1);

namespace MtHrms\Shared;

use Throwable;

class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, handler: callable}>
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): void
    {
        if ($request->method() === 'OPTIONS') {
            JsonResponse::make(['status' => 204], 204)->send();
            return;
        }

        try {
            foreach ($this->routes as $route) {
                if ($route['method'] !== $request->method()) {
                    continue;
                }

                $params = $this->match($route['pattern'], $request->path());
                if ($params === null) {
                    continue;
                }

                $result = ($route['handler'])($request, $params);
                if ($result instanceof JsonResponse) {
                    $result->send();
                    return;
                }

                if (is_array($result)) {
                    JsonResponse::make($result)->send();
                    return;
                }

                throw new HttpException(500, 'Route handler must return array or JsonResponse.');
            }

            JsonResponse::error('Route not found.', 404)->send();
        } catch (HttpException $exception) {
            JsonResponse::error(
                $exception->getMessage(),
                $exception->statusCode,
                $exception->meta
            )->send();
        } catch (Throwable $exception) {
            $meta = [];
            if ((getenv('APP_DEBUG') ?: 'false') === 'true') {
                $meta['details'] = $exception->getMessage();
            }

            JsonResponse::error('Unexpected server error.', 500, $meta)->send();
        }
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->normalizePattern($pattern),
            'handler' => $handler,
        ];
    }

    private function normalizePattern(string $pattern): string
    {
        $trimmed = trim($pattern);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/' . trim($trimmed, '/');
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $patternSegments = $this->segments($pattern);
        $pathSegments = $this->segments($path);

        if (count($patternSegments) !== count($pathSegments)) {
            return null;
        }

        $params = [];
        foreach ($patternSegments as $index => $patternSegment) {
            $value = urldecode($pathSegments[$index]);
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $patternSegment, $matches) === 1) {
                $params[$matches[1]] = $value;
                continue;
            }

            if ($patternSegment !== $value) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @return array<int, string>
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }
}
