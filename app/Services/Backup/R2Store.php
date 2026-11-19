<?php

namespace App\Services\Backup;

use Aws\S3\Exception\S3Exception;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use RuntimeException;

/** Cloudflare R2 through the S3 API (the AWS SDK is already a dependency, so no extra package). */
class R2Store implements BackupStore
{
    private ?S3Client $client = null;

    /** @param  array{endpoint:?string,bucket:?string,key:?string,secret:?string}  $cfg */
    public function __construct(private readonly array $cfg, private readonly string $prefix) {}

    public static function configured(array $cfg): bool
    {
        return ! empty($cfg['endpoint']) && ! empty($cfg['bucket']) && ! empty($cfg['key']) && ! empty($cfg['secret']);
    }

    public function label(): string
    {
        return 'Cloudflare R2 bucket '.$this->cfg['bucket'];
    }

    public function put(string $localPath, string $name): void
    {
        $handle = fopen($localPath, 'rb');
        try {
            $result = (new ObjectUploader($this->client(), $this->cfg['bucket'], $name, $handle))->upload();
            if (! $result) {
                throw new RuntimeException('R2 did not confirm the upload.');
            }
        } catch (S3Exception $e) {
            throw new RuntimeException('R2 upload failed: '.($e->getAwsErrorMessage() ?: $e->getMessage()), 0, $e);
        } finally {
            is_resource($handle) && fclose($handle);
        }
    }

    public function list(): array
    {
        $out = [];
        try {
            foreach ($this->client()->getPaginator('ListObjectsV2', ['Bucket' => $this->cfg['bucket'], 'Prefix' => $this->prefix.'-']) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $out[$object['Key']] = (int) $object['Size'];
                }
            }
        } catch (S3Exception $e) {
            throw new RuntimeException('R2 listing failed: '.($e->getAwsErrorMessage() ?: $e->getMessage()), 0, $e);
        }

        return $out;
    }

    public function size(string $name): ?int
    {
        try {
            return (int) $this->client()->headObject(['Bucket' => $this->cfg['bucket'], 'Key' => $name])['ContentLength'];
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw new RuntimeException('R2 check failed: '.($e->getAwsErrorMessage() ?: $e->getMessage()), 0, $e);
        }
    }

    public function delete(array $names): void
    {
        foreach (array_chunk($names, 1000) as $chunk) {
            $this->client()->deleteObjects([
                'Bucket' => $this->cfg['bucket'],
                'Delete' => ['Objects' => array_map(fn ($n) => ['Key' => $n], $chunk), 'Quiet' => true],
            ]);
        }
    }

    public function get(string $name, string $destination): void
    {
        try {
            $this->client()->getObject(['Bucket' => $this->cfg['bucket'], 'Key' => $name, 'SaveAs' => $destination]);
        } catch (S3Exception $e) {
            throw new RuntimeException("Cannot download {$name}: ".($e->getAwsErrorMessage() ?: $e->getMessage()), 0, $e);
        }
    }

    private function client(): S3Client
    {
        return $this->client ??= new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => $this->cfg['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $this->cfg['key'], 'secret' => $this->cfg['secret']],
            // R2 rejects the extra checksums newer SDK versions add by default.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ]);
    }
}
