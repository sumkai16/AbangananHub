<?php

namespace App\Services\Backup;

use RuntimeException;

class LocalStore implements BackupStore
{
    public function __construct(private readonly string $dir) {}

    public function label(): string
    {
        return 'local folder '.$this->dir;
    }

    public function put(string $localPath, string $name): void
    {
        if (! is_dir($this->dir) && ! mkdir($this->dir, 0700, true) && ! is_dir($this->dir)) {
            throw new RuntimeException("Cannot create {$this->dir}");
        }
        if (! copy($localPath, $this->path($name))) {
            throw new RuntimeException("Cannot write {$this->path($name)}");
        }
        chmod($this->path($name), 0600);
    }

    public function list(): array
    {
        $out = [];
        foreach (glob($this->dir.'/*.tar.gz.enc') ?: [] as $file) {
            $out[basename($file)] = (int) filesize($file);
        }

        return $out;
    }

    public function size(string $name): ?int
    {
        return is_file($this->path($name)) ? (int) filesize($this->path($name)) : null;
    }

    public function delete(array $names): void
    {
        foreach ($names as $name) {
            @unlink($this->path($name));
        }
    }

    public function get(string $name, string $destination): void
    {
        if (! is_file($this->path($name)) || ! copy($this->path($name), $destination)) {
            throw new RuntimeException("Backup {$name} not found in {$this->dir}");
        }
    }

    private function path(string $name): string
    {
        return $this->dir.'/'.basename($name);   // basename: a name can never climb out of the folder
    }
}
