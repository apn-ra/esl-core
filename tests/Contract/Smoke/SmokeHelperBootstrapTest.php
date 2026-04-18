<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Smoke;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/tools/smoke/bootstrap.php';

final class SmokeHelperBootstrapTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/esl-core-smoke-bootstrap-' . bin2hex(random_bytes(6));
        $this->createDirectory($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_resolves_repo_local_vendor_autoload_from_smoke_directory(): void
    {
        $repoRoot = $this->tempDir . '/repo';
        $smokeDir = $repoRoot . '/tools/smoke';
        $autoload = $repoRoot . '/vendor/autoload.php';

        $this->createDirectory($smokeDir);
        $this->createDirectory(dirname($autoload));
        file_put_contents($autoload, "<?php\n");

        self::assertSame($autoload, resolveSmokeHelperAutoloadPath($smokeDir));
    }

    public function test_resolves_consumer_vendor_autoload_for_composer_installed_layout(): void
    {
        $packageRoot = $this->tempDir . '/consumer/vendor/apntalk/esl-core';
        $smokeDir = $packageRoot . '/tools/smoke';
        $autoload = $this->tempDir . '/consumer/vendor/autoload.php';

        $this->createDirectory($smokeDir);
        $this->createDirectory(dirname($autoload));
        file_put_contents($autoload, "<?php\n");

        self::assertSame($autoload, resolveSmokeHelperAutoloadPath($smokeDir));
    }

    public function test_throws_clear_error_when_no_supported_autoload_path_exists(): void
    {
        $smokeDir = $this->tempDir . '/consumer/vendor/apntalk/esl-core/tools/smoke';
        $this->createDirectory($smokeDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to locate Composer autoload.php for smoke helper');
        $this->expectExceptionMessage(dirname($smokeDir, 2) . '/vendor/autoload.php');
        $this->expectExceptionMessage(dirname($smokeDir, 4) . '/autoload.php');

        resolveSmokeHelperAutoloadPath($smokeDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;

            if (is_dir($entryPath)) {
                $this->removeDirectory($entryPath);
                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }

    private function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }
}
