<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly int $statusCode = 200
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function make(array $payload, int $statusCode = 200): self
    {
        return new self($payload, $statusCode);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function error(string $message, int $statusCode, array $meta = []): self
    {
        return new self(
            [
                'error' => $message,
                'status' => $statusCode,
                'meta' => $meta,
            ],
            $statusCode
        );
    }

    public function send(): void
    {
        $origin = getenv('CORS_ALLOW_ORIGIN') ?: '*';

        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-ID');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        if ($origin !== '*') {
            header('Vary: Origin');
        }

        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES);
    }
}
