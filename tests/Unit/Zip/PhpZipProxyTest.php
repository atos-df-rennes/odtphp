<?php

namespace Odtphp\Test\Unit\Zip;

use Odtphp\Zip\PhpZipProxy;
use Odtphp\Zip\ZipInterface;

/**
 * @covers \Odtphp\Zip\PhpZipProxy
 */
class PhpZipProxyTest extends AbstractZipProxyTestCase
{
    protected function createProxy(): ZipInterface
    {
        return new PhpZipProxy();
    }

    /**
     * Divergence from PclZipProxy: PhpZipProxy wraps ZipArchive directly.
     * On PHP 8+, ZipArchive throws \ValueError when an operation is
     * attempted on an unopened/uninitialized archive. On PHP 7.4, the same
     * call instead emits an E_WARNING ("Invalid or uninitialized Zip
     * object") and returns false - PclZipProxy guards this case explicitly
     * on every version and returns false instead (see
     * PclZipProxyTest::testGetFromNameWithoutOpenReturnsFalse).
     */
    public function testGetFromNameWithoutOpenThrowsValueError(): void
    {
        $proxy = $this->createProxy();

        if (\PHP_VERSION_ID >= 80000) {
            $this->expectException(\ValueError::class);
            $proxy->getFromName('content.xml');

            return;
        }

        $captured = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$captured): bool {
            $captured = $errstr;

            return true;
        }, \E_WARNING);

        try {
            self::assertFalse($proxy->getFromName('content.xml'));
        } finally {
            restore_error_handler();
        }

        self::assertSame('ZipArchive::getFromName(): Invalid or uninitialized Zip object', $captured);
    }

    /**
     * Divergence from PclZipProxy: see testGetFromNameWithoutOpenThrowsValueError.
     */
    public function testCloseWithoutOpenThrowsValueError(): void
    {
        $proxy = $this->createProxy();

        if (\PHP_VERSION_ID >= 80000) {
            $this->expectException(\ValueError::class);
            $proxy->close();

            return;
        }

        $captured = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$captured): bool {
            $captured = $errstr;

            return true;
        }, \E_WARNING);

        try {
            self::assertFalse($proxy->close());
        } finally {
            restore_error_handler();
        }

        self::assertSame('ZipArchive::close(): Invalid or uninitialized Zip object', $captured);
    }
}
