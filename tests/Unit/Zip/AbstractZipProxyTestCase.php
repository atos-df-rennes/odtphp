<?php

namespace Odtphp\Test\Unit\Zip;

use Odtphp\Zip\ZipInterface;
use PHPUnit\Framework\TestCase;

/**
 * Shared test cases run against every ZipInterface implementation
 * (PhpZipProxy, PclZipProxy) to guarantee behavioural parity ahead of
 * the Phase 4 "pclzip vs ext-zip" decision.
 *
 * Subclasses only need to provide the concrete class under test via
 * createProxy(). Any behaviour that legitimately differs between
 * implementations must be overridden explicitly in the subclass with
 * a comment explaining the divergence (see PhpZipProxyTest for an
 * example: ZipArchive throws \ValueError on unopened operations,
 * whereas PclZipProxy returns false).
 */
abstract class AbstractZipProxyTestCase extends TestCase
{
    private const FIXTURE_ODT = __DIR__ . '/../../Fixtures/odt/tutoriel1.odt';

    /** @var string[] files to clean up after each test */
    private array $cleanupFiles = [];

    abstract protected function createProxy(): ZipInterface;

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                @chmod($file, 0644);
                unlink($file);
            }
        }
        $this->cleanupFiles = [];
        if (is_dir('./tmp')) {
            self::rrmdir('./tmp');
        }
    }

    private static function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            is_dir($path) ? self::rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Copies the fixture .odt to a fresh temporary archive so tests
     * can mutate it freely without touching the shared fixture.
     */
    private function newWorkingArchive(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'odtphp-zip-') . '.zip';
        copy(self::FIXTURE_ODT, $tmp);
        $this->cleanupFiles[] = $tmp;

        return $tmp;
    }

    public function testOpenExistingArchiveSucceeds(): void
    {
        $proxy = $this->createProxy();

        self::assertTrue($proxy->open($this->newWorkingArchive()));
    }

    public function testOpenNonExistentPathStillSucceeds(): void
    {
        // Both implementations treat open() as "open or create" - this is
        // characterization of the current (perhaps surprising) behaviour.
        $path = sys_get_temp_dir() . '/odtphp-zip-does-not-exist-' . uniqid() . '.zip';
        $this->cleanupFiles[] = $path;
        $proxy = $this->createProxy();

        self::assertTrue($proxy->open($path));
    }

    public function testGetFromNameReturnsContentForExistingMember(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        $content = $proxy->getFromName('content.xml');

        self::assertIsString($content);
        self::assertStringContainsString('<office:document-content', $content);
    }

    public function testGetFromNameReturnsFalseForMissingMember(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        self::assertFalse($proxy->getFromName('does-not-exist.xml'));
    }

    public function testAddFromStringThenReadBackReturnsTheNewContent(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        self::assertTrue($proxy->addFromString('content.xml', '<root>replaced</root>'));

        $proxy->close();
    }

    public function testAddFromStringOnNonWritableTargetReturnsFalse(): void
    {
        $archive = $this->newWorkingArchive();
        chmod($archive, 0444);
        $proxy = $this->createProxy();
        $proxy->open($archive);

        self::assertFalse($proxy->addFromString('content.xml', 'irrelevant'));
    }

    public function testAddFileWithMissingSourceReturnsFalse(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        self::assertFalse($proxy->addFile('/no/such/file-' . uniqid() . '.txt', 'inzip.txt'));
    }

    public function testAddFileOnNonWritableTargetReturnsFalse(): void
    {
        $archive = $this->newWorkingArchive();
        chmod($archive, 0444);
        $proxy = $this->createProxy();
        $proxy->open($archive);

        $sourceFile = tempnam(sys_get_temp_dir(), 'odtphp-zip-src-');
        $this->cleanupFiles[] = $sourceFile;
        file_put_contents($sourceFile, 'some content');

        self::assertFalse($proxy->addFile($sourceFile, 'copy.txt'));
    }

    public function testAddFileWithExplicitLocalNameSucceeds(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        $sourceFile = tempnam(sys_get_temp_dir(), 'odtphp-zip-src-');
        $this->cleanupFiles[] = $sourceFile;
        file_put_contents($sourceFile, 'some content');

        self::assertTrue($proxy->addFile($sourceFile, 'Pictures/copy.txt'));

        $proxy->close();
    }

    public function testCloseAfterOpenReturnsTrue(): void
    {
        $proxy = $this->createProxy();
        $proxy->open($this->newWorkingArchive());

        self::assertTrue($proxy->close());
    }
}
