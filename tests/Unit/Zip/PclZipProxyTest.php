<?php

namespace Odtphp\Test\Unit\Zip;

use Odtphp\Zip\PclZipProxy;
use Odtphp\Zip\ZipInterface;

/**
 * @covers \Odtphp\Zip\PclZipProxy
 */
class PclZipProxyTest extends AbstractZipProxyTestCase
{
    protected function createProxy(): ZipInterface
    {
        return new PclZipProxy();
    }

    /**
     * Divergence from PhpZipProxy: PclZipProxy explicitly guards against
     * operating on an unopened archive and returns false instead of
     * throwing (see PhpZipProxyTest::testGetFromNameWithoutOpenThrowsValueError).
     */
    public function testGetFromNameWithoutOpenReturnsFalse(): void
    {
        $proxy = $this->createProxy();

        self::assertFalse($proxy->getFromName('content.xml'));
    }

    /**
     * Divergence from PhpZipProxy: see testGetFromNameWithoutOpenReturnsFalse.
     */
    public function testCloseWithoutOpenReturnsFalse(): void
    {
        $proxy = $this->createProxy();

        self::assertFalse($proxy->close());
    }
}
