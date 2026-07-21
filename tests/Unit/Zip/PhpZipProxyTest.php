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
     * Divergence from PclZipProxy: PhpZipProxy wraps ZipArchive directly,
     * which throws \ValueError when an operation is attempted on an
     * unopened/uninitialized archive (PHP 8+ behaviour). PclZipProxy
     * guards this case explicitly and returns false instead (see
     * PclZipProxyTest::testGetFromNameWithoutOpenReturnsFalse).
     */
    public function testGetFromNameWithoutOpenThrowsValueError(): void
    {
        $proxy = $this->createProxy();

        $this->expectException(\ValueError::class);
        $proxy->getFromName('content.xml');
    }

    /**
     * Divergence from PclZipProxy: see testGetFromNameWithoutOpenThrowsValueError.
     */
    public function testCloseWithoutOpenThrowsValueError(): void
    {
        $proxy = $this->createProxy();

        $this->expectException(\ValueError::class);
        $proxy->close();
    }
}
