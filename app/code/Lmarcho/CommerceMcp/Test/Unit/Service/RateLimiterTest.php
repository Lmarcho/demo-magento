<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Service;

use Lmarcho\CommerceMcp\Service\RateLimiter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    public function testAllowsOnlyConfiguredLimitWithinWindow(): void
    {
        $files = [];
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('isExist')->willReturnCallback(
            static function (string $path) use (&$files): bool {
                return isset($files[$path]);
            }
        );
        $directory->method('readFile')->willReturnCallback(
            static function (string $path) use (&$files): string {
                return $files[$path];
            }
        );
        $directory->method('writeFile')->willReturnCallback(
            static function (string $path, string $content) use (&$files): void {
                $files[$path] = $content;
            }
        );
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')
            ->with(DirectoryList::VAR_DIR)
            ->willReturn($directory);

        $limiter = new RateLimiter($filesystem);

        self::assertTrue($limiter->isAllowed('client:1', 2));
        self::assertTrue($limiter->isAllowed('client:1', 2));
        self::assertFalse($limiter->isAllowed('client:1', 2));
    }
}
