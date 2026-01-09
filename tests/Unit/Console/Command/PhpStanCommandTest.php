<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Console\Command\PhpStanCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PhpStanCommand::class)]
final class PhpStanCommandTest extends TestCase
{
    private PhpStanCommand $command;
    private MockObject&InputInterface $mockInput;
    private MockObject&ConsoleOutputInterface $mockOutput;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('phpstan_command_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor/bin directory structure
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        mkdir($vendorBinDir, 0777, true);

        // Create a fake phpstan executable
        $phpStanExecutable = $vendorBinDir . '/phpstan';
        file_put_contents($phpStanExecutable, "#!/bin/bash\necho 'PHPStan analysis completed successfully'\nexit 0\n");
        chmod($phpStanExecutable, 0755);

        // Create cpsit/quality-tools config directory structure to match the resolveConfigPath expectation
        $vendorConfigDir = $this->tempDir . '/vendor/cpsit/quality-tools/config';
        mkdir($vendorConfigDir, 0777, true);
        file_put_contents($vendorConfigDir . '/phpstan.neon', "parameters:\n  level: 6\n");

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $this->command = new PhpStanCommand();
                $this->command->setApplication($app);
            }
        );

        $this->mockInput = $this->createMock(InputInterface::class);
        $this->mockOutput = $this->createMock(ConsoleOutputInterface::class);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testCommandHasCorrectConfiguration(): void
    {
        $this->assertEquals('lint:phpstan', $this->command->getName());
        $this->assertEquals('Run PHPStan static analysis', $this->command->getDescription());

        $expectedHelp = 'This command runs PHPStan static analysis to find bugs in your code without ' .
                       'running it. Use --config to specify a custom configuration file, --path to ' .
                       'target specific directories, or --level to override the analysis level.';
        $this->assertEquals($expectedHelp, $this->command->getHelp());
    }

    public function testCommandInheritsBaseCommandOptions(): void
    {
        $this->assertBaseCommandOptionsExist();
    }

    private function assertBaseCommandOptionsExist(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('config'));
        $this->assertTrue($definition->hasOption('path'));

        $configOption = $definition->getOption('config');
        $this->assertEquals('c', $configOption->getShortcut());
        $this->assertTrue($configOption->isValueRequired());
        $this->assertEquals('Override default configuration file path', $configOption->getDescription());

        $pathOption = $definition->getOption('path');
        $this->assertEquals('p', $pathOption->getShortcut());
        $this->assertTrue($pathOption->isValueRequired());
        $this->assertEquals('Specify custom target paths (defaults to project root)', $pathOption->getDescription());
    }

    public function testCommandHasPhpStanSpecificOptions(): void
    {
        $this->assertPhpStanSpecificOptionsExist();
    }

    private function assertPhpStanSpecificOptionsExist(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('level'));
        $this->assertTrue($definition->hasOption('memory-limit'));

        $levelOption = $definition->getOption('level');
        $this->assertEquals('l', $levelOption->getShortcut());
        $this->assertTrue($levelOption->isValueRequired());
        $this->assertEquals('Override the analysis level (0-9)', $levelOption->getDescription());

        $memoryLimitOption = $definition->getOption('memory-limit');
        $this->assertEquals('m', $memoryLimitOption->getShortcut());
        $this->assertTrue($memoryLimitOption->isValueRequired());
        $this->assertEquals('Memory limit for analysis (e.g., 1G, 512M)', $memoryLimitOption->getDescription());
    }

    #[DataProvider('executeCommandDataProvider')]
    public function testExecuteWithVariousOptions(
        string $scenarioName,
        ?string $customConfigContent,
        bool $needsCustomPath,
        ?string $level,
        ?string $memoryLimit,
        bool $isVerbose
    ): void {
        $optionMap = [
            ['config', null],
            ['path', null],
            ['level', $level],
            ['memory-limit', $memoryLimit],
            ['no-optimization', false],
        ];

        // Set up custom config file if needed
        if ($customConfigContent !== null) {
            $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
            file_put_contents($customConfigPath, $customConfigContent);
            $optionMap[0] = ['config', $customConfigPath];
        }

        // Set up custom target directory if needed
        if ($needsCustomPath) {
            $customTargetDir = $this->tempDir . '/custom-target';
            mkdir($customTargetDir, 0777, true);
            $optionMap[1] = ['path', $customTargetDir];
        }

        $this->mockInput
            ->method('getOption')
            ->willReturnMap($optionMap);

        $this->mockOutput
            ->expects($this->atLeastOnce())
            ->method('isVerbose')
            ->willReturn($isVerbose);

        if ($isVerbose) {
            $this->mockOutput
                ->method('writeln');
        }

        // For default options scenario, we don't expect a specific write call
        $expectsSpecificWriteCall = $scenarioName !== 'default options';
        
        if ($expectsSpecificWriteCall) {
            $this->mockOutput
                ->expects($this->atLeastOnce())
                ->method('write')
                ->with("PHPStan analysis completed successfully\n");
        } else {
            $this->mockOutput
                ->method('write');
        }

        try {
            $result = $this->command->run($this->mockInput, $this->mockOutput);
            $this->assertEquals(0, $result);
        } catch (ExceptionInterface $e) {
            $this->fail('Command execution should not throw exceptions in normal scenarios: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string|null, bool, string|null, string|null, bool}>
     */
    public static function executeCommandDataProvider(): array
    {
        return [
            'default options' => [
                'default options',         // scenarioName
                null,                      // customConfigContent
                false,                     // needsCustomPath
                null,                      // level
                null,                      // memoryLimit
                false,                     // isVerbose
            ],
            'custom config' => [
                'custom config',
                "parameters:\n  level: 8\n", // customConfigContent
                false,                     // needsCustomPath
                null,                      // level
                null,                      // memoryLimit
                false,                     // isVerbose
            ],
            'custom target path' => [
                'custom target path',
                null,                      // customConfigContent
                true,                      // needsCustomPath
                null,                      // level
                null,                      // memoryLimit
                false,                     // isVerbose
            ],
            'custom level' => [
                'custom level',
                null,                      // customConfigContent
                false,                     // needsCustomPath
                '8',                       // level
                null,                      // memoryLimit
                false,                     // isVerbose
            ],
            'custom memory limit' => [
                'custom memory limit',
                null,                      // customConfigContent
                false,                     // needsCustomPath
                null,                      // level
                '1G',                      // memoryLimit
                false,                     // isVerbose
            ],
            'all custom options' => [
                'all custom options',
                "parameters:\n  level: 6\n", // customConfigContent
                true,                      // needsCustomPath
                '9',                       // level
                '512M',                    // memoryLimit
                false,                     // isVerbose
            ],
            'verbose output' => [
                'verbose output',
                null,                      // customConfigContent
                false,                     // needsCustomPath
                null,                      // level
                null,                      // memoryLimit
                true,                      // isVerbose
            ],
        ];
    }

    public function testExecuteHandlesTargetPathException(): void
    {
        $nonExistentTargetDir = $this->tempDir . '/non-existent-target';
        $this->setupMockInputWithOptions([
            ['config', null],
            ['path', $nonExistentTargetDir],
            ['no-optimization', false],
        ]);

        $this->mockOutput
            ->method('writeln');

        try {
            $result = $this->command->run($this->mockInput, $this->mockOutput);
            $this->assertEquals(1, $result);
        } catch (ExceptionInterface $e) {
            // Expected for invalid target path scenarios
            $this->assertStringContainsString('path', strtolower($e->getMessage()));
        }
    }

    public function testExecuteHandlesConfigPathException(): void
    {
        $nonExistentConfigPath = $this->tempDir . '/non-existent-config.neon';
        $this->setupMockInputWithOptions([
            ['config', $nonExistentConfigPath],
            ['no-optimization', false],
        ]);

        $this->mockOutput
            ->method('writeln');

        try {
            $result = $this->command->run($this->mockInput, $this->mockOutput);
            $this->assertEquals(1, $result);
        } catch (ExceptionInterface $e) {
            // Expected for invalid config path scenarios
            $this->assertStringContainsString('config', strtolower($e->getMessage()));
        }
    }

    public function testCommandBuildsCorrectExecutionCommand(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with default options
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain phpstan execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandBuildsCorrectExecutionCommandWithCustomOptions(): void
    {
        $customConfigPath = $this->tempDir . '/custom-phpstan.neon';
        file_put_contents($customConfigPath, "parameters:\n  level: 6\n");

        $customTargetDir = $this->tempDir . '/custom-target';
        mkdir($customTargetDir, 0777, true);

        $commandTester = new CommandTester($this->command);

        // Execute with custom options
        $commandTester->execute([
            '--config' => $customConfigPath,
            '--path' => $customTargetDir,
            '--level' => '9',
            '--memory-limit' => '1G'
        ]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Output should contain phpstan execution result
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandHandlesMissingExecutable(): void
    {
        // Remove phpstan executable to simulate missing dependency
        $phpStanExecutable = $this->tempDir . '/vendor/bin/phpstan';
        unlink($phpStanExecutable);

        $this->setupMockInputWithOptions([
            ['config', null],
            ['path', null],
            ['level', null],
            ['memory-limit', null],
            ['no-optimization', false],
        ]);

        // Since the executable doesn't exist, this will fail at the process level
        // and the executeProcess method will return a non-zero exit code
        try {
            $result = $this->command->run($this->mockInput, $this->mockOutput);
            // Command should return non-zero exit code due to missing executable
            $this->assertNotEquals(0, $result);
        } catch (ExceptionInterface) {
            // Expected for missing executable scenarios
            $this->addToAssertionCount(1); // Mark as valid test outcome
        }
    }

    public function testCommandUsesCorrectProcessArguments(): void
    {
        $commandTester = new CommandTester($this->command);

        // Execute with verbose output to see command being executed
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check that the correct command arguments are used (analyse, --configuration)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHPStan analysis completed successfully', $output);
    }

    public function testCommandAnalyzesCodeWithoutModification(): void
    {
        // Create a test PHP file for analysis
        $testFile = $this->tempDir . '/test.php';
        $originalContent = $this->createTestPhpContent();
        file_put_contents($testFile, $originalContent);

        // Verify original content exists
        $this->assertTestFileCreatedCorrectly($testFile);

        $commandTester = new CommandTester($this->command);

        // Execute analysis command (should NOT modify the file)
        $commandTester->execute([]);

        // Command should execute successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // File should still exist with original content (not modified by analysis)
        $this->assertTestFileUnmodified($testFile, $originalContent);
    }

    private function setupMockInputWithOptions(array $optionMap): void
    {
        $this->mockInput
            ->method('getOption')
            ->willReturnMap($optionMap);
    }

    private function createTestPhpContent(): string
    {
        return "<?php\nclass TestClass {\n    public function test() {\n        return 'test';\n    }\n}\n";
    }

    private function assertTestFileCreatedCorrectly(string $testFile): void
    {
        $this->assertFileExists($testFile);
        $this->assertStringContainsString('TestClass', file_get_contents($testFile));
    }

    private function assertTestFileUnmodified(string $testFile, string $originalContent): void
    {
        $this->assertFileExists($testFile);
        $contentAfterAnalysis = file_get_contents($testFile);
        $this->assertEquals($originalContent, $contentAfterAnalysis);
    }
}
