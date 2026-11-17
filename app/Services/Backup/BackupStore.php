<?php

namespace App\Services\Backup;

/** Where encrypted archives live: Cloudflare R2 in production, a local folder for trying it out. */
interface BackupStore
{
    public function label(): string;

    /** Upload a finished archive under $name. Throws on failure. */
    public function put(string $localPath, string $name): void;

    /** @return array<string, int> archive name => size in bytes, oldest first not guaranteed */
    public function list(): array;

    /** Size of one stored archive, or null when it is not there. */
    public function size(string $name): ?int;

    /** @param  list<string>  $names */
    public function delete(array $names): void;

    public function get(string $name, string $destination): void;
}
