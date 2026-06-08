<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class RateLimiter
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public function isAllowed(string $bucket, int $limit, int $windowSeconds = 60): bool
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $now = time();
        $window = intdiv($now, $windowSeconds);
        $safeBucket = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $bucket) ?: 'unknown';
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('commerce_mcp/rate_limit');
        $path = 'commerce_mcp/rate_limit/' . hash('sha256', $safeBucket) . '.json';

        $data = ['window' => $window, 'count' => 0];
        if ($directory->isExist($path)) {
            $decoded = json_decode((string)$directory->readFile($path), true);
            if (is_array($decoded)
                && isset($decoded['window'], $decoded['count'])
                && (int)$decoded['window'] === $window
            ) {
                $data = ['window' => $window, 'count' => (int)$decoded['count']];
            }
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count']++;
        $directory->writeFile($path, json_encode($data, JSON_THROW_ON_ERROR), 'w+');

        return true;
    }
}
