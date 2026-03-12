<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Exception;

use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\QualityToolsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualityToolsException::class)]
final class QualityToolsExceptionTest extends TestCase
{
    public function testGetErrorInfo(): void
    {
        $troubleshooting = ['Step 1', 'Step 2'];
        $context = ['key' => 'value'];

        $exception = new ConfigurationException(
            'Test message',
            1001,
            null,
            $troubleshooting,
            $context,
        );

        $errorInfo = $exception->getErrorInfo();

        self::assertArrayHasKey('type', $errorInfo);
        self::assertArrayHasKey('code', $errorInfo);
        self::assertArrayHasKey('message', $errorInfo);
        self::assertArrayHasKey('troubleshooting', $errorInfo);
        self::assertArrayHasKey('context', $errorInfo);
        self::assertArrayHasKey('timestamp', $errorInfo);

        self::assertEquals('configuration', $errorInfo['type']);
        self::assertEquals(1001, $errorInfo['code']);
        self::assertEquals('Test message', $errorInfo['message']);
        self::assertEquals($troubleshooting, $errorInfo['troubleshooting']);
        self::assertEquals($context, $errorInfo['context']);
    }

    public function testGetErrorType(): void
    {
        $exception = new ConfigurationException('Test');
        self::assertEquals('configuration', $exception->getErrorType());
    }

    public function testGetTroubleshootingSteps(): void
    {
        $troubleshooting = ['Step 1', 'Step 2'];
        $exception = new ConfigurationException('Test', 1001, null, $troubleshooting);

        self::assertEquals($troubleshooting, $exception->getTroubleshootingSteps());
    }

    public function testGetContext(): void
    {
        $context = ['key' => 'value', 'number' => 42];
        $exception = new ConfigurationException('Test', 1001, null, [], $context);

        self::assertEquals($context, $exception->getContext());
    }

    public function testIsRetryable(): void
    {
        $exception = new ConfigurationException('Test');
        self::assertFalse($exception->isRetryable());
    }

    public function testGetSuggestedExitCode(): void
    {
        $exception = new ConfigurationException('Test');
        self::assertEquals(2, $exception->getSuggestedExitCode());
    }
}
