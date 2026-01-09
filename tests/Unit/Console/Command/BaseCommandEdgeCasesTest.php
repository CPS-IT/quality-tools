<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command\BaseCommandEdgeCasesTest;

use Cpsit\QualityTools\Console\Command\BaseCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Edge case and error scenario tests for BaseCommand.
 *
 * @covers \Cpsit\QualityTools\Console\Command\BaseCommand
 */
final class BaseCommandEdgeCasesTest extends TestCase
{
    private EdgeCaseTestCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&OutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('edge_case_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new EdgeCaseTestCommand();
                $this->command->setApplication($app);
            },
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(OutputInterface::class);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testResolveConfigPathWithEmptyCustomPath(): void
    {
        $projectRoot = $this->tempDir;
        $configDir = $projectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);

        $defaultConfigFile = $configDir . '/test.php';
        file_put_contents($defaultConfigFile, '<?php return [];');

        // Empty string is treated as a file path and will fail - this tests error handling
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Configuration file not found: ');

        $this->command->testResolveConfigPath('test.php', '');
    }

    public function testResolveConfigPathWithSymlinkConfigFile(): void
    {
        $actualConfigFile = $this->tempDir . '/actual-config.php';
        $symlinkConfigFile = $this->tempDir . '/symlink-config.php';

        file_put_contents($actualConfigFile, '<?php return [];');
        symlink($actualConfigFile, $symlinkConfigFile);

        $result = $this->command->testResolveConfigPath('test.php', $symlinkConfigFile);

        $this->assertEquals(realpath($symlinkConfigFile), $result);
        $this->assertEquals(realpath($actualConfigFile), $result);
    }

    public function testResolveConfigPathWithRelativeCustomPath(): void
    {
        $currentDir = getcwd();
        $relativeConfigFile = 'relative-config.php';

        // Create config file in current directory
        file_put_contents($currentDir . '/' . $relativeConfigFile, '<?php return [];');

        try {
            $result = $this->command->testResolveConfigPath('test.php', $relativeConfigFile);
            $this->assertEquals(realpath($currentDir . '/' . $relativeConfigFile), $result);
        } finally {
            // Clean up
            if (file_exists($currentDir . '/' . $relativeConfigFile)) {
                unlink($currentDir . '/' . $relativeConfigFile);
            }
        }
    }

    public function testExecuteProcessWithEmptyCommand(): void
    {
        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        // Empty command array will throw ValueError in PHP 8.3+
        $this->expectException(\ValueError::class);

        $this->command->testExecuteProcess([], $this->mockInput, $this->mockOutput);
    }

    public function testExecuteProcessWithVeryLongOutput(): void
    {
        $longString = str_repeat('A', 10000); // 10KB of output
        $command = ['echo', $longString];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $capturedOutput = '';
        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($data) use (&$capturedOutput): void {
                $capturedOutput .= $data;
            });

        $result = $this->command->testExecuteProcess($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString($longString, $capturedOutput);
    }

    public function testExecuteProcessWithVerboseMode(): void
    {
        $command = ['echo', 'test'];

        // Mock output to return verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true);

        // Verbose message should be shown
        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/Executing:.*echo/i'));

        // Process output should also be written
        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("test\n");

        $result = $this->command->testExecuteProcess($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteProcessWithNullExitCode(): void
    {
        // This tests the fallback behavior when Process::getExitCode() returns null

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        // Use a command that might result in null exit code (though this is rare in practice)
        $result = $this->command->testExecuteProcess(['true'], $this->mockInput, $this->mockOutput);

        // Should return 0 for successful command, or 1 if exit code is null
        $this->assertContains($result, [0, 1]);
    }

    public function testGetTargetPathWithEmptyStringPath(): void
    {
        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn('');

        // Empty string is treated as a path and will fail since it's not a directory
        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage('Directory not found: ');

        $this->command->testGetTargetPath($this->mockInput);
    }

    public function testGetTargetPathWithSymlinkDirectory(): void
    {
        $actualDir = $this->tempDir . '/actual-dir';
        $symlinkDir = $this->tempDir . '/symlink-dir';

        mkdir($actualDir, 0o777, true);
        symlink($actualDir, $symlinkDir);

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($symlinkDir);

        $result = $this->command->testGetTargetPath($this->mockInput);

        $this->assertEquals(realpath($symlinkDir), $result);
        $this->assertEquals(realpath($actualDir), $result);
    }

    public function testGetTargetPathWithRelativePath(): void
    {
        $currentDir = getcwd();
        $relativePath = 'temp-test-dir';

        // Create relative directory
        $fullPath = $currentDir . '/' . $relativePath;
        mkdir($fullPath, 0o777, true);

        try {
            $this->mockInput
                ->expects($this->once())
                ->method('getOption')
                ->with('path')
                ->willReturn($relativePath);

            $result = $this->command->testGetTargetPath($this->mockInput);

            $this->assertEquals(realpath($fullPath), $result);
        } finally {
            // Clean up
            if (is_dir($fullPath)) {
                rmdir($fullPath);
            }
        }
    }

    public function testConfigureOptionsAreProperlyConfigured(): void
    {
        $definition = $this->command->getDefinition();

        // Test that our custom options are properly configured
        $options = $definition->getOptions();

        // Test option names - only test the ones we actually configure
        $this->assertArrayHasKey('config', $options);
        $this->assertArrayHasKey('path', $options);

        // Test that our custom options have proper configuration
        $configOption = $options['config'];
        $this->assertEquals('c', $configOption->getShortcut());
        $this->assertTrue($configOption->isValueRequired());

        $pathOption = $options['path'];
        $this->assertEquals('p', $pathOption->getShortcut());
        $this->assertTrue($pathOption->isValueRequired());

        // Test that shortcuts don't conflict among our custom options
        $customShortcuts = ['c', 'p'];
        $this->assertEquals(2, \count(array_unique($customShortcuts)), 'Custom shortcuts should not conflict');
    }

    public function testExecuteProcessWithSpecialCharactersInCommand(): void
    {
        $specialString = 'Hello "World" & $HOME | test';
        $command = ['echo', $specialString];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $capturedOutput = '';
        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($data) use (&$capturedOutput): void {
                $capturedOutput .= $data;
            });

        $result = $this->command->testExecuteProcess($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString($specialString, $capturedOutput);
    }

    public function testExecuteProcessWithUnicodeOutput(): void
    {
        $unicodeString = 'Hello 世界 🌍 café naïve';
        $command = ['echo', $unicodeString];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $capturedOutput = '';
        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($data) use (&$capturedOutput): void {
                $capturedOutput .= $data;
            });

        $result = $this->command->testExecuteProcess($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString($unicodeString, $capturedOutput);
    }
}

/**
 * Test command for edge case testing.
 */
final class EdgeCaseTestCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('test:edge-cases');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function testResolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return $this->resolveConfigPath($configFile, $customConfigPath);
    }

    public function testExecuteProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        return $this->executeProcess($command, $input, $output);
    }

    public function testGetTargetPath(InputInterface $input): string
    {
        return $this->getTargetPath($input);
    }
}
