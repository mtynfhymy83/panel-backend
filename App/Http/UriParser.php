<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Extracts the API version and clean path from a request URI.
 * Accepts both /v1/... and /api/v1/... styles.
 */
class UriParser
{
    private string $apiVersion = 'v1';
    private string $path = '/';

    public function __construct(private string $uri)
    {
        $this->parse();
    }

    private function parse(): void
    {
        $uri = explode('?', $this->uri, 2)[0];
        $uri = rtrim($uri, '/') ?: '/';

        if (preg_match('#^/api/v(\d+)(/.*)?$#', $uri, $matches)) {
            $this->apiVersion = 'v' . $matches[1];
            $this->path = $matches[2] ?? '/';
            return;
        }

        if (preg_match('#^/v(\d+)(/.*)?$#', $uri, $matches)) {
            $this->apiVersion = 'v' . $matches[1];
            $this->path = $matches[2] ?? '/';
            return;
        }

        if (preg_match('#^/api(/.*)?$#', $uri, $matches)) {
            $this->apiVersion = 'api';
            $this->path = $matches[1] ?? '/';
            if ($this->path === '') {
                $this->path = '/';
            }
            return;
        }

        $this->path = $uri;
    }

    public function getVersion(): string
    {
        return $this->apiVersion;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public static function parseUri(string $uri): array
    {
        $p = new self($uri);
        return ['version' => $p->getVersion(), 'path' => $p->getPath()];
    }
}
