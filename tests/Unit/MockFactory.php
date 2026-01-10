<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\CommandBuilder;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Process\Process;

/**
 * Factory for creating commonly used mocks with sensible defaults.
 */
final readonly class MockFactory
{
    public function __construct(
        private BaseTestCase $testCase,
    ) {
    }

    /**
     * Create a FilesystemService mock with common filesystem operations.
     */
    public function createFilesystemServiceMock(array $files = []): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory(FilesystemService::class);

        // Configure common methods
        $mock->method('fileExists')
            ->willReturnCallback(fn (string $path): bool => isset($files[$path]));

        $mock->method('directoryExists')
            ->willReturnCallback(fn (string $path): bool => \in_array($path, array_keys($files), true) || str_ends_with($path, '/'));

        $mock->method('readFile')
            ->willReturnCallback(function (string $path) use ($files): string {
                if (!isset($files[$path])) {
                    throw new \RuntimeException("File not found: {$path}");
                }

                return $files[$path];
            });

        $mock->method('writeFile')
            ->willReturnCallback(function (string $path, string $content) use (&$files): void {
                $files[$path] = $content;
            });

        return $mock;
    }

    /**
     * Create a SecurityService mock with secure defaults.
     */
    public function createSecurityServiceMock(): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory(SecurityService::class);

        $mock->method('setSecureFilePermissions')
            ->willReturn(true);

        $mock->method('interpolateEnvironmentVariables')
            ->willReturnCallback(fn (string $content): string => $this->interpolateEnvironmentVariables($content));

        $mock->method('validateTrustedPath')
            ->willReturn(true);

        return $mock;
    }

    /**
     * Create a ConfigurationValidator mock.
     */
    public function createConfigurationValidatorMock(bool $isValid = true, array $errors = []): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory(ConfigurationValidator::class);

        $mock->method('validate')
            ->willReturn($isValid);

        $mock->method('getErrors')
            ->willReturn($errors);

        return $mock;
    }

    /**
     * Create a ProcessExecutor mock for command execution testing.
     */
    public function createProcessExecutorMock(array $results = []): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory(ProcessExecutor::class);

        $mock->method('execute')
            ->willReturnCallback(function (Process $process) use ($results): int {
                $commandLine = $process->getCommandLine();

                // Return configured result or default success
                foreach ($results as $pattern => $result) {
                    if (str_contains($commandLine, $pattern)) {
                        return $result['exitCode'] ?? 0;
                    }
                }

                return 0; // Default success
            });

        return $mock;
    }

    /**
     * Create a CommandBuilder mock for building commands.
     */
    public function createCommandBuilderMock(array $commandMappings = []): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory(CommandBuilder::class);

        $mock->method('buildCommand')
            ->willReturnCallback(function (string $tool, array $arguments) use ($commandMappings): Process {
                $commandLine = $commandMappings[$tool] ?? [$tool];

                if (!empty($arguments)) {
                    $commandLine = array_merge($commandLine, $arguments);
                }

                return new Process($commandLine);
            });

        return $mock;
    }

    /**
     * Create a complete dependency set for YamlConfigurationLoader.
     */
    public function createYamlConfigurationLoaderDependencies(array $files = []): array
    {
        return [
            'validator' => $this->createConfigurationValidatorMock(),
            'securityService' => $this->createSecurityServiceMock(),
            'filesystemService' => $this->createFilesystemServiceMock($files),
        ];
    }

    /**
     * Create mocks for console command testing.
     */
    public function createConsoleCommandDependencies(): array
    {
        return [
            'processExecutor' => $this->createProcessExecutorMock(),
            'commandBuilder' => $this->createCommandBuilderMock(),
            'filesystemService' => $this->createFilesystemServiceMock(),
        ];
    }

    /**
     * Simple environment variable interpolation for testing.
     */
    private function interpolateEnvironmentVariables(string $content): string
    {
        return preg_replace_callback(
            '/\$\{([^}]+)\}/',
            function (array $matches): string {
                $varSpec = $matches[1];

                if (str_contains($varSpec, ':-')) {
                    [$varName, $default] = explode(':-', $varSpec, 2);

                    return $_ENV[$varName] ?? $default;
                }

                return $_ENV[$varSpec] ?? '';
            },
            $content,
        );
    }

    /**
     * Create a mock with method call verification.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return MockObject&T
     */
    public function createVerifiableMock(string $className, array $expectedCalls = []): MockObject
    {
        $mock = $this->testCase->createTestMockForFactory($className);

        foreach ($expectedCalls as $methodName => $expectations) {
            // @phpstan-ignore-next-line
            $times = $expectations['times'] ?? $this->testCase->once();
            $invocation = $mock->expects($times)->method($methodName);

            if (isset($expectations['with']) && \is_array($expectations['with'])) {
                $invocation->with(...array_values($expectations['with']));
            }

            if (isset($expectations['willReturn'])) {
                $invocation->willReturn($expectations['willReturn']);
            }
        }

        return $mock;
    }
}
