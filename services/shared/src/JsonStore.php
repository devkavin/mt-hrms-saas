<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class JsonStore
{
    private string $path;

    public function __construct(private readonly string $namespace)
    {
        $baseDir = dirname(__DIR__, 2) . '/.runtime-data';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $this->path = $baseDir . '/' . $this->sanitize($namespace) . '.json';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->read();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $records = $this->read();
        return $records[$id] ?? null;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function set(string $id, array $record): array
    {
        $records = $this->read();
        $records[$id] = $record;
        $this->write($records);

        return $record;
    }

    public function delete(string $id): void
    {
        $records = $this->read();
        if (!array_key_exists($id, $records)) {
            return;
        }

        unset($records[$id]);
        $this->write($records);
    }

    /**
     * @param callable(array<string, mixed>):bool|null $filter
     * @return array<int, array<string, mixed>>
     */
    public function list(?callable $filter = null): array
    {
        $records = array_values($this->read());
        if ($filter !== null) {
            $records = array_values(array_filter($records, $filter));
        }

        usort(
            $records,
            static fn (array $left, array $right): int => strcmp(
                (string) ($right['updated_at'] ?? ''),
                (string) ($left['updated_at'] ?? '')
            )
        );

        return $records;
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value) ?? 'store';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $record) {
            if (!is_string($key) || !is_array($record)) {
                continue;
            }
            $normalized[$key] = $record;
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $records
     */
    private function write(array $records): void
    {
        file_put_contents(
            $this->path,
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
