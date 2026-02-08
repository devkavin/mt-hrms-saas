<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class IdGenerator
{
    public static function next(string $prefix): string
    {
        try {
            return $prefix . '_' . bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return $prefix . '_' . uniqid('', true);
        }
    }
}
