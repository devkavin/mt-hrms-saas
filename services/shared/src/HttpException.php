<?php

declare(strict_types=1);

namespace MtHrms\Shared;

use RuntimeException;

class HttpException extends RuntimeException
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly array $meta = []
    ) {
        parent::__construct($message, $statusCode);
    }
}
