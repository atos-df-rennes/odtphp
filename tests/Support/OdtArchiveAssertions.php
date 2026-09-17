<?php

namespace Odtphp\Test\Support;

/**
 * Low-level ZIP structural assertions for generated .odt files.
 *
 * These checks are intentionally independent of ZipArchive/PclZip: they
 * parse the ZIP local headers and central directory by hand so that a
 * regression in either writer (e.g. a stale "general purpose flag" bit 3
 * left over from a data-descriptor entry) is caught even when PHP's own
 * ZipArchive is lenient enough to still open the file.
 *
 * See docs of the "support new LibreOffice versions" effort: LibreOffice
 * >= 25 produces ODT files whose non-content entries are written with a
 * data descriptor (flag bit 3). When PclZipProxy rewrites such an archive
 * (delete + add), it must clear that bit once CRC/sizes are known and
 * known, or LibreOffice >= 25 refuses to open the result without a
 * "repair" prompt.
 */
trait OdtArchiveAssertions
{
    private const DATA_DESCRIPTOR_BIT = 0x08;

    /**
     * Asserts that every entry whose general purpose flag has bit 3 set
     * is genuinely followed by a data descriptor signature. This is the
     * exact invariant that PclZip used to violate.
     */
    protected function assertValidOdtArchive(string $path): void
    {
        $bytes = file_get_contents($path);
        self::assertNotFalse($bytes, "Unable to read '$path'");

        foreach ($this->readCentralDirectoryEntries($bytes) as $entry) {
            if (($entry['flag'] & self::DATA_DESCRIPTOR_BIT) === 0) {
                continue;
            }

            $localOffset = $entry['local_offset'];
            [$localFlag, $localFilenameLen, $localExtraLen] = $this->readLocalHeaderLengths($bytes, $localOffset);

            self::assertSame(
                $entry['flag'] & self::DATA_DESCRIPTOR_BIT,
                $localFlag & self::DATA_DESCRIPTOR_BIT,
                "Entry '{$entry['filename']}': local header flag disagrees with central directory on the data-descriptor bit."
            );

            $dataStart = $localOffset + 30 + $localFilenameLen + $localExtraLen;
            $dataEnd = $dataStart + $entry['compressed_size'];
            $descriptorSignature = substr($bytes, $dataEnd, 4);

            self::assertSame(
                "PK\x07\x08",
                $descriptorSignature,
                "Entry '{$entry['filename']}' declares a data descriptor (flag bit 3) but none was found "
                . "right after its compressed data. The archive is structurally invalid (this is the bug "
                . "that makes LibreOffice >= 25 ask to repair the document)."
            );
        }
    }

    /**
     * Asserts that 'mimetype' is the first entry of the archive, stored
     * (no compression) and without any extra field, as required by the
     * ODF specification.
     */
    protected function assertMimetypeEntryIsConformant(string $path): void
    {
        $bytes = file_get_contents($path);
        self::assertNotFalse($bytes, "Unable to read '$path'");

        $entries = $this->readCentralDirectoryEntries($bytes);
        self::assertNotEmpty($entries, "Archive '$path' has no entries");
        $first = $entries[0];

        self::assertSame('mimetype', $first['filename'], "'mimetype' must be the first entry of the archive.");
        self::assertSame(0, $first['method'], "'mimetype' must be stored (uncompressed).");

        [, , $localExtraLen] = $this->readLocalHeaderLengths($bytes, $first['local_offset']);
        self::assertSame(0, $localExtraLen, "'mimetype' must not have an extra field.");

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path) === true, "Unable to open '$path' with ZipArchive");
        $content = $zip->getFromName('mimetype');
        $zip->close();
        self::assertSame('application/vnd.oasis.opendocument.text', $content);
    }

    /**
     * Asserts that every entry of the archive can be decompressed
     * (CRC/size consistency), using ZipArchive as the reference reader.
     */
    protected function assertArchiveEntriesAreReadable(string $path): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path) === true, "Unable to open '$path' with ZipArchive");

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            self::assertNotFalse($name, "Unable to read entry name at index $i in '$path'");
            $content = $zip->getFromIndex($i);
            self::assertNotFalse($content, "Entry '$name' in '$path' could not be read/decompressed.");
        }

        $zip->close();
    }

    /**
     * Guard used on the source fixtures: fails if none of the entries use
     * a data descriptor, meaning the fixture would no longer exercise the
     * bit-3 regression this test suite protects against.
     */
    protected function assertHasDataDescriptorEntries(string $path): void
    {
        $bytes = file_get_contents($path);
        self::assertNotFalse($bytes, "Unable to read '$path'");

        $withDescriptor = array_filter(
            $this->readCentralDirectoryEntries($bytes),
            static fn(array $entry): bool => ($entry['flag'] & self::DATA_DESCRIPTOR_BIT) !== 0
        );

        self::assertNotEmpty(
            $withDescriptor,
            "Fixture '$path' no longer contains data-descriptor entries; it does not exercise the LibreOffice >= 25 regression anymore."
        );
    }

    /**
     * @return array<int, array{filename: string, flag: int, method: int, compressed_size: int, local_offset: int}>
     */
    private function readCentralDirectoryEntries(string $bytes): array
    {
        $eocdPos = strrpos($bytes, "PK\x05\x06");
        self::assertNotFalse($eocdPos, 'End of central directory record not found.');

        $eocd = unpack('vdisk/vcddisk/ventries/ventriestotal/Vsize/Voffset/vcommentlen', substr($bytes, $eocdPos + 4, 18));
        self::assertIsArray($eocd);

        $entries = [];
        $offset = $eocd['offset'];
        for ($i = 0; $i < $eocd['entriestotal']; $i++) {
            self::assertSame('PK' . "\x01\x02", substr($bytes, $offset, 4), "Malformed central directory entry #$i.");

            $header = unpack(
                'vversion/vversion_extracted/vflag/vmethod/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Vlocal_offset',
                substr($bytes, $offset + 4, 42)
            );
            self::assertIsArray($header);

            $filenameStart = $offset + 46;
            $filename = substr($bytes, $filenameStart, $header['filename_len']);

            $entries[] = [
                'filename' => $filename,
                'flag' => $header['flag'],
                'method' => $header['method'],
                'compressed_size' => $header['compressed_size'],
                'local_offset' => $header['local_offset'],
            ];

            $offset = $filenameStart + $header['filename_len'] + $header['extra_len'] + $header['comment_len'];
        }

        return $entries;
    }

    /**
     * @return array{0: int, 1: int, 2: int} [flag, filename_len, extra_len]
     */
    private function readLocalHeaderLengths(string $bytes, int $localOffset): array
    {
        self::assertSame("PK\x03\x04", substr($bytes, $localOffset, 4), "Malformed local header at offset $localOffset.");
        $header = unpack('vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len', substr($bytes, $localOffset + 4, 26));
        self::assertIsArray($header);

        return [$header['flag'], $header['filename_len'], $header['extra_len']];
    }
}
