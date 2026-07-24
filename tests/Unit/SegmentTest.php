<?php

namespace Odtphp\Test\Unit;

use Odtphp\Exceptions\OdfException;
use Odtphp\Exceptions\SegmentException;
use Odtphp\Segment;
use Odtphp\Zip\PhpZipProxy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Odtphp\Segment
 */
class SegmentTest extends TestCase
{
    /** @var string[] temp files to clean up */
    private array $cleanupFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->cleanupFiles = [];
    }

    /**
     * Minimal stub of the Odf dependency required by Segment: a
     * configuration accessor and a valid, writable temporary zip file
     * (Segment::merge() opens/closes it, even with no images to add).
     *
     * Uses classic property declarations + constructor assignment
     * (no constructor property promotion, no match expression) to
     * stay PHP 7.4-compatible, per composer.json's "php": "^7.4.0".
     */
    private function stubOdf(string $delimiterLeft = '{', string $delimiterRight = '}'): object
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'odtphp-segment-');
        $this->cleanupFiles[] = $tmpfile;

        return new class ($tmpfile, $delimiterLeft, $delimiterRight) {
            private string $tmpfile;
            private string $delimiterLeft;
            private string $delimiterRight;

            public function __construct(string $tmpfile, string $delimiterLeft, string $delimiterRight)
            {
                $this->tmpfile = $tmpfile;
                $this->delimiterLeft = $delimiterLeft;
                $this->delimiterRight = $delimiterRight;
            }

            public function getConfig($configKey)
            {
                switch ($configKey) {
                    case 'ZIP_PROXY':
                        return PhpZipProxy::class;
                    case 'DELIMITER_LEFT':
                        return $this->delimiterLeft;
                    case 'DELIMITER_RIGHT':
                        return $this->delimiterRight;
                    default:
                        return false;
                }
            }

            public function getTmpfile(): string
            {
                return $this->tmpfile;
            }
        };
    }

    public function testGetNameReturnsTheSegmentName(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]content[!-- END articles --]', $this->stubOdf());

        self::assertSame('articles', $segment->getName());
    }

    public function testHasChildrenIsFalseForALeafSegment(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        self::assertFalse($segment->hasChildren());
        self::assertCount(0, $segment);
    }

    public function testAnalysesAndExposesDirectChildSegments(): void
    {
        $xml = '[!-- BEGIN parent --]before'
            . '[!-- BEGIN child --]inner text[!-- END child --]'
            . 'after[!-- END parent --]';
        $segment = new Segment('parent', $xml, $this->stubOdf());

        self::assertTrue($segment->hasChildren());
        self::assertCount(1, $segment);
        self::assertInstanceOf(Segment::class, $segment->child);
        self::assertSame('child', $segment->child->getName());
    }

    public function testGetOnUnknownChildThrowsSegmentException(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $this->expectException(SegmentException::class);
        $segment->doesNotExist;
    }

    public function testSetVarsThrowsWhenVariableNotFoundInSegmentXml(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $this->expectException(SegmentException::class);
        $this->expectExceptionMessage('var unknownVar not found in articles');
        $segment->setVars('unknownVar', 'value');
    }

    public function testSetVarsReplacesThePlaceholderOnMerge(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $segment->setVars('titre', 'Hello World');

        self::assertSame('Hello World', $segment->merge());
    }

    public function testSetVarsEscapesHtmlSpecialCharsByDefault(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $segment->setVars('titre', '<b>bold</b> & "quoted"');

        self::assertSame('&lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;', $segment->merge());
    }

    public function testSetVarsWithEncodeFalseKeepsRawMarkup(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $segment->setVars('titre', '<b>bold</b>', false);

        self::assertSame('<b>bold</b>', $segment->merge());
    }

    public function testSetVarsConvertsNewlinesToTextLineBreakTags(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $segment->setVars('titre', "line1\nline2", false);

        self::assertSame('line1<text:line-break/>line2', $segment->merge());
    }

    public function testCallMagicMethodProxiesToSetVars(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        // __call('titre', ['value']) -> setVars('titre', 'value')
        $segment->titre('Called via magic method');

        self::assertSame('Called via magic method', $segment->merge());
    }

    public function testCallMagicMethodWrapsSegmentExceptionWithMethodOrVarMessage(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        $this->expectException(SegmentException::class);
        $this->expectExceptionMessage('method doesNotExist nor var doesNotExist exist');
        $segment->doesNotExist('value');
    }

    public function testSetImageThrowsOdfExceptionForInvalidImage(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{image}[!-- END articles --]', $this->stubOdf());

        $this->expectException(OdfException::class);
        $this->expectExceptionMessage('Invalid image');
        $segment->setImage('image', '/no/such/image-' . uniqid() . '.png');
    }

    public function testSetImageInjectsDrawFrameMarkupOnMerge(): void
    {
        $imagePath = __DIR__ . '/../Fixtures/images/php.gif';
        $segment = new Segment('articles', '[!-- BEGIN articles --]{image}[!-- END articles --]', $this->stubOdf());

        $segment->setImage('image', $imagePath, -1);
        $merged = $segment->merge();

        self::assertStringContainsString('<draw:frame', $merged);
        self::assertStringContainsString('xlink:href="Pictures/php.gif"', $merged);
    }

    public function testGetXmlParsedIsEmptyUntilMergeIsCalled(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]{titre}[!-- END articles --]', $this->stubOdf());

        self::assertSame('', $segment->getXmlParsed());

        $segment->setVars('titre', 'x');
        $segment->merge();

        self::assertSame('x', $segment->getXmlParsed());
    }

    public function testRespectsCustomDelimiters(): void
    {
        $segment = new Segment('articles', '[!-- BEGIN articles --]#titre#[!-- END articles --]', $this->stubOdf('#', '#'));

        $segment->setVars('titre', 'value');

        self::assertSame('value', $segment->merge());
    }

    /**
     * Regression test: PHPStan level 6 upgrades added public Segment::getChildren()
     * accessor to fix a visibility bug where recursive iteration (getIterator() +
     * RecursiveIteratorIterator descending 2+ levels) would fail because
     * SegmentIterator::getChildren() was accessing protected $segment->children
     * from outside the class, triggering __get() magic method.
     *
     * This test verifies that recursive iteration now works correctly at all nesting levels.
     */
    public function testRecursiveIteratorDescendingTwoLevelsWorksAfterVisibilityFix(): void
    {
        $xml = '[!-- BEGIN outer --]before'
            . '[!-- BEGIN middle --]midbefore'
            . '[!-- BEGIN inner --]INNER[!-- END inner --]'
            . 'midafter[!-- END middle --]'
            . 'after[!-- END outer --]';
        $segment = new Segment('outer', $xml, $this->stubOdf());

        $collected = [];
        foreach ($segment->getIterator() as $key => $item) {
            $collected[] = [
                'key' => $key,
                'name' => $item->getName(),
            ];
        }

        // Verify we can now successfully iterate through all nesting levels
        self::assertNotEmpty($collected, 'Should collect nested segments without throwing');
        self::assertCount(2, $collected, 'Should iterate outer->middle and outer->inner');
        self::assertSame('middle', $collected[0]['name']);
        self::assertSame('inner', $collected[1]['name']);
    }
}
