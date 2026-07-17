<?php

namespace Odtphp\Test\Unit\Exceptions;

use Odtphp\Exceptions\OdfException;
use Odtphp\Exceptions\PclZipProxyException;
use Odtphp\Exceptions\PhpZipProxyException;
use Odtphp\Exceptions\SegmentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Odtphp\Exceptions\OdfException
 * @covers \Odtphp\Exceptions\SegmentException
 * @covers \Odtphp\Exceptions\PclZipProxyException
 * @covers \Odtphp\Exceptions\PhpZipProxyException
 */
class ExceptionsTest extends TestCase
{
    /**
     * @dataProvider exceptionClassesProvider
     */
    public function testIsAThrowableException(string $exceptionClass): void
    {
        $exception = new $exceptionClass('some message', 42);

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertSame('some message', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
    }

    /**
     * @dataProvider exceptionClassesProvider
     */
    public function testCanActuallyBeThrownAndCaught(string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage('boom');

        throw new $exceptionClass('boom');
    }

    /**
     * @return array<string, array{0: class-string<\Exception>}>
     */
    public static function exceptionClassesProvider(): array
    {
        return [
            'OdfException' => [OdfException::class],
            'SegmentException' => [SegmentException::class],
            'PclZipProxyException' => [PclZipProxyException::class],
            'PhpZipProxyException' => [PhpZipProxyException::class],
        ];
    }
}
