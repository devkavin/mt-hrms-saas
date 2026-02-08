<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $json
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers,
        private readonly array $query,
        private readonly array $json,
        private readonly string $rawBody
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = self::normalizePath((string) parse_url($uri, PHP_URL_PATH));
        $headers = self::readHeaders();
        $rawBody = (string) file_get_contents('php://input');
        $json = self::decodeJsonBody($headers, $rawBody);

        return new self(
            $method,
            $path,
            $headers,
            is_array($_GET) ? $_GET : [],
            $json,
            $rawBody
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->json);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);

        return $this->headers[$normalized] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return $this->json;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('authorization');
        if ($authorization === null) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/' . trim($trimmed, '/');
    }

    /**
     * @return array<string, string>
     */
    private static function readHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $normalized = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$normalized] = $value;
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $normalized = strtolower(str_replace('_', '-', $key));
                $headers[$normalized] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private static function decodeJsonBody(array $headers, string $rawBody): array
    {
        if ($rawBody === '') {
            return [];
        }

        $contentType = strtolower($headers['content-type'] ?? '');
        if (!str_contains($contentType, 'application/json')) {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
