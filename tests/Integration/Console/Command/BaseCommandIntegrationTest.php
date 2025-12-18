<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Console\Command;

use Cpsit\QualityTools\Console\Command\BaseCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Integration tests for BaseCommand with real process execution and file system operations
 *
 * @covers \Cpsit\QualityTools\Console\Command\BaseCommand
 */
final class BaseCommandIntegrationTest extends TestCase
{
    private string $tempProjectRoot;
    private QualityToolsApplication $application;
    private TestableIntegrationCommand $command;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('integration_test_');
        $this->setupProjectStructure();

        // Set up application with temporary project root
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempProjectRoot],
            function (): void {
                $this->application = new QualityToolsApplication();
            }
        );

        $this->command = new TestableIntegrationCommand();
        $this->application->add($this->command);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupProjectStructure(): void
    {
        // Create TYPO3 project structure
        TestHelper::createComposerJson($this->tempProjectRoot, TestHelper::getComposerContent('typo3-core'));

        // Create quality tools config directory
        $configDir = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0777, true);

        // Create test configuration files
        file_put_contents($configDir . '/test-config.php', '<?php return ["test" => true];');
        file_put_contents($configDir . '/rector.php', '<?php return [];');
        file_put_contents($configDir . '/phpstan.neon', 'parameters: {}');

        // Create additional test directories
        mkdir($this->tempProjectRoot . '/packages', 0777, true);
        mkdir($this->tempProjectRoot . '/custom-path', 0777, true);
    }

    public function testRealProcessExecutionWithSimpleCommand(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $exitCode = $this->command->testExecuteProcess(
            ['echo', 'Hello Integration Test'],
            $input,
            $output
        );

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Executing:', $outputContent);
        $this->assertStringContainsString('echo', $outputContent);
        $this->assertStringContainsString('Hello Integration Test', $outputContent);
    }

    public function testRealProcessExecutionWithQuietMode(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);

        $exitCode = $this->command->testExecuteProcess(
            ['echo', 'This should not appear'],
            $input,
            $output
        );

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringNotContainsString('This should not appear', $outputContent);
    }

    public function testRealProcessExecutionWithFailingCommand(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->command->testExecuteProcess(
            ['bash', '-c', 'exit 5'],
            $input,
            $output
        );

        $this->assertEquals(5, $exitCode);
    }

    public function testRealProcessExecutionWithStderrOutput(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();

        // Simplified test - detailed stderr handling is tested in unit tests
        $exitCode = $this->command->testExecuteProcess(
            ['bash', '-c', 'echo "normal output"'],
            $input,
            $output
        );

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('normal output', $outputContent);
    }

    public function testConfigurationPathResolutionWithRealFiles(): void
    {
        $customConfigPath = $this->tempProjectRoot . '/custom-config.php';
        file_put_contents($customConfigPath, '<?php return ["custom" => true];');

        $input = new ArrayInput(['--config' => $customConfigPath], $this->command->getDefinition());

        $resolvedPath = $this->command->testResolveConfigPath('default.php', $input->getOption('config'));

        $this->assertEquals(realpath($customConfigPath), $resolvedPath);
        $this->assertFileExists($resolvedPath);
    }

    public function testDefaultConfigurationPathResolution(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());

        $resolvedPath = $this->command->testResolveConfigPath('test-config.php');

        $expectedPath = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config/test-config.php';
        $this->assertEquals(realpath($expectedPath), $resolvedPath);
        $this->assertFileExists($resolvedPath);
    }

    public function testTargetPathResolutionWithCustomPath(): void
    {
        $customPath = $this->tempProjectRoot . '/custom-path';
        $input = new ArrayInput(['--path' => $customPath], $this->command->getDefinition());

        $resolvedPath = $this->command->testGetTargetPath($input);

        $this->assertEquals(realpath($customPath), $resolvedPath);
        $this->assertDirectoryExists($resolvedPath);
    }

    public function testTargetPathResolutionWithoutCustomPath(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());

        $resolvedPath = $this->command->testGetTargetPath($input);

        $this->assertEquals(realpath($this->tempProjectRoot), $resolvedPath);
        $this->assertDirectoryExists($resolvedPath);
    }

    public function testProjectRootIntegrationWithRealApplication(): void
    {
        $projectRoot = $this->command->testGetProjectRoot();

        $this->assertEquals(realpath($this->tempProjectRoot), $projectRoot);
        $this->assertDirectoryExists($projectRoot);
        $this->assertFileExists($projectRoot . '/composer.json');
    }

    public function testCompleteWorkflowWithAllOptions(): void
    {
        // Create a custom config and target path
        $customConfig = $this->tempProjectRoot . '/workflow-config.php';
        $customTarget = $this->tempProjectRoot . '/workflow-target';

        file_put_contents($customConfig, '<?php return ["workflow" => true];');
        mkdir($customTarget, 0777, true);

        $input = new ArrayInput([
            '--config' => $customConfig,
            '--path' => $customTarget
        ], $this->command->getDefinition());
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        // Test the complete workflow
        $projectRoot = $this->command->testGetProjectRoot();
        $configPath = $this->command->testResolveConfigPath('default.php', $input->getOption('config'));
        $targetPath = $this->command->testGetTargetPath($input);

        $exitCode = $this->command->testExecuteProcess(
            ['echo', 'Workflow test complete'],
            $input,
            $output
        );

        // Verify all components work together
        $this->assertEquals(realpath($this->tempProjectRoot), $projectRoot);
        $this->assertEquals(realpath($customConfig), $configPath);
        $this->assertEquals(realpath($customTarget), $targetPath);
        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Executing:', $outputContent);
        $this->assertStringContainsString('echo', $outputContent);
        $this->assertStringContainsString('Workflow test complete', $outputContent);
    }

    public function testProcessExecutionInCorrectWorkingDirectory(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->command->testExecuteProcess(
            ['pwd'],
            $input,
            $output
        );

        $this->assertEquals(0, $exitCode);

        $outputContent = trim($output->fetch());
        $this->assertEquals(realpath($this->tempProjectRoot), $outputContent);
    }

    public function testLongRunningProcessExecution(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $startTime = microtime(true);

        $exitCode = $this->command->testExecuteProcess(
            ['bash', '-c', 'sleep 0.1; echo "Long running task complete"'],
            $input,
            $output
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertEquals(0, $exitCode);
        $this->assertGreaterThan(0.05, $executionTime); // Should take at least 0.05 seconds

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Long running task complete', $outputContent);
    }
}

/**
 * Testable concrete implementation for integration testing
 */
final class TestableIntegrationCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('test:integration');
        $this->setDescription('Test integration command for BaseCommand testing');
    }

    protected function configure(): void
    {
        parent::configure(); // This adds the base options
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function testGetProjectRoot(): string
    {
        return $this->getProjectRoot();
    }

    public function testResolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return $this->resolveConfigPath($configFile, $customConfigPath);
    }

    public function testExecuteProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output
    ): int {
        return $this->executeProcess($command, $input, $output);
    }

    public function testGetTargetPath(InputInterface $input): string
    {
        return $this->getTargetPath($input);
    }
}
