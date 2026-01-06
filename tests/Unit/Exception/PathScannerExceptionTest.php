<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Exception;

use Cpsit\QualityTools\Exception\PathScannerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathScannerException::class)]
final class PathScannerExceptionTest extends TestCase
{
    public function testExceptionCanBeCreatedWithDefaultValues(): void
    {
        $exception = new PathScannerException();
        
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertEquals('', $exception->getMessage());
        self::assertEquals(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
    
    public function testExceptionCanBeCreatedWithCustomMessage(): void
    {
        $message = 'Custom path scanner error message';
        $exception = new PathScannerException($message);
        
        self::assertEquals($message, $exception->getMessage());
        self::assertEquals(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
    
    public function testExceptionCanBeCreatedWithCustomCode(): void
    {
        $message = 'Custom message';
        $code = 123;
        $exception = new PathScannerException($message, $code);
        
        self::assertEquals($message, $exception->getMessage());
        self::assertEquals($code, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
    
    public function testExceptionCanBeCreatedWithPreviousException(): void
    {
        $previousException = new \RuntimeException('Previous exception');
        $message = 'Current exception';
        $code = 456;
        
        $exception = new PathScannerException($message, $code, $previousException);
        
        self::assertEquals($message, $exception->getMessage());
        self::assertEquals($code, $exception->getCode());
        self::assertSame($previousException, $exception->getPrevious());
    }
    
    public function testExceptionInheritsFromRuntimeException(): void
    {
        $exception = new PathScannerException();
        
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertInstanceOf(\Exception::class, $exception);
        self::assertInstanceOf(\Throwable::class, $exception);
    }
}