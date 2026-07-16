<?php

namespace Odtphp\Test\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base class for golden-master (characterization) tests.
 *
 * These tests capture the CURRENT behaviour of the library as a reference
 * snapshot. They exist to detect unintended regressions while the library
 * is modernized (see Phase 0 of the migration plan) - they are not meant
 * to express intent about "correct" behaviour.
 *
 * Run with UPDATE_SNAPSHOTS=1 to (re)generate snapshots when a behaviour
 * change is deliberate and reviewed.
 */
abstract class OdtSnapshotTestCase extends TestCase
{
    protected function snapshotsDir(): string
    {
        return __DIR__ . '/../GoldenMaster/__snapshots__';
    }

    /**
     * Extracts a member file from an .odt (zip) archive as a string.
     */
    protected function extractFromOdt(string $odtPath, string $member): string
    {
        $zip = new \ZipArchive();
        $opened = $zip->open($odtPath);
        self::assertTrue($opened === true, "Unable to open ODT archive '$odtPath'");
        $content = $zip->getFromName($member);
        $zip->close();
        self::assertNotFalse($content, "Member '$member' not found in '$odtPath'");

        return $content;
    }

    /**
     * Normalizes XML so that snapshots are stable across runs
     * (attribute order, whitespace between tags) while still being
     * sensitive to any content/structure change.
     */
    protected function normalizeXml(string $xml): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $loaded = $dom->loadXML($xml);
        self::assertTrue($loaded, 'Produced XML is not well-formed');

        return $dom->saveXML();
    }

    /**
     * Asserts that $actual matches the stored snapshot named $name.
     * Snapshots live in tests/GoldenMaster/__snapshots__/$name.xml.
     */
    protected function assertMatchesXmlSnapshot(string $actualXml, string $name): void
    {
        $normalized = $this->normalizeXml($actualXml);
        $path = $this->snapshotsDir() . '/' . $name . '.xml';

        if (getenv('UPDATE_SNAPSHOTS') === '1' && !file_exists($path)) {
            file_put_contents($path, $normalized);
            fwrite(STDERR, "Snapshot '$name' created.\n");
        } elseif (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($path, $normalized);
            fwrite(STDERR, "Snapshot '$name' updated.\n");
        }

        self::assertFileExists(
            $path,
            "Snapshot '$name' does not exist yet. Run with UPDATE_SNAPSHOTS=1 to create it after reviewing the output."
        );
        self::assertXmlStringEqualsXmlString(
            file_get_contents($path),
            $normalized,
            "Produced XML for '$name' no longer matches the committed golden-master snapshot. "
            . "If this change is intentional, review the diff carefully then regenerate with UPDATE_SNAPSHOTS=1."
        );
    }
}
