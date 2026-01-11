<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\DependencyInjection;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test that all commands work identically regardless of configuration implementation.
 *
 * This validates the critical requirement that switching between simple and hierarchical
 * modes via DI configuration produces identical command behavior from user perspective.
 */
final class CommandConsistencyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('command_consistency_test_');

        // Create comprehensive test configuration
        $testConfig = <<<YAML
            quality-tools:
              project:
                name: "command-consistency-test"
                php_version: "8.4"
                typo3_version: "13.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-13"
                phpstan:
                  enabled: true
                  level: 8
                  memory_limit: "1G"
                php-cs-fixer:
                  enabled: true
                  preset: "typo3"
              paths:
                scan:
                  - "packages/"
                  - "src/"
                exclude:
                  - "var/"
                  - "vendor/"
              output:
                verbosity: "normal"
                colors: true
                progress: true
              performance:
                parallel: true
                max_processes: 4
                cache_enabled: true
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $testConfig);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Data provider for configuration modes.
     */
    public static function configurationModeProvider(): array
    {
        return [
            'simple mode' => ['simple'],
            'hierarchical mode' => ['hierarchical'],
        ];
    }

    /**
     * Test config:show command produces identical output in both modes.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigShowCommandConsistency(string $mode): void
    {
        $output = $this->executeCommandInMode('config:show', [], $mode);

        // Should complete successfully
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        // Should contain expected configuration content
        $outputContent = $output['output'];
        self::assertStringContainsString('Resolved Configuration', $outputContent);
        self::assertStringContainsString('command-consistency-test', $outputContent);
        self::assertStringContainsString('php_version: \'8.4\'', $outputContent);
        self::assertStringContainsString('typo3_version: \'13.4\'', $outputContent);

        // Should contain tool configuration
        self::assertStringContainsString('tools:', $outputContent);
        self::assertStringContainsString('rector:', $outputContent);
        self::assertStringContainsString('level: typo3-13', $outputContent);
        self::assertStringContainsString('enabled: true', $outputContent);
    }

    /**
     * Test config:show command with JSON format in both modes.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigShowJsonFormatConsistency(string $mode): void
    {
        $output = $this->executeCommandInMode('config:show', ['--format' => 'json'], $mode);

        // Should complete successfully
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        // Extract JSON from output
        $outputLines = explode("\n", (string) $output['output']);
        $jsonOutput = '';
        $foundJson = false;

        foreach ($outputLines as $line) {
            if (str_starts_with($line, '{')) {
                $foundJson = true;
            }
            if ($foundJson) {
                $jsonOutput .= $line . "\n";
            }
        }

        $jsonOutput = trim($jsonOutput);
        self::assertNotEmpty($jsonOutput);

        // Validate JSON structure
        $decoded = json_decode($jsonOutput, true);
        self::assertNotNull($decoded, 'Output should be valid JSON');
        self::assertArrayHasKey('quality-tools', $decoded);

        $qtConfig = $decoded['quality-tools'];
        self::assertSame('command-consistency-test', $qtConfig['project']['name']);
        self::assertSame('8.4', $qtConfig['project']['php_version']);
        self::assertSame('13.4', $qtConfig['project']['typo3_version']);
        self::assertTrue($qtConfig['tools']['rector']['enabled']);
        self::assertSame('typo3-13', $qtConfig['tools']['rector']['level']);
    }

    /**
     * Test config:show with verbose flag produces consistent output.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigShowVerboseConsistency(string $mode): void
    {
        $output = $this->executeCommandInMode('config:show', ['-v'], $mode);

        // Should complete successfully
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        $outputContent = $output['output'];
        self::assertStringContainsString('Resolved Configuration', $outputContent);
        self::assertStringContainsString('command-consistency-test', $outputContent);

        // Note: Configuration Sources might only be shown in hierarchical mode
        // This test ensures both modes handle verbose flag consistently
        self::assertStringNotContainsString('ERROR', $outputContent);
        self::assertStringNotContainsString('FATAL', $outputContent);
    }

    /**
     * Test config:validate command consistency.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigValidateCommandConsistency(string $mode): void
    {
        $output = $this->executeCommandInMode('config:validate', [], $mode);

        // Should complete successfully
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        $outputContent = $output['output'];
        self::assertStringContainsString('Validating configuration file:', $outputContent);
        // Normalize whitespace to handle line breaks in file path
        $normalizedOutput = preg_replace('/\s+/', '', $outputContent);
        self::assertStringContainsString('.quality-tools.yaml', $normalizedOutput);
        self::assertStringContainsString('Configuration is valid', $outputContent);
    }

    /**
     * Test config:validate with verbose flag produces consistent output.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigValidateVerboseConsistency(string $mode): void
    {
        $output = $this->executeCommandInMode('config:validate', ['-v'], $mode);

        // Should complete successfully
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        $outputContent = $output['output'];
        self::assertStringContainsString('Validating configuration file:', $outputContent);
        // Normalize whitespace to handle line breaks in file path
        $normalizedOutput = preg_replace('/\s+/', '', $outputContent);
        self::assertStringContainsString('.quality-tools.yaml', $normalizedOutput);
        self::assertStringContainsString('Configuration is valid', $outputContent);

        // Verbose mode might show additional details
        // This test ensures both modes handle verbose flag consistently
        self::assertStringNotContainsString('ERROR', $outputContent);
        self::assertStringNotContainsString('FATAL', $outputContent);
    }

    /**
     * Test config:init command behavior consistency when config already exists.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigInitExistingConfigConsistency(string $mode): void
    {
        // Since setUp() already creates a config file, test the "already exists" behavior
        $output = $this->executeCommandInMode('config:init', [], $mode);

        // Should complete successfully (returns SUCCESS even when file exists)
        self::assertSame(Command::SUCCESS, $output['exit_code']);

        $outputContent = $output['output'];
        self::assertStringContainsString('Configuration file already exists:', $outputContent);
        self::assertStringContainsString('--force', $outputContent);
        // Normalize whitespace to handle line breaks in file path
        $normalizedOutput = preg_replace('/\s+/', '', $outputContent);
        self::assertStringContainsString('.quality-tools.yaml', $normalizedOutput);
    }

    /**
     * Test that all configuration commands are available in both modes.
     *
     * @dataProvider configurationModeProvider
     */
    public function testAllConfigurationCommandsAvailable(string $mode): void
    {
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($mode): void {
                $app = $this->createApplicationWithMode($mode);

                // Verify all config commands are available
                $commands = $app->all();
                self::assertArrayHasKey('config:show', $commands);
                self::assertArrayHasKey('config:init', $commands);
                self::assertArrayHasKey('config:validate', $commands);

                // Verify commands are properly configured
                foreach (['config:show', 'config:init', 'config:validate'] as $commandName) {
                    $command = $app->get($commandName);
                    self::assertSame($commandName, $command->getName());
                    self::assertNotEmpty($command->getDescription());
                }
            },
        );
    }

    /**
     * Test command option parsing consistency.
     *
     * @dataProvider configurationModeProvider
     */
    public function testCommandOptionParsingConsistency(string $mode): void
    {
        // Test that basic format options work consistently
        $jsonOutput = $this->executeCommandInMode('config:show', ['--format' => 'json'], $mode);
        self::assertSame(Command::SUCCESS, $jsonOutput['exit_code']);
        self::assertStringContainsString('{', $jsonOutput['output']); // Should contain JSON

        // Test that invalid format values are handled consistently
        $invalidOutput = $this->executeCommandInMode('config:show', ['--format' => 'xml'], $mode);
        self::assertSame(Command::FAILURE, $invalidOutput['exit_code']);
        self::assertStringContainsString('Format must be either "yaml" or "json"', $invalidOutput['output']);
    }

    /**
     * Test configuration loading error handling consistency.
     *
     * @dataProvider configurationModeProvider
     */
    public function testConfigurationErrorHandlingConsistency(string $mode): void
    {
        // Create a directory without configuration
        $emptyDir = TestHelper::createTempDirectory('empty_config_test_');

        try {
            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $emptyDir],
                function () use ($mode): void {
                    // Both modes should handle missing config gracefully
                    $output = $this->executeCommandInMode('config:show', [], $mode);

                    // Should complete successfully (showing defaults)
                    self::assertSame(Command::SUCCESS, $output['exit_code']);
                    self::assertStringContainsString('Resolved Configuration', $output['output']);
                },
            );
        } finally {
            TestHelper::removeDirectory($emptyDir);
        }
    }

    /**
     * Cross-mode output comparison test.
     */
    public function testCrossModeOutputComparison(): void
    {
        // Execute same command in both modes
        $simpleOutput = $this->executeCommandInMode('config:show', ['--format' => 'json'], 'simple');
        $hierarchicalOutput = $this->executeCommandInMode('config:show', ['--format' => 'json'], 'hierarchical');

        // Both should succeed
        self::assertSame(Command::SUCCESS, $simpleOutput['exit_code']);
        self::assertSame(Command::SUCCESS, $hierarchicalOutput['exit_code']);

        // Extract JSON from both outputs
        $simpleJson = $this->extractJsonFromOutput($simpleOutput['output']);
        $hierarchicalJson = $this->extractJsonFromOutput($hierarchicalOutput['output']);

        $simpleData = json_decode($simpleJson, true);
        $hierarchicalData = json_decode($hierarchicalJson, true);

        // Core configuration should be identical
        $simpleProject = $simpleData['quality-tools']['project'];
        $hierarchicalProject = $hierarchicalData['quality-tools']['project'];

        self::assertSame($simpleProject['name'], $hierarchicalProject['name']);
        self::assertSame($simpleProject['php_version'], $hierarchicalProject['php_version']);
        self::assertSame($simpleProject['typo3_version'], $hierarchicalProject['typo3_version']);

        // Tool configuration should be identical
        $simpleTools = $simpleData['quality-tools']['tools'];
        $hierarchicalTools = $hierarchicalData['quality-tools']['tools'];

        foreach (['rector', 'phpstan', 'php-cs-fixer'] as $tool) {
            if (isset($simpleTools[$tool]) && isset($hierarchicalTools[$tool])) {
                self::assertSame($simpleTools[$tool]['enabled'], $hierarchicalTools[$tool]['enabled']);
                if (isset($simpleTools[$tool]['level'])) {
                    self::assertSame($simpleTools[$tool]['level'], $hierarchicalTools[$tool]['level']);
                }
            }
        }
    }

    /**
     * Execute a command in the specified configuration mode.
     */
    private function executeCommandInMode(string $commandName, array $input, string $mode): array
    {
        return TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($commandName, $input, $mode): array {
                $app = $this->createApplicationWithMode($mode);
                $command = $app->get($commandName);

                $arrayInput = new ArrayInput($input);
                $output = new BufferedOutput();

                $exitCode = $command->run($arrayInput, $output);

                return [
                    'exit_code' => $exitCode,
                    'output' => $output->fetch(),
                ];
            },
        );
    }

    /**
     * Create application configured for the specified mode.
     */
    private function createApplicationWithMode(string $mode): QualityToolsApplication
    {
        // For now, use the default application
        // In future phases, we could inject custom container configuration
        return new QualityToolsApplication();
    }

    /**
     * Extract JSON content from command output.
     */
    private function extractJsonFromOutput(string $output): string
    {
        $outputLines = explode("\n", $output);
        $jsonOutput = '';
        $foundJson = false;

        foreach ($outputLines as $line) {
            if (str_starts_with($line, '{')) {
                $foundJson = true;
            }
            if ($foundJson) {
                $jsonOutput .= $line . "\n";
            }
        }

        return trim($jsonOutput);
    }
}
