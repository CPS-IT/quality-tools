<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\BaseCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @covers \Cpsit\QualityTools\Console\Command\BaseCommand
 */
final class BaseCommandTest extends TestCase
{
    private TestableBaseCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('basecommand_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new TestableBaseCommand();
                $this->command->setApplication($app);
            },
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(ConsoleOutputInterface::class);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testConfigureAddsExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        // Only test the options that BaseCommand actually defines
        $this->assertTrue($definition->hasOption('config'));
        $this->assertTrue($definition->hasOption('path'));

        // verbose and quiet are built-in Symfony Console options, not custom ones
        // They're added by the parent Command class, not by our configure() method

        $configOption = $definition->getOption('config');
        $this->assertEquals('c', $configOption->getShortcut());
        $this->assertTrue($configOption->isValueRequired());
        $this->assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        $this->assertEquals('p', $pathOption->getShortcut());
        $this->assertTrue($pathOption->isValueRequired());
        $this->assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());
    }

    public function testGetProjectRootReturnsApplicationProjectRoot(): void
    {
        $result = $this->command->getProjectRootPublic();

        $this->assertEquals(realpath($this->tempDir), $result);
    }

    public function testGetProjectRootThrowsExceptionWhenApplicationIsNotQualityToolsApplication(): void
    {
        $regularApplication = $this->createMock(Application::class);
        $this->command->setApplication($regularApplication);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command must be run within QualityToolsApplication');

        $this->command->getProjectRootPublic();
    }

    // Note: resolveConfigPath tests have been moved to AbstractToolCommandTest
    // since that method was moved from BaseCommand to AbstractToolCommand

    public function testExecuteProcessWithVerboseMode(): void
    {
        $command = ['echo', 'test output'];

        // Mock output to return verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true);

        $this->mockOutput
            ->expects($this->once())
            ->method('writeln')
            ->with($this->matchesRegularExpression('/Executing:.*echo/i'));

        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("test output\n");

        $result = $this->command->executeProcessPublic($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteProcessWithNormalMode(): void
    {
        $command = ['echo', 'test output'];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        // In normal mode, no verbose message should be shown
        $this->mockOutput
            ->expects($this->never())
            ->method('writeln');

        // But the process output should still be written (Symfony handles quiet mode automatically)
        $this->mockOutput
            ->expects($this->once())
            ->method('write')
            ->with("test output\n");

        $result = $this->command->executeProcessPublic($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteProcessWithErrorOutput(): void
    {
        $command = ['bash', '-c', 'echo "test with error"; echo "error message" >&2; exit 0'];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $mockErrorOutput = $this->createMock(OutputInterface::class);
        $this->mockOutput
            ->method('getErrorOutput')
            ->willReturn($mockErrorOutput);

        // Test that the process runs successfully and the error output interface is used
        $result = $this->command->executeProcessPublic($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(0, $result);
    }

    public function testExecuteProcessReturnsExitCode(): void
    {
        $command = ['bash', '-c', 'exit 42'];

        // Mock output to return non-verbose mode
        $this->mockOutput
            ->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false);

        $result = $this->command->executeProcessPublic($command, $this->mockInput, $this->mockOutput);

        $this->assertEquals(42, $result);
    }

    public function testGetTargetPathWithCustomPath(): void
    {
        $customPath = $this->tempDir . '/custom-target';
        mkdir($customPath, 0o777, true);

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($customPath);

        $result = $this->command->getTargetPathPublic($this->mockInput);

        $this->assertEquals(realpath($customPath), $result);
    }

    public function testGetTargetPathWithCustomPathThrowsExceptionWhenDirectoryNotExists(): void
    {
        $nonExistentPath = $this->tempDir . '/non-existent';

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($nonExistentPath);

        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage("Directory not found: {$nonExistentPath}");

        $this->command->getTargetPathPublic($this->mockInput);
    }

    public function testGetTargetPathWithoutCustomPathReturnsProjectRoot(): void
    {
        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn(null);

        $result = $this->command->getTargetPathPublic($this->mockInput);

        $this->assertEquals(realpath($this->tempDir), $result);
    }

    public function testGetTargetPathThrowsExceptionWhenCustomPathIsFile(): void
    {
        $filePath = $this->tempDir . '/testfile.txt';
        file_put_contents($filePath, 'test content');

        $this->mockInput
            ->expects($this->once())
            ->method('getOption')
            ->with('path')
            ->willReturn($filePath);

        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage("Directory not found: {$filePath}");

        $this->command->getTargetPathPublic($this->mockInput);
    }
}

/**
 * Testable concrete implementation of BaseCommand for testing purposes.
 */
final class TestableBaseCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('test:base-command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function getProjectRootPublic(): string
    {
        return $this->getProjectRoot();
    }

    public function executeProcessPublic(
        array $command,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        return $this->executeProcess($command, $input, $output);
    }

    public function getTargetPathPublic(InputInterface $input): string
    {
        return $this->getTargetPath($input);
    }
}
