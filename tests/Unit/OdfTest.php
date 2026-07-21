<?php

namespace Odtphp\Test\Unit;

use Odtphp\Exceptions\OdfException;
use Odtphp\Odf;
use Odtphp\Segment;
use Odtphp\Zip\PclZipProxy;
use Odtphp\Zip\PhpZipProxy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Odtphp\Odf
 */
class OdfTest extends TestCase
{
    private const FIXTURE_ODT = __DIR__ . '/../Fixtures/odt/tutoriel1.odt';
    private const FIXTURE_WITH_IMAGE = __DIR__ . '/../Fixtures/odt/tutoriel2.odt';
    private const FIXTURE_WITH_SEGMENT = __DIR__ . '/../Fixtures/odt/tutoriel3.odt';
    private const IMAGE = __DIR__ . '/../Fixtures/images/anaska.jpg';

    /** @var string[] temp files/dirs to clean up */
    private array $cleanupFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                @chmod($file, 0644);
                unlink($file);
            }
        }
        $this->cleanupFiles = [];
    }

    private function newOdf(string $fixture = self::FIXTURE_ODT, array $config = []): Odf
    {
        $config += ['ZIP_PROXY' => PhpZipProxy::class];

        return new Odf($fixture, $config);
    }

    /**
     * Variable substitution only happens in Odf::_save() (via the private
     * _parse() method), so tests that need the merged content.xml must
     * save first and read it back from the archive - __toString() exposes
     * the raw, not-yet-parsed contentXml (see testToStringReturnsRawNotYetParsedContentXml).
     */
    private function savedContentXml(Odf $odf): string
    {
        $out = $this->tempOdtPath();
        $odf->saveToDisk($out);

        $zip = new \ZipArchive();
        $zip->open($out);
        $content = $zip->getFromName('content.xml');
        $zip->close();

        return $content;
    }

    // --- Construction -------------------------------------------------

    public function testConstructsSuccessfullyFromAValidOdtFile(): void
    {
        $odf = $this->newOdf();

        self::assertInstanceOf(Odf::class, $odf);
        self::assertFileExists($odf->getTmpfile());
    }

    public function testConstructorLeavesTheOriginalFileUntouched(): void
    {
        $hashBefore = md5_file(self::FIXTURE_ODT);

        $odf = $this->newOdf();
        $odf->setVars('titre', 'mutated');
        $odf->saveToDisk();

        self::assertSame($hashBefore, md5_file(self::FIXTURE_ODT));
    }

    public function testConstructorThrowsWhenConfigIsNotAnArray(): void
    {
        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('Configuration data must be provided as array');

        // @phpstan-ignore-next-line intentionally invalid input under test
        new Odf(self::FIXTURE_ODT, 'not-an-array');
    }

    public function testConstructorThrowsForUnknownZipProxyClass(): void
    {
        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('NoSuchZipProxy class not found');

        new Odf(self::FIXTURE_ODT, ['ZIP_PROXY' => 'NoSuchZipProxy']);
    }

    public function testConstructorThrowsForNonExistentFile(): void
    {
        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('Nothing to parse');

        $this->newOdf('/tmp/odtphp-does-not-exist-' . uniqid() . '.odt');
    }

    public function testConstructorThrowsForACorruptArchiveWithPhpZipProxy(): void
    {
        $corrupt = tempnam(sys_get_temp_dir(), 'odtphp-corrupt-') . '.odt';
        $this->cleanupFiles[] = $corrupt;
        file_put_contents($corrupt, 'this is not a zip file');

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('Error while Opening the file');

        $this->newOdf($corrupt, ['ZIP_PROXY' => PhpZipProxy::class]);
    }

    public function testDefaultZipProxyIsPclZip(): void
    {
        $odf = new Odf(self::FIXTURE_ODT);

        self::assertSame(PclZipProxy::class, $odf->getConfig('ZIP_PROXY'));
    }

    // --- getConfig ------------------------------------------------------

    public function testGetConfigReturnsFalseForUnknownKey(): void
    {
        $odf = $this->newOdf();

        self::assertFalse($odf->getConfig('UNKNOWN_KEY'));
    }

    public function testGetConfigReturnsOverriddenValue(): void
    {
        $odf = $this->newOdf(self::FIXTURE_ODT, ['DELIMITER_LEFT' => '#']);

        self::assertSame('#', $odf->getConfig('DELIMITER_LEFT'));
    }

    // --- setVars ----------------------------------------------------------

    public function testSetVarsThrowsWhenPlaceholderNotFoundInDocument(): void
    {
        $odf = $this->newOdf();

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('var unknownVar not found in the document');

        $odf->setVars('unknownVar', 'value');
    }

    public function testSetVarsReplacesPlaceholderInContentXmlOnSave(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'Hello Title');
        $odf->setVars('message', 'Hello Message');

        $content = $this->savedContentXml($odf);

        self::assertStringContainsString('Hello Title', $content);
        self::assertStringContainsString('Hello Message', $content);
    }

    public function testSetVarsEscapesHtmlSpecialCharsByDefault(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', '<b>&"</b>');
        $odf->setVars('message', 'irrelevant');

        self::assertStringContainsString('&lt;b&gt;&amp;&quot;&lt;/b&gt;', $this->savedContentXml($odf));
    }

    public function testSetVarsWithEncodeFalseKeepsRawMarkup(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', '<b>raw</b>', false);
        $odf->setVars('message', 'irrelevant');

        self::assertStringContainsString('<b>raw</b>', $this->savedContentXml($odf));
    }

    public function testSetVarsConvertsNewlinesToTextLineBreakTags(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'irrelevant');
        $odf->setVars('message', "line1\nline2", false);

        self::assertStringContainsString('line1<text:line-break/>line2', $this->savedContentXml($odf));
    }

    public function testSetVarsIsChainable(): void
    {
        $odf = $this->newOdf();

        $result = $odf->setVars('titre', 'a');

        self::assertSame($odf, $result);
    }

    /**
     * Known limitation (documented, not fixed in this test-only phase):
     * Odf::setVars() runs $value through recursiveHtmlspecialchars() first
     * (which correctly handles arrays), but then unconditionally calls
     * utf8_encode($value) when charset is 'ISO-8859' (the default) without
     * checking whether $value is still an array. Left as-is pending a
     * Phase 2 fix decision.
     *
     * Behaviour is PHP-version dependent: internal functions only started
     * throwing \TypeError for uncoercible argument types in PHP 8.0 (see
     * https://www.php.net/manual/en/migration80.incompatible.php). On PHP
     * 7.4, utf8_encode(array) instead emits an E_WARNING and returns null.
     */
    public function testSetVarsWithArrayValueAndDefaultCharsetThrowsTypeError(): void
    {
        $odf = $this->newOdf();

        if (\PHP_VERSION_ID >= 80000) {
            $this->expectException(\TypeError::class);
            $odf->setVars('message', ['a', 'b']);

            return;
        }

        $captured = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$captured): bool {
            $captured = $errstr;

            return true;
        }, \E_WARNING);

        try {
            $odf->setVars('message', ['a', 'b']);
        } finally {
            restore_error_handler();
        }

        self::assertSame('utf8_encode() expects parameter 1 to be string, array given', $captured);
    }

    // --- setImage -----------------------------------------------------

    public function testSetImageThrowsForInvalidImagePath(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_IMAGE);

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('Invalid image');

        $odf->setImage('image', '/no/such/image-' . uniqid() . '.png');
    }

    public function testSetImageInjectsDrawFrameAndRegistersManifestEntry(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_IMAGE);
        $odf->setImage('image', self::IMAGE);
        $odf->setVars('titre', 'irrelevant');
        $odf->setVars('message', 'irrelevant');

        $out = $this->tempOdtPath();
        $odf->saveToDisk($out);

        $zip = new \ZipArchive();
        $zip->open($out);
        $content = $zip->getFromName('content.xml');
        $manifest = $zip->getFromName('META-INF/manifest.xml');
        $zip->close();

        self::assertStringContainsString('<draw:frame', $content);
        self::assertStringContainsString('xlink:href="Pictures/anaska.jpg"', $content);
        self::assertStringContainsString('Pictures/anaska.jpg', $manifest);
    }

    // --- setSegment / mergeSegment -----------------------------------

    public function testSetSegmentThrowsWhenSegmentNotFoundInDocument(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_SEGMENT);

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage("'doesNotExist' segment not found in the document");

        $odf->setSegment('doesNotExist');
    }

    public function testSetSegmentReturnsTheSameCachedInstanceOnSecondCall(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_SEGMENT);

        $first = $odf->setSegment('articles');
        $second = $odf->setSegment('articles');

        self::assertSame($first, $second);
    }

    public function testMergeSegmentThrowsWhenSegmentWasNeverDeclaredOnThisOdf(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_SEGMENT);
        $foreignSegment = new Segment('articles', '[!-- BEGIN articles --]x[!-- END articles --]', $odf);

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('cannot be parsed, has it been set yet ?');

        $odf->mergeSegment($foreignSegment);
    }

    public function testMergeSegmentInsertsMergedContentIntoDocument(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_SEGMENT);
        $odf->setVars('titre', 't');
        $odf->setVars('message', 'm');

        $article = $odf->setSegment('articles');
        $article->titreArticle('My Article Title');
        $article->texteArticle('My article body.');
        $article->merge();
        $odf->mergeSegment($article);

        self::assertStringContainsString('My Article Title', (string) $odf);
        self::assertStringContainsString('My article body.', (string) $odf);
        self::assertStringNotContainsString('[!-- BEGIN articles --]', (string) $odf);
    }

    // --- saveToDisk / getTmpfile / __toString -------------------------

    public function testSaveToDiskWithoutArgumentSavesInPlaceOverTmpfile(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'InPlace');
        $odf->setVars('message', 'msg');

        $odf->saveToDisk();

        $zip = new \ZipArchive();
        $zip->open($odf->getTmpfile());
        $content = $zip->getFromName('content.xml');
        $zip->close();

        self::assertStringContainsString('InPlace', $content);
    }

    public function testSaveToDiskWithPathCopiesToTheGivenLocation(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'Copied');
        $odf->setVars('message', 'msg');

        $out = $this->tempOdtPath();
        $odf->saveToDisk($out);

        self::assertFileExists($out);
        $zip = new \ZipArchive();
        $zip->open($out);
        $content = $zip->getFromName('content.xml');
        $zip->close();
        self::assertStringContainsString('Copied', $content);
    }

    public function testSaveToDiskThrowsWhenTargetFileIsNotWritable(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'x');
        $odf->setVars('message', 'y');

        $target = $this->tempOdtPath();
        touch($target);
        chmod($target, 0444);

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage("Permission denied : can't create");

        $odf->saveToDisk($target);
    }

    /**
     * __toString() exposes contentXml as loaded from the archive, BEFORE
     * variable substitution (which only happens during _save()) - the
     * placeholder is therefore still present, not the assigned value.
     */
    public function testToStringReturnsRawNotYetParsedContentXml(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'ToStringTest');

        self::assertIsString((string) $odf);
        self::assertStringContainsString('{titre}', (string) $odf);
        self::assertStringNotContainsString('ToStringTest', (string) $odf);
    }

    public function testDestructRemovesTheTemporaryWorkingFile(): void
    {
        $odf = $this->newOdf();
        $tmpfile = $odf->getTmpfile();
        self::assertFileExists($tmpfile);

        unset($odf);

        self::assertFileDoesNotExist($tmpfile);
    }

    // --- printVars / printDeclaredSegments -----------------------------

    public function testPrintVarsIncludesAssignedVariables(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'PrintedValue');

        self::assertStringContainsString('PrintedValue', $odf->printVars());
    }

    public function testPrintDeclaredSegmentsListsDeclaredSegmentNames(): void
    {
        $odf = $this->newOdf(self::FIXTURE_WITH_SEGMENT);
        $odf->setSegment('articles');

        self::assertStringContainsString('articles', $odf->printDeclaredSegments());
    }

    // --- custom delimiters ------------------------------------------------

    public function testCustomDelimitersAreHonouredForSetVars(): void
    {
        $odf = $this->newOdf(__DIR__ . '/../Fixtures/odt/tutoriel7.odt', [
            'DELIMITER_LEFT' => '#',
            'DELIMITER_RIGHT' => '#',
        ]);

        $odf->setVars('titre', 'CustomDelimiterValue');
        $odf->setVars('message', 'irrelevant');

        self::assertStringContainsString('CustomDelimiterValue', $this->savedContentXml($odf));
    }

    // --- exportAsAttachedFile --------------------------------------------

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * Isolated in its own process: exportAsAttachedFile() calls
     * headers_sent() and throws OdfException if output has already
     * started - which is always true once the test runner itself has
     * printed anything to stdout (e.g. under --testdox).
     */
    public function testExportAsAttachedFileStreamsAValidOdtArchive(): void
    {
        $odf = $this->newOdf();
        $odf->setVars('titre', 'ExportedValue');
        $odf->setVars('message', 'irrelevant');

        ob_start();
        $odf->exportAsAttachedFile('myfile.odt');
        $output = ob_get_clean();

        self::assertStringStartsWith('PK', $output, 'Output should be a valid zip archive');

        $tmpFile = $this->tempOdtPath();
        file_put_contents($tmpFile, $output);

        $zip = new \ZipArchive();
        $zip->open($tmpFile);
        $content = $zip->getFromName('content.xml');
        $zip->close();

        self::assertStringContainsString('ExportedValue', $content);
    }

    // --- _moveRowSegments (private, invoked automatically by __construct) ---

    /**
     * _moveRowSegments() relocates "[!-- BEGIN row.xxx --]...[!-- END row.xxx --]"
     * markers that a template author placed INSIDE a <table:table-row> so
     * they end up wrapping the whole row instead - this lets a repeated
     * segment merge an entire table row per iteration. None of the tutorial
     * fixtures exercise this feature, so it is characterized here via
     * reflection directly against the transformation logic.
     */
    public function testMoveRowSegmentsRelocatesTagsAroundTheWholeTableRow(): void
    {
        $odf = $this->newOdf();
        $reflection = new \ReflectionClass($odf);

        $contentXmlProperty = $reflection->getProperty('contentXml');
        $contentXmlProperty->setAccessible(true);
        $contentXmlProperty->setValue(
            $odf,
            '<table:table-row attr="x">[!-- BEGIN row.item --]<text:p>{item}</text:p>[!-- END row.item --]</table:table-row>'
        );

        $moveRowSegments = $reflection->getMethod('_moveRowSegments');
        $moveRowSegments->setAccessible(true);
        $moveRowSegments->invoke($odf);

        self::assertSame(
            '[!-- BEGIN item --]<table:table-row attr="x"><text:p>{item}</text:p></table:table-row>[!-- END item --]',
            $contentXmlProperty->getValue($odf)
        );
    }

    private function tempOdtPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'odtphp-out-') . '.odt';
        $this->cleanupFiles[] = $path;

        return $path;
    }
}
