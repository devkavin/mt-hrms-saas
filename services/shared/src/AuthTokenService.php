<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class AuthTokenService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $configured = $secret ?? getenv('APP_TOKEN_SECRET') ?: '';
        $this->secret = $configured !== '' ? $configured : 'dev-only-change-this-secret';
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issue(array $claims, int $ttlSeconds = 28800): string
    {
        $now = time();
        $payload = array_merge(
            $claims,
            [
                'iat' => $now,
                'exp' => $now + $ttlSeconds,
            ]
        );

        $payloadSegment = self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $payloadSegment, $this->secret, true));

        return $payloadSegment . '.' . $signature;
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        if ($token === '' || !str_contains($token, '.')) {
            throw new HttpException(401, 'Malformed bearer token.');
        }

        [$payloadSegment, $signatureSegment] = explode('.', $token, 2);

        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', $payloadSegment, $this->secret, true));
        if (!hash_equals($expectedSignature, $signatureSegment)) {
            throw new HttpException(401, 'Invalid bearer token signature.');
        }

        $decodedPayload = self::base64UrlDecode($payloadSegment);
        if (!is_string($decodedPayload) || $decodedPayload === '') {
            throw new HttpException(401, 'Invalid bearer token payload.');
        }

        $payload = json_decode($decodedPayload, true);
        if (!is_array($payload)) {
            throw new HttpException(401, 'Invalid bearer token payload.');
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            throw new HttpException(401, 'Bearer token has expired.');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
