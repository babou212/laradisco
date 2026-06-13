<?php

namespace App\Support;

use Aws\S3\S3Client;
use DateTimeInterface;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Signs S3 temporary (presigned) URLs against a separate "public" endpoint when
 * one is configured on the disk via `public_endpoint`.
 *
 * This solves local-dev split-horizon: the backend talks to the S3 service over
 * an internal docker hostname (e.g. http://garage:3900) for put/get, but the
 * presigned URL handed to the desktop/web client must use a host-reachable
 * address (e.g. http://localhost:3900). The host header is part of the SigV4
 * signature, so the URL has to be *signed* with the public endpoint — it can't
 * be rewritten afterwards.
 *
 * In production no `public_endpoint` is set, so this falls back to the default
 * media-library behaviour (single endpoint).
 */
class PublicEndpointUrlGenerator extends DefaultUrlGenerator
{
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        $diskName = $this->getDiskName();
        $config = config("filesystems.disks.{$diskName}");

        $publicEndpoint = $config['public_endpoint'] ?? null;

        if (! $publicEndpoint || ($config['driver'] ?? null) !== 's3') {
            return parent::getTemporaryUrl($expiration, $options);
        }

        $client = new S3Client([
            'region' => $config['region'] ?? 'us-east-1',
            'version' => 'latest',
            'endpoint' => $publicEndpoint,
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        $command = $client->getCommand('GetObject', array_merge([
            'Bucket' => $config['bucket'],
            'Key' => $this->getPathRelativeToRoot(),
        ], $options));

        return (string) $client->createPresignedRequest($command, $expiration)->getUri();
    }
}
