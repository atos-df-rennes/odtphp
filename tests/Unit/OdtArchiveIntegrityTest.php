<?php

namespace Odtphp\Test\Unit;

use Odtphp\Odf;
use Odtphp\Test\Support\OdtArchiveAssertions;
use Odtphp\Zip\PclZipProxy;
use Odtphp\Zip\PhpZipProxy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Non-regression test for the "LibreOffice >= 25 asks to repair the
 * document" bug.
 *
 * Root cause: LibreOffice >= 25 writes some ODT entries using a ZIP data
 * descriptor (general purpose flag bit 3). When such an archive is
 * rewritten by PclZipProxy (delete + add), the rewritten local header
 * keeps that bit set without emitting a matching data descriptor, which
 * makes the resulting archive structurally invalid even though
 * ZipArchive/LibreOffice's lenient readers may still open it after a
 * "repair" prompt.
 */
class OdtArchiveIntegrityTest extends TestCase
{
    use OdtArchiveAssertions;

    private const FIXTURES = __DIR__ . '/../Fixtures/odt';

    /** @var string[] */
    private array $cleanupFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->cleanupFiles = [];
        if (is_dir('./tmp')) {
            $this->rrmdir('./tmp');
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @return array<string, array{0: string, 1: class-string<\Odtphp\Zip\ZipInterface>}>
     */
    public static function fixtureAndProxyProvider(): array
    {
        return [
            'libreoffice7 + PhpZipProxy' => ['libreoffice7.odt', PhpZipProxy::class],
            'libreoffice7 + PclZipProxy' => ['libreoffice7.odt', PclZipProxy::class],
            'libreoffice25 + PhpZipProxy' => ['libreoffice25.odt', PhpZipProxy::class],
            'libreoffice25 + PclZipProxy' => ['libreoffice25.odt', PclZipProxy::class],
        ];
    }

    /**
     * The source fixtures themselves must still contain data-descriptor
     * entries, otherwise this test would no longer exercise the bug.
     */
    #[DataProvider('fixtureAndProxyProvider')]
    public function testFixtureStillExercisesDataDescriptorRegression(string $fixture, string $proxyClass): void
    {
        $this->assertHasDataDescriptorEntries(self::FIXTURES . '/' . $fixture);
    }

    #[DataProvider('fixtureAndProxyProvider')]
    public function testSavedArchiveIsStructurallyValid(string $fixture, string $proxyClass): void
    {
        $output = tempnam(sys_get_temp_dir(), 'odtphp-integrity-') . '.odt';
        $this->cleanupFiles[] = $output;

        $odf = new Odf(self::FIXTURES . '/' . $fixture, ['ZIP_PROXY' => $proxyClass]);
        $odf->setVars('BI', 'Test');
        $odf->saveToDisk($output);

        $this->assertValidOdtArchive($output);
        $this->assertMimetypeEntryIsConformant($output);
        $this->assertArchiveEntriesAreReadable($output);
    }

    /**
     * Same as above but exercising the addFile() path (image insertion),
     * which triggers a second archive rewrite.
     */
    #[DataProvider('fixtureAndProxyProvider')]
    public function testSavedArchiveWithImageIsStructurallyValid(string $fixture, string $proxyClass): void
    {
        $output = tempnam(sys_get_temp_dir(), 'odtphp-integrity-img-') . '.odt';
        $this->cleanupFiles[] = $output;

        $odf = new Odf(self::FIXTURES . '/' . $fixture, ['ZIP_PROXY' => $proxyClass]);
        $odf->setVars('BI', 'Test');
        $odf->setImage('PI', __DIR__ . '/../Fixtures/images/anaska.jpg');
        $odf->saveToDisk($output);

        $this->assertValidOdtArchive($output);
        $this->assertMimetypeEntryIsConformant($output);
        $this->assertArchiveEntriesAreReadable($output);
    }
}
