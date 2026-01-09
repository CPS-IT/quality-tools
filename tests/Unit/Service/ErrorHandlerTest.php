<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\TransientException;
use Cpsit\QualityTools\Service\ErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $errorHandler;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->errorHandler = new ErrorHandler();
        $this->output = new BufferedOutput();
    }

    public function testHandleStructuredException(): void
    {
        $exception = new ConfigurationException(
            'Config file not found',
            ConfigurationException::ERROR_CONFIG_FILE_NOT_FOUND,
            null,
            ['Check file path', 'Verify permissions'],
            ['file' => '/path/to/config.yaml'],
        );

        $exitCode = $this->errorHandler->handleException($exception, $this->output);

        self::assertEquals(2, $exitCode);

        $output = $this->output->fetch();
        self::assertStringContainsString('Configuration Error (1001)', $output);
        self::assertStringContainsString('Config file not found', $output);
        self::assertStringContainsString('Troubleshooting:', $output);
        self::assertStringContainsString('1. Check file path', $output);
        self::assertStringContainsString('2. Verify permissions', $output);
    }

    public function testHandleStructuredExceptionVerbose(): void
    {
        $exception = new ConfigurationException(
            'Config validation failed',
            ConfigurationException::ERROR_CONFIG_VALIDATION_FAILED,
            null,
            ['Fix syntax errors'],
            ['file' => '/path/to/config.yaml', 'errors' => ['Missing section']],
        );

        $this->output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);
        $exitCode = $this->errorHandler->handleException($exception, $this->output, true);

        self::assertEquals(2, $exitCode);

        $output = $this->output->fetch();
        self::assertStringContainsString('Context:', $output);
        self::assertStringContainsString('file: /path/to/config.yaml', $output);
        self::assertStringContainsString('errors: ["Missing section"]', $output);
    }

    public function testHandleGenericException(): void
    {
        $exception = new \RuntimeException('Something went wrong');

        $exitCode = $this->errorHandler->handleException($exception, $this->output);

        self::assertEquals(1, $exitCode);

        $output = $this->output->fetch();
        self::assertStringContainsString('Unexpected Error: Something went wrong', $output);
        self::assertStringContainsString('Troubleshooting:', $output);
        self::assertStringContainsString('1. Run the command with --verbose', $output);
    }

    public function testExecuteWithRetrySuccess(): void
    {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            ++$callCount;

            return 'success';
        };

        $result = $this->errorHandler->executeWithRetry($operation, $this->output);

        self::assertEquals('success', $result);
        self::assertEquals(1, $callCount);
    }

    public function testExecuteWithRetryTransientFailure(): void
    {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            ++$callCount;
            if ($callCount < 3) {
                throw new TransientException('Temporary failure');
            }

            return 'success';
        };

        $result = $this->errorHandler->executeWithRetry($operation, $this->output);

        self::assertEquals('success', $result);
        self::assertEquals(3, $callCount);
    }

    public function testExecuteWithRetryMaxAttemptsExceeded(): void
    {
        $callCount = 0;
        $operation = function () use (&$callCount): void {
            ++$callCount;
            throw new TransientException('Always fails');
        };

        $this->expectException(TransientException::class);
        $this->expectExceptionMessage('Always fails');

        $this->errorHandler->executeWithRetry($operation, $this->output, 2);

        self::assertEquals(2, $callCount);
    }

    public function testExecuteWithRetryNonTransientFailure(): void
    {
        $operation = function (): void {
            throw new \RuntimeException('Non-transient failure');
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-transient failure');

        $this->errorHandler->executeWithRetry($operation, $this->output);
    }

    public function testCreateErrorResponse(): void
    {
        $exception = new ConfigurationException(
            'Test error',
            1001,
            null,
            ['Step 1'],
            ['context' => 'value'],
        );

        $response = $this->errorHandler->createErrorResponse($exception);

        self::assertArrayHasKey('type', $response);
        self::assertArrayHasKey('code', $response);
        self::assertArrayHasKey('message', $response);
        self::assertArrayHasKey('troubleshooting', $response);
        self::assertArrayHasKey('context', $response);
        self::assertArrayHasKey('timestamp', $response);

        self::assertEquals('configuration', $response['type']);
        self::assertEquals(1001, $response['code']);
        self::assertEquals('Test error', $response['message']);
    }

    public function testCreateErrorResponseGeneric(): void
    {
        $exception = new \RuntimeException('Generic error', 1);

        $response = $this->errorHandler->createErrorResponse($exception);

        self::assertEquals('unexpected', $response['type']);
        self::assertEquals(1, $response['code']);
        self::assertEquals('Generic error', $response['message']);
        self::assertArrayHasKey('troubleshooting', $response);
        self::assertArrayHasKey('context', $response);
    }
}
